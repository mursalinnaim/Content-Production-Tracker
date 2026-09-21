# Background Content Generation Specification

## Flow

1. The authenticated owner clicks **Generate Content Plan**.
2. Laravel checks authentication, project ownership, and required project input.
3. Inside a database transaction, Laravel reuses an existing `pending` or `processing` generation for that project, or creates one new `pending` generation. The accepted prompt and configured model are saved at this point.
4. The generation job is dispatched after the transaction commits. The web request returns `202 Accepted` with the generation ID and current status; it never calls OpenAI.
5. The database queue stores the job. A separate `queue:work` process claims it.
6. The worker safely changes `pending` to `processing`, loads the saved generation, and calls the existing OpenAI service using the saved prompt/model.
7. A validated provider result is written back to the same generation as `completed`, including token usage. Safe provider failures become `failed` with an internal error code.
8. The Vue page polls the owner-only generation status endpoint approximately every two seconds while the generation is active. Polling stops at `completed` or `failed`.
9. Refreshing or returning to the page loads the latest saved generation and resumes polling only when its status is `pending` or `processing`.

## Stored generation fields

`content_generations` stores the project ID, status, accepted prompt, configured model, validated response, token usage, safe error code, and timestamps. Day 3 completed and failed records remain valid. Day 4 adds processing timestamps and a safe error message; no API key or authorization header is stored.

## Status lifecycle

- `pending`: Waiting to start
- `processing`: Generating
- `completed`: Completed
- `failed`: Failed

Normal transitions are `pending -> processing -> completed` or `pending -> processing -> failed`. A pending generation may become failed if it cannot safely start. Completed and failed generations are terminal and must not be restarted by duplicate delivery.

## Duplicate prevention

A project may have many generations over time, but only one generation may be `pending` or `processing` at a time. The generate request locks the project row while it checks for an active generation and creates one when necessary. This makes concurrent requests serialize on MySQL rather than relying on a disabled Vue button. A duplicate request returns the existing active generation.

The job receives only the generation ID. It does not serialize the user or project. The worker claims the generation under a row lock before making the provider call. Laravel's `WithoutOverlapping` middleware also uses the generation ID as a second guard against simultaneous duplicate deliveries.

## Queue and transaction coordination

The database queue is used; Redis and Horizon are intentionally out of scope. Queue dispatch uses `afterCommit()` so a job cannot be visible to a worker before its generation row is committed. Database queue `retry_after` is configured longer than the job timeout. The OpenAI HTTP timeout is shorter than the queue job/worker timeout so the application has an opportunity to record a safe failure.

The documented local worker is:

```text
php artisan queue:work database --queue=default --tries=2 --timeout=75
```

With a 60-second OpenAI HTTP timeout, a 75-second job timeout, and a 90-second database queue `retry_after`, a worker should not be duplicated while the original worker is still within its configured timeout window.

## Retry policy

Only an explicit provider rate-limit response (`429`) is retryable. It receives one additional attempt after a short delay. Invalid output, missing configuration, ordinary provider errors, and ambiguous connection/read timeouts are not automatically retried because repeating a paid external request can create another charge.

Laravel's job attempt limit is finite. Terminal job failures are converted to the generation's `failed` state with a safe message/code rather than exposing provider details.

## Exactly-once limitation

The database state transition and the external OpenAI request cannot be made exactly-once as one atomic transaction. A worker can die after OpenAI accepts the request but before the result is saved. This implementation therefore prioritizes preventing known duplicate active jobs and automatic retries, while documenting that manual recovery may be required for an uncertain external-call outcome. It does not silently make another paid provider request in that ambiguous case.

## Status endpoint

`GET /projects/{project}/generations/{generation}` checks that the authenticated user owns the project and that the generation belongs to that project. It performs no provider request and returns only UI-safe fields: generation ID, status, safe message, validated response, model, token usage, and processing/completion timestamps.

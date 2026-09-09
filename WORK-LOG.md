# Day 1 Work Log

## Start Time

6:00 pm, Wednesday, September 2, 2026

## Environment

- OS: Windows
- PHP: 8.4.25
- Composer: 2.10.3
- Node.js: 24.19.0
- npm: 11.17.0
- Git: 2.55.0
- MySQL: XAMPP MySQL
- MySQL CLI: Not available in PATH (`mysql --version` not recognized)
- Laravel environment: Laravel Herd

## Tasks Completed

## Testing

- `php artisan test`

    - PASS `Tests\Feature\DashboardTest`
    - ✓ guests are redirected to the login page 0.03s
    - ✓ authenticated users can visit the dashboard 0.05s
    - ✓ authenticated users can see Internship Progress on the dashboard
    - 24 tests passed
    - 3 tests skipped because two-factor authentication is not enabled
    - 0 tests failed
    - 65 assertions

- `npm run type-check`

    - Passed with no TypeScript errors

## Problems

- `mysql --version` was not recognized because the MySQL CLI executable from XAMPP is not available in the system PATH.
- Laravel was still able to connect to the XAMPP MySQL server and migrations completed successfully.

## What I Learned

I learned how a Laravel application is structured and how the different parts of the project fit together. I also gained a better understanding of how Laravel, Vue, and Inertia work together, especially how data is passed from the Laravel backend through Inertia to Vue components and displayed in the frontend. I also became more familiar with Laravel's project structure, routing, authentication, and the overall design of the application.

## End Time

11:00 pm, Wednesday, September 2, 2026

# Day 2 Work Log

## Start Time

8:00 pm, Thursday, September 3, 2026

## Starting Branch and Commit

- Branch: `feature/project-foundation`
- Starting commit: `7b572f4`

## Test Command

```text
php artisan test
```

## Initial Test Result

- `php artisan test`

    - PASS `Tests\Feature\DashboardTest`
    - ✓ guests are redirected to the login page 0.03s
    - ✓ authenticated users can visit the dashboard 0.05s
    - ✓ authenticated users can see Internship Progress on the dashboard
    - 24 tests passed
    - 3 tests skipped because two-factor authentication is not enabled
    - 0 tests failed
    - 65 assertions

- `npm run type-check`

    - Passed with no TypeScript errors

## Setup Errors

No setup errors occurred at the start of Day 2.

## Tasks Completed

- Created the project data specification in `docs/PROJECTS-SPEC.md`.
- Added the database relationship between users and projects.
- Created the `Project` model and migration.
- Added the `projects()` relationship to the `User` model.
- Added project factory data using the required content types and statuses.
- Added a development demo user and sample projects through the database seeder.
- Added the Projects controller and authenticated `/projects` route.
- Added the Projects Vue page using Vue 3 and TypeScript.
- Added the Projects navigation item to the application sidebar.
- Displayed project title, content type, status, due date, and project count.
- Added an empty state for users without projects.
- Added feature tests for authentication, ownership, ordering, and the empty state.
- Verified the full test suite.

## What I Learned

I learned how Laravel model relationships connect the `User` and `Project` models, how factories and seeders can create realistic development data, and how Inertia passes typed project data from Laravel to Vue. I also learned how to use the authenticated user when querying projects so that users only receive their own data.

## Problems and Solutions

The project due date was initially displayed in the browser as a full ISO timestamp such as `2026-09-18T00:00:00.000000Z`. The database and factory were working correctly, but Laravel's date cast serialized the value as a date-time string for Inertia. I formatted the value in Vue so that only the `YYYY-MM-DD` portion is displayed.

## Final Test Result

- `php artisan test`

    - PASS `Tests\Feature\DashboardTest`
    - ✓ guests are redirected to the login page 0.03s
    - ✓ authenticated users can visit the dashboard 0.05s
    - ✓ authenticated users can see Internship Progress on the dashboard
    - PASS `Tests\Feature\ProjectTest`
    - ✓ it allows a user to have many projects
    - ✓ it allows a project to belong to a user
    - ✓ it deletes a users projects when the user is deleted
    - ✓ it blocks guests from accessing the projects page
    - ✓ it allows authenticated users to access the projects page
    - ✓ it does not show projects belonging to another user
    - ✓ it shows projects newest first
    - ✓ it shows an empty projects list when the user has no projects
    - 33 tests passed
    - 3 tests skipped
    - 0 tests failed
    - 114 assertions

- `npm run type-check`

    - Passed with no TypeScript errors

## End Time

1:00 am, Friday, September 4, 2026

# Day 3 Work Log

## Start Time

3:00 pm, Monday, September 7, 2026

## Starting Branch and Commit

- Branch: `feature/ai-content-plan`
- Starting commit: `9f32e44`

## Baseline Verification

The Day 2 project foundation was verified before starting the Day 3 AI implementation.

- Command: `php artisan test tests/Feature/ProjectTest.php`
- Result:

    - PASS `Tests\Feature\ProjectTest`
    - 9 tests passed
    - 49 assertions
    - 0 tests failed
    - Duration: 1.14s

## Tasks Completed

- Created the AI content plan specification in `docs/AI-CONTENT-PLAN-SPEC.md`.
- Added the `content_generations` database table.
- Added the `ContentGeneration` model, factory, and project relationship.
- Added typed `ContentPlan` and `ContentPlanResult` data objects.
- Added controlled `ContentGenerationException` handling.
- Added OpenAI configuration through `config/services.php`.
- Added `OPENAI_API_KEY` and `OPENAI_MODEL` to `.env.example`.
- Added the OpenAI Responses API integration.
- Added structured JSON schema requirements for generated content plans.
- Added server-side prompt construction using only the allowed project information.
- Added project ownership checks before making an AI request.
- Added validation for the returned structured content plan.
- Added successful and failed generation persistence.
- Added input and output token usage directly to `content_generations`.
- Added safe error handling without exposing provider responses or credentials.
- Added the content generation API route.
- Added the Vue interface for generating and displaying content plans.
- Added TypeScript types and response validation in the Vue page.
- Added loading and error states to prevent repeated generation requests.
- Added automated tests using fake HTTP responses so tests do not make real AI requests.
- Temporarily tested the generation flow with Gemini while OpenAI API credit was unavailable.
- Fixed a local PHP execution timeout encountered during a longer Gemini request.
- Tested the final implementation with OpenAI.
- Removed the temporary Gemini implementation so the final feature uses only OpenAI as required by the assignment.
- Updated the project documentation, AI log, and work log.

## AI Testing

The planned final implementation was OpenAI-only. While waiting for OpenAI API credit, I temporarily implemented Gemini support to test the generation flow.

Gemini was used to test the complete generation flow, including structured output, validation, persistence, token usage, and Vue rendering. A longer request initially hit the local PHP execution timeout, which I fixed by increasing the PHP execution time in Herd.

After OpenAI API credit became available, I tested the final implementation with OpenAI and then removed the Gemini-specific code, configuration, and tests so that the submitted implementation follows the assignment requirement of using a single provider.

## Problems and Solutions

- OpenAI API credit was not initially available, so Gemini was temporarily used to test the generation flow.
- A longer AI request exceeded the local PHP execution timeout. I increased the local PHP execution time and restarted Herd.
- After OpenAI credit became available, the final implementation was switched back to OpenAI and the temporary Gemini implementation was removed.

## Testing

### Focused AI Generation Tests

```text
php artisan test tests/Feature/ContentGenerationTest.php
```

PASS `Tests\Feature\ContentGenerationTest`

- ✓ it allows a user to generate a content plan for their own project
- ✓ it does not allow a user to generate a plan for another users project
- ✓ it rejects a project with missing required information
- ✓ it uses the configured model
- ✓ it stores the generated content and prompt
- ✓ it handles a provider failure safely
- ✓ it handles invalid provider output safely
- ✓ it handles an empty provider response safely
- ✓ it handles structurally invalid provider output safely
- ✓ it stores provider token usage
- ✓ it only includes allowed project information in the provider prompt
- ✓ it requires authentication to generate a content plan
- Tests: 12 passed (53 assertions)
- Duration: 1.25s

### TypeScript

```text
npm run type-check
```

- Passed with no TypeScript errors.

### CI Checks

```text
composer ci:check
```

- Passed.

### Full Test Suite

```text
php artisan test
```

- Tests: 45 passed (167 assertions)
- 3 tests skipped
- 0 tests failed
- Duration: 3.47s
- Passed with no failures.

## Manual Verification

One successful manual AI generation was performed using synthetic project data.

- **Model:** `gpt-4o-mini`
- **Generation:** Successful
- **Saved generation status:** `completed`
- **Structured sections:** Displayed correctly in the Vue interface, including the suggested title, content brief, outline, key points, production tasks, and risks or missing information.
- **Input tokens:** 275
- **Output tokens:** 315
- **Problem encountered:** No problem occurred during the successful OpenAI request.

No API key, authorization header, or raw provider response was recorded.

## What I Learned

I learned how to structure an AI feature so that the controller does not contain the provider-specific logic. The OpenAI service handles the prompt and API request, while the data object validates the structured result before it is stored.

I also learned why structured JSON output is more useful for an application than asking an AI provider to return unrestricted Markdown. The application can validate each required field and safely render the resulting data in Vue.

I learned how token usage can be stored directly with the generation that produced it, how provider failures should be converted into controlled application errors, and how HTTP fakes allow the AI integration to be tested without making real API requests.

## End Time

1:00 am, Tuesday, September 8, 2026

# Day 4 Work Log

## Start Time

10 am, Wednesday, September 9, 2026

## Starting Branch and Commit

- Branch: `feature/background-content-generation`
- Starting point: `main` at `4fad87e21a8a70f3c5ac60062c439f4d36e87243` when this branch was created.

## Baseline Verification

The repository was inspected from the Day 3 `main` implementation before changing the generation flow. The Day 3 controller was synchronously calling `OpenAIService`, and the Vue page expected a completed response directly from the POST request.

## Tasks Completed

- Created `docs/BACKGROUND-GENERATION-SPEC.md` describing the request, database queue, worker, polling, status lifecycle, duplicate prevention, failure policy, timeout coordination, and exactly-once limitation.

- Added `processing_started_at`, `completed_at`, and safe `error_message` fields to `content_generations`.

- Updated the `ContentGeneration` model casts and fillable fields for the new state data.

- Changed the generation controller so the web request validates ownership/input, saves a pending generation, and returns `202 Accepted` without calling OpenAI.

- Added transaction locking on the project row so concurrent requests cannot create two active generations for the same project.

- Added `GenerateContentPlan` as a database-queue job that receives only the generation ID.

- Added worker-side status claiming, terminal-state protection, structured-output persistence, safe failure handling, and one controlled retry for explicit provider rate limiting.

- Added `WithoutOverlapping` protection keyed by generation ID.

- Preserved the accepted prompt and model on the generation so queued work is not changed by later configuration changes.

- Configured the database queue to dispatch after transactions commit and documented a 90-second `retry_after` with a 75-second job timeout.

- Added the owner-only generation status endpoint.

- Added the latest generation relationship to projects so refresh can restore saved state.

- Updated the Vue interface to show `Waiting to start`, `Generating`, `Completed`, and `Failed` states and poll every two seconds only while work is active.

- Made loading state per project so another project's Generate button remains usable.

- Added polling cleanup on page leave and protection against overlapping status requests.

- Reworked feature tests to fake queue/provider behavior and execute the worker job without real OpenAI requests.

- Added regression tests for object-shaped collection values, including empty objects, verifying safe failure and failed-generation persistence.

- Added a regression test for a valid reasoning-first provider response, verifying successful completion and persistence of the generated plan.

- Added deterministic frontend request-guard tests covering repeated requests for the same project, independent projects, and guard reset after failure.

- Updated Vite+ test configuration so the frontend regression test runs through the project's existing tooling.

- Updated README with the separate web/worker startup commands and background-generation architecture.

## Queue Configuration

Database queue settings:

```text
QUEUE_CONNECTION=database

DB_QUEUE_RETRY_AFTER=90
```

Worker command:

```text
php artisan queue:work database --queue=default --tries=2 --timeout=75
```

OpenAI HTTP timeout: 60 seconds.

## Retry Policy

Only an explicit provider `429` rate-limit response is retryable, with one additional delayed attempt. Invalid output, missing configuration, ordinary provider errors, and ambiguous timeouts are not automatically retried.

## Verification

### Focused Day 4 Feature Tests

```text
php artisan test --filter=ContentGenerationTest
```

PASS Tests\Feature\ContentGenerationTest
✓ it creates a pending generation and queues work without calling OpenAI 0.62s  
✓ it executes the same generation in the worker and stores the validated result 0.08s  
✓ it rejects object-shaped collection fields and persists a safe failure 0.05s  
✓ it accepts a reasoning-first response and saves the completed plan 0.03s  
✓ it returns the existing active generation on repeated requests 0.03s  
✓ it rejects another users project before creating or queuing work 0.03s  
✓ it rejects incomplete project input without creating work 0.03s  
✓ it reads generation status only for the owning project 0.04s  
✓ it marks provider failures as terminal without exposing provider details 0.03s  
✓ it does not call OpenAI for a terminal generation 0.03s  
✓ it uses the saved model instead of current configuration 0.03s  
✓ it retries a rate limited generation once 0.03s  
✓ it marks a generation as failed when the provider times out 0.03s  
✓ it fails safely when OpenAI configuration is missing 0.04s  
✓ it does not retry a non retryable provider failure 0.03s  
✓ it does nothing when the generation has been deleted 0.02s  
✓ it does not call the provider for a completed generation 0.02s

Tests: 17 passed (80 assertions)

- All focused content-generation tests passed.
- Regression coverage includes object-shaped and empty-object collection rejection with safe failure persistence.
- Regression coverage includes a valid reasoning-first provider response with completed-plan persistence.
- Duplicate-generation and ownership protections passed.
- No real OpenAI requests were made.

### Frontend Regression Tests

```text
npx vp test
```

- 1 test file passed.
- 3 tests passed.
- 0 failures.
- Deterministic deferred-request tests verify repeated-click protection, cross-project behavior, and guard reset after completion/failure.

### TypeScript

```text
npm run type-check
```

- Passed with no TypeScript errors.

### Production Build

```text
npm run build
```

- Passed successfully.

### Full Project Quality Checks

```text
composer ci:check
```

- Formatting: passed.

- Lint: passed with no warnings or errors.

- TypeScript check: passed.

- Pint: passed.

- PHPStan: passed with no errors.

- Full Laravel test suite: 50 passed, 3 skipped, 194 assertions, 0 failures.

- The 3 skipped tests are existing Fortify two-factor-authentication tests because two-factor authentication is not enabled in the local configuration.

### Reviewer-Only Verification

- Boundary and deferred-fetch probes requested during review were executed successfully.
- The reviewer-only probes confirmed the intended request and deferred-response behavior.
- No live provider requests were made during automated verification.

## Review Regression Coverage

The Day 3 re-review requested committed regression tests for NCP-011, NCP-012, and NCP-013. These tests are now included in the repository and pass through the project's test tooling.

- NCP-011: object-shaped collection values, including empty objects, are rejected and persisted as safe failed generations.
- NCP-012: valid reasoning-first provider responses are accepted and persisted as completed plans.
- NCP-013: deterministic frontend request-guard coverage prevents repeated requests for the same project and preserves independent-project behavior while an active request is pending.

The frontend regression test uses deferred promises to deterministically verify request concurrency behavior without adding a browser-test dependency.

## Manual Verification

The background generation flow was manually verified with a separate Laravel queue worker.

- The web request immediately displayed `Waiting to start`.
- The generation transitioned to `Generating` while the worker processed the queued job.
- The generation transitioned to `Completed` after the worker finished.
- Closing the browser during generation did not stop the background job.
- Reopening the Projects page restored the saved generation state/result.
- Refreshing during generation preserved the server-side generation lifecycle and did not start a new generation.
- Duplicate generation was tested using multiple tabs and did not create a second active generation.
- An intentionally invalid provider configuration produced a terminal `Failed` state with a safe error message.
- The worker output confirmed that `GenerateContentPlan` ran independently of the browser.
- The worker-stopped/pending recovery scenario was not performed because Laravel Herd was running additional queue listener/worker processes in the development environment. After stopping the manually started `queue:work` process, other Laravel queue processes (`queue:listen` and `queue:work --once`) continued consuming the database queue. Therefore, a true state with no queue worker available could not be isolated reliably, so the test was not claimed as completed.

## Final Verification Notes

The automated tests use fake/synthetic provider behavior and do not make real OpenAI requests. The queue implementation is configured for the database driver, with the worker run separately from the web request.

The final implementation preserves the accepted prompt and model for queued work, prevents multiple active generations per project, and stores terminal results independently of the browser session.

The implementation documents the exactly-once limitation: a database transaction and a paid external API cannot guarantee exactly-once external execution across every possible failure boundary.

## End Time

9 pm, Wednesday, September 9, 2026

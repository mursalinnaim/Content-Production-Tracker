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

- Model: `gpt-4o-mini`
- Generation: Successful
- Saved generation status: `completed`
- Structured sections: Displayed correctly in the Vue interface, including the suggested title, content brief, outline, key points, production tasks, and risks or missing information.
- Input tokens: 275
- Output tokens: 315
- Problem encountered: No problem occurred during the successful OpenAI request.

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

- Fixed the first-request error state so provider failures are visible even when no generation object has been returned yet.

- Added frontend lifecycle protection so a late POST response cannot restart polling after the component has been unmounted.

- Made the generation request guard reactive so the Generate button correctly reflects an in-flight request.

- Added separate web and queue-worker development commands so starting the normal development environment does not automatically start a queue consumer.

- Added queue lifecycle regression coverage for queue failure handling, already-processing recovery, terminal generations, and unexpected worker exceptions.

## Queue Configuration

Database queue settings:

```text

QUEUE_CONNECTION=database

DB_QUEUE_RETRY_AFTER=90

```

Worker command:

```text

composer run dev:queue

```

OpenAI HTTP timeout: 60 seconds.

## Retry Policy

Only an explicit provider `429` rate-limit response is retryable, with one additional delayed attempt. Invalid output, missing configuration, ordinary provider errors, and ambiguous timeouts are not automatically retried.

## Verification

### Focused Day 4 Feature Tests

```text

php artisan test tests/Feature/ContentGenerationTest.php

```

PASS Tests\Feature\ContentGenerationTest

✓ it creates a pending generation and queues work without calling OpenAI

✓ it executes the same generation in the worker and stores the validated result

✓ it rejects object-shaped collection fields and persists a safe failure

✓ it accepts a reasoning-first response and saves the completed plan

✓ it returns the existing active generation on repeated requests

✓ it rejects another users project before creating or queuing work

✓ it rejects incomplete project input without creating work

✓ it reads generation status only for the owning project

✓ it marks provider failures as terminal without exposing provider details

✓ it does not call OpenAI for a terminal generation

✓ it uses the saved model instead of current configuration

✓ it retries a rate limited generation once and then completes successfully

✓ it fails after two consecutive rate limited attempts

✓ it marks a generation as failed when the provider times out

✓ it fails safely when OpenAI configuration is missing

✓ it does not retry a non retryable provider failure

✓ it does nothing when the generation has been deleted

✓ it does not call the provider for a completed generation

✓ it marks an active generation as failed when the queue job fails

✓ it fails safely when a generation is already processing

✓ it does not process a generation that is already failed

✓ it handles unexpected worker exceptions without exposing internal details

Tests: 22 passed (120 assertions)

### Frontend Regression Tests

```text

npm run test

```

✓ tests/frontend/generationRequestGuard.test.ts (3 tests) 9ms

✓ tests/frontend/ContentGeneration.test.ts (4 tests) 63ms

Test Files 2 passed (2)

Tests 7 passed (7)

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

- Full Laravel test suite: 3 skipped, 55 passed (234 assertions)

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

Additional Day 4 review regressions:

- NCP-015: first-request provider failures are rendered even when no generation object exists yet.

- NCP-016: a late POST response cannot restart polling after the component has been unmounted.

- NCP-017: the in-flight generation request state is reactive and correctly disables the relevant Generate button.

- NCP-018: web development startup and queue-worker startup are separated, and the README documents the independent worker lifecycle.

- NCP-019: queue lifecycle coverage now includes rate-limit retry/success, consecutive rate-limit failure, provider timeout/error handling, queue `failed()` handling, already-processing recovery, terminal duplicate delivery, and unexpected worker exceptions without exposing internal details.

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

- Stopped-worker recovery was verified: generation `5` remained pending with one queued job while the worker was stopped; after restarting the worker, generation `5` completed and the queued job count returned to zero.

- MySQL concurrent requests were tested with two near-simultaneous generation requests for the same project. Both requests returned the same generation ID, with one active generation and one queued job, confirming that concurrent requests did not create duplicate active generations.

## Final Verification Notes

The automated tests use fake/synthetic provider behavior and do not make real OpenAI requests. The queue implementation is configured for the database driver, with the worker run separately from the web request.

The final implementation preserves the accepted prompt and model for queued work, prevents multiple active generations per project, and stores terminal results independently of the browser session.

The implementation documents the exactly-once limitation: a database transaction and a paid external API cannot guarantee exactly-once external execution across every possible failure boundary.

For a generation left in a `processing` state after an interrupted worker, the current implementation fails the generation safely rather than blindly re-queuing it. This avoids silently issuing a second paid provider request when the outcome of the original external request is unknown. Such generations should be reviewed before any manual retry.

## End Time

9 pm, Wednesday, September 9, 2026

# Day 5 Work Log

## Date

9 pm, Monday, September 21, 2026

## Starting Branch and Commit

- Branch: `feature/content-plan-review`
- Starting commit: `4fad87e` (`main`)

## Baseline Verification

Starting commit: `4fad87e21a8a70f3c5ac60062c439f4d36e87243`

Commands run from the Day 5 starting commit:

- `npm run build` — PASS. Vite build completed successfully in 12.31s. The build reported an optional `fontaine` package warning, but completed successfully.
- `composer ci:check` — PASS. Formatting passed for 65 files, type checking passed, PHPStan reported no errors, and the Laravel test suite passed with 3 skipped and 45 passed (167 assertions).
- `npm run type-check` — PASS. `vue-tsc --noEmit` completed successfully.

## Task

Implement human review for completed AI content plans: edit and save drafts, accept a final snapshot, regenerate with instructions, preserve generation history, and keep the original AI response separate from user edits.

## Design Note

Created `docs/CONTENT-REVIEW-SPEC.md` before implementation. The design keeps the original provider response immutable, stores one editable draft per generation, creates a separate generation for regeneration, and stores one current accepted snapshot per project.

## Day 4 Follow-up

Day 4 PR #11 was still under review when Day 5 work began.
Day 5 was started from the Day 4/main baseline commit
`4fad87e21a8a70f3c5ac60062c439f4d36e87243`.

The Day 5 implementation was developed on the separate
`feature/content-plan-review` branch.

## Day 5 Implementation

- Added separate draft and accepted-plan storage while preserving original AI responses.
- Added accepted-plan migrations, relationships, source-generation references, and generation numbering.
- Added owner- and project-generation checks for history, detail, draft, acceptance, and regeneration actions.
- Added strict Laravel validation for all six editable content sections and nested outline items.
- Added idempotent acceptance with one current accepted snapshot per project.
- Added regeneration prompts using the saved draft or original response, persisted instructions, source generation, and configured model.
- Reused the existing database queue and active-generation protection for regeneration.
- Added version history with statuses, timestamps, draft markers, and accepted-source markers.
- Added Vue controls for Edit, Save draft, Cancel, Accept, Regenerate, version switching, discard confirmation, and safe failure messages.
- Preserved open edit forms during background completion and stopped polling on terminal status or unmount, including delayed POST responses.
- Added an explicit notice when regeneration instructions are not queued because another generation is already active.

## Problems and Fixes

- Review actions could resolve the wrong generation after selection changes. Review actions now use the rendered selected generation consistently.
- Several frontend tests selected the first button instead of the intended control. Tests now select controls by their rendered text.
- TypeScript exposed nullable accepted-plan rendering and weakly typed component fixtures. Both were corrected.
- PHPStan identified an unsafe regeneration content type. Regeneration now reads cast attributes safely and rejects completed generations without usable content.
- The neighboring component test suite was not included by Vitest and used an unsupported jsdom selector. Test discovery and selectors were corrected.
- Active regeneration originally reused work silently. The API now returns a reuse flag/message and the UI displays it.

## Verification

### Focused Day 5 Feature Tests

```text

php artisan test tests/Feature/ContentGenerationReviewTest.php

```

PASS Tests\Feature\ContentGenerationReviewTest
✓ it accepts the saved draft as an exact snapshot without calling OpenAI or the queue  
✓ it accepts the original response when no draft exists  
✓ it repeating acceptance of the same generation is a no-op  
✓ it repeated acceptance replaces the current selection without creating history rows  
✓ it rejects acceptance for another user and a wrong-project generation  
✓ it rejects acceptance of an incomplete generation  
✓ it loads the accepted plan only for its owning project  
✓ it rejects unauthenticated access to every review endpoint  
✓ it rejects another user and wrong-project generations for every review endpoint  
✓ it creates a new pending regeneration from saved draft instructions without provider or queue work in the request  
✓ it rejects blank regeneration instructions without creating work  
✓ it rejects regeneration for an ineligible or unauthorized generation  
✓ it returns the existing active generation instead of replacing its saved prompt  
✓ it rejects unknown draft fields and unknown outline fields  
✓ it keeps the original AI response unchanged when a draft is saved  
✓ it marks only the currently accepted generation as accepted in history
✓ it does not overwrite an accepted snapshot when a later generation is created
✓ it preserves the source draft and accepted snapshot when regeneration fails

Tests: 18 passed (123 assertions)

### Full Test Suite

```text
php artisan test
```

- Tests: 74 passed (363 assertions)
- 3 tests skipped
- 0 tests failed
- Duration: 5.81s
- Passed with no failures.

### Frontend Regression Tests

```text

npm run test

```

✓ tests/frontend/generationRequestGuard.test.ts (3 tests) 8ms  
✓ resources/js/pages/Projects/tests/ContentGeneration.test.ts (3 tests) 211ms  
✓ tests/frontend/ContentGeneration.test.ts (14 tests) 259ms

Test Files 3 passed (3)
Tests 20 passed (20)

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

- Full Laravel test suite: 3 skipped, 55 passed (234 assertions)

- The 3 skipped tests are existing Fortify two-factor-authentication tests because two-factor authentication is not enabled in the local configuration.

## What I Learned

I learned how to keep AI-generated content, user edits, and accepted content separate so that later edits or regenerations cannot silently change earlier work.

I also learned how background UI state introduces race conditions, especially when polling and delayed requests continue while the user switches versions or leaves the page.

Finally, I learned that acceptance should create an immutable snapshot, while regeneration should create a completely separate generation so the user can review and choose between versions without losing previous work.

## End Time

10 am, Tuesday, September 22, 2026

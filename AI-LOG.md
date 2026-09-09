# Day 1

## Entry 1

### Task

Set up the Laravel project and configure the local development environment.

### Prompt

I asked the AI how to set up the Laravel project using Laravel Herd, Vue, TypeScript, and MySQL, and how to troubleshoot the setup.

### Suggested Solution

The AI suggested creating the project with the Laravel Vue starter kit, using Laravel Herd for the PHP/Laravel environment, and connecting the project to the local MySQL server provided by XAMPP.

### My Verification

I ran the project, connected it to MySQL, successfully ran the database migrations, and verified that the authentication pages worked.

### My Changes

I created the Laravel project, configured the `.env` database settings, ran the migrations, and verified the initial application setup.

## Entry 2

### Task

Add the required Internship Progress section to the dashboard.

### Prompt

I asked the AI how to add the Internship Progress section using Laravel, Inertia, Vue, and TypeScript.

### Suggested Solution

The AI suggested creating a separate Vue component with typed TypeScript props and passing the Internship Progress data from the Laravel dashboard route through Inertia.

### My Verification

I logged in and confirmed that the Internship Progress section appeared on the dashboard and remained visible after refreshing the page.

### My Changes

I created the `InternshipProgress.vue` component and added the required Internship Progress data to the dashboard route.

## Entry 3

### Task

Replace the hardcoded student name with the authenticated user's name and verify the implementation.

### Prompt

I asked the AI how to use the authenticated user's existing data instead of hardcoding the student name, and how to test the required dashboard behavior.

### Suggested Solution

The AI suggested using the authenticated user already provided through Inertia shared props and passing the user's name to the Internship Progress component. It also suggested adding feature tests for guest access, authenticated dashboard access, and the Internship Progress data.

### My Verification

I logged in with the test account and confirmed that the correct user name appeared on the dashboard. I also ran `php artisan test` and `npm run type-check`. The applicable tests passed and the TypeScript check completed without errors.

### My Changes

I replaced the hardcoded student name with the authenticated user's name, added the dashboard feature test, and corrected an initial Inertia test assertion after it failed.

# Day 2

## Entry 4

### Task

Create the Project database model, migration, factory, and seed data.

### Prompt

I asked the AI how to implement the Project model and migration, define the User and Project relationships, and create realistic factory and seed data.

### Suggested Solution

The AI suggested creating a `Project` model with a migration, adding a foreign key from projects to users with cascade deletion, adding the required relationships and casts, and using a factory and seeder to generate development data.

### My Verification

I ran the migrations and seeded a fresh local database. I also ran the test suite and verified that the model relationships and cascade deletion worked.

### My Changes

I created the Project model and migration, updated the User model, created `ProjectFactory`, and updated `DatabaseSeeder` with one demo user and ten sample projects.

## Entry 5

### Task

Test Project listing, authentication, ownership, ordering, and empty state.

### Prompt

I asked the AI to create Pest feature tests covering the Project listing requirements from the assignment.

### Suggested Solution

The AI suggested tests for guest access, authenticated access, project ownership, newest-first ordering, and an empty project list.

### My Verification

I ran the focused Project tests and then the full Laravel test suite. The final result was 33 tests passed, 3 skipped, 0 failed, with 114 assertions.

### My Changes

I added the Project feature tests and verified that the full test suite passed.

## Entry 6

### Task

Review the Project page and make sure the empty state follows the Day 2 assignment.

### Prompt

I asked the AI to review the Project page against the Day 2 assignment and suggest any improvements that could make the page more useful.

### Suggested Solution

The AI suggested adding a **Create Project** button to the empty state so that users could create a project when they did not have any projects.

### My Decision

I rejected the suggestion to add a **Create Project** button.

The Day 2 assignment only required displaying the user's projects and providing an appropriate empty state. Project creation was not part of the assignment, so adding a creation flow would introduce extra functionality and increase the scope of the task.

Instead, I kept the empty state focused on explaining that the user currently has no projects.

### My Verification

I checked the empty state in the browser and ran the project quality checks after the change. The relevant tests, TypeScript checks, formatting checks, and other project checks passed.

# Day 3

## Entry 7 — Token Usage Storage

### Task: Decide how token usage should be stored for AI generations.

### Prompt:

I asked AI how to handle input and output token usage for each generated content plan.

### Suggested Solution:

AI suggested that token usage could be stored in a separate usage table related to each generation.

### My Decision:

I decided not to create a separate token usage table. Since input and output token counts belong directly to an individual content generation, I stored `input_tokens` and `output_tokens` directly in the `content_generations` table. This also matched the assignment requirements without adding unnecessary database complexity.

### Verification:

Updated the migration and model, then verified that token usage was correctly saved from the provider response and covered by automated tests.

### Why My Final Approach Was Safer or Clearer

AI suggested storing token usage in a separate table. After checking the assignment's required `content_generations` fields and testing the generation flow, I decided to store `input_tokens` and `output_tokens` directly on `content_generations`.

This is clearer because token usage belongs to a specific generation, so keeping it with that generation avoids an unnecessary extra relationship and database table. It also keeps the implementation closer to the assignment requirements and makes the generation record easier to inspect.

## Entry 8 — AI Generation Architecture

### Task: Implement the AI content generation flow and understand how the controller, service, validation, and database responsibilities should be separated.

### Prompt:

I asked AI how to structure the implementation so the controller could handle the request and ownership while the AI-specific API logic, structured response validation, and generation metadata remained separated.

### Suggested Solution:

AI suggested using a dedicated `OpenAIService` for building the prompt and communicating with the OpenAI API, a `ContentPlan` data object for validating the structured AI response, and a `ContentPlanResult` object for returning the validated plan together with the prompt, model, and token usage.

### My Decision:

I followed this structure because it made the responsibilities clearer instead of putting the entire AI implementation inside the controller.

During implementation, I also decided to temporarily test the same generation flow with both OpenAI and Gemini. The assignment ultimately required a single provider, but using Gemini temporarily allowed me to test the generation flow while my OpenAI API credit was not yet available.

### Verification:

Implemented the service and data objects, tested the structured response handling, persistence, and token usage, and verified the flow using fake provider responses in the automated tests.

## Entry 9 — Testing Gemini and OpenAI

### Task: Test the AI generation feature with real provider requests before finalizing the implementation.

### Prompt:

I asked AI for help implementing the AI generation flow and testing the provider integration while keeping the provider logic separate from the controller.

### Suggested Solution:

AI's main recommendation was to use OpenAI as the provider and keep the API integration behind a dedicated service.

### My Decision:

For development testing, I chose to temporarily support both Gemini and OpenAI instead of immediately using only OpenAI. I used Gemini while waiting for OpenAI API credit and used it to verify that the prompt, structured response, validation, database persistence, token usage, and Vue rendering were working correctly.

Once OpenAI credit became available, I tested the final flow with OpenAI as required by the assignment.

After confirming the implementation worked, I removed the Gemini provider, Gemini configuration, and Gemini-specific code/tests so the final implementation uses only OpenAI.

### Why my final approach is safer or clearer:

Using Gemini temporarily reduced the risk of consuming paid OpenAI credits while I was still debugging the generation flow. Once the flow was verified, removing Gemini made the final implementation clearer and closer to the assignment, which specifically requires a single AI provider. The final code therefore has one provider, one configuration path, and one service responsible for AI generation.

### Verification:

Verified the real generation flow with both providers during development, then removed Gemini and confirmed the final OpenAI-only implementation passed the automated tests, TypeScript check, and project CI checks.

## Entry 10 — Safe AI Failures

### Task: Handle provider failures and invalid AI responses without exposing sensitive information.

### Prompt:

I asked AI how the application should handle OpenAI failures, empty responses, invalid JSON, and structurally invalid content while keeping provider details and credentials out of the browser.

### Suggested Solution:

AI suggested using controlled application exceptions, storing a safe error code for failed generations, and returning a generic error message to the frontend instead of exposing the provider response or internal exception details.

### My Decision:

I followed this approach. Failed generations store a safe error code in `content_generations`, while the raw provider response is not stored or returned to the browser. The frontend displays a generic message when generation fails.

### Verification:

Added fake-provider tests for HTTP failures, empty responses, invalid JSON, and structurally invalid responses. Verified that the tests pass and that provider error details and API credentials are not exposed.

# Day 4

## Entry 11 — Background Job Architecture

### Task

Move the slow OpenAI content-plan generation out of the web request and into a Laravel database-queue job while preserving the Day 3 generation behavior.

### Prompt

I asked AI how to implement the Day 4 background-generation flow while meeting the requirements for a database queue, saved generation state, duplicate protection, worker retries, polling, and safe failures.

### Suggested Solution

AI recommended keeping the web request responsible for authentication, ownership, validation, and accepting the generation; saving the intended prompt and configured model on a `content_generations` record; dispatching a job containing only the generation ID; and having the worker load the generation and call the existing `OpenAIService`. The frontend should use the saved generation status and poll the status endpoint while the generation is active.

### My Decision

I followed this architecture. The queue driver remains `database`; Redis and Horizon were not added. The request creates a `pending` generation and returns `202 Accepted` without calling OpenAI. The job transitions the saved record to `processing`, calls the existing service, and persists either the validated result or a safe terminal failure.

I also kept the accepted prompt and model on the generation record so later configuration changes cannot alter already-accepted queued work. The job receives only the generation ID rather than serializing the user or project object.

### Verification

I ran the focused feature tests and confirmed that the request/worker separation is covered with fake queue/provider behavior and no real OpenAI requests.

## Entry 12 — Concurrency, Retry, and Recovery Decisions

### Task

Prevent duplicate active generations and duplicate provider calls while handling queue delivery, rate limits, and worker failures safely.

### Prompt

I asked AI how to protect the one-active-generation-per-project rule under concurrent requests and queue delivery, and how to implement the assignment's requirement for exactly one retryable provider case.

### Suggested Solution

AI recommended locking the project row inside a database transaction when checking for an active generation and creating a new one, dispatching only after the generation commit, and using a generation-ID-based overlap lock in the worker. For retries, only an explicit provider HTTP 429/rate-limit response should be retried once; invalid output, missing configuration, ordinary provider errors, and ambiguous timeout/read failures should become terminal failures.

### My Decision

I followed that policy. The worker is configured for two total attempts, with a delayed retry only for the explicit rate-limit exception. I did not add automatic retries for ambiguous timeouts because a paid external API request may have succeeded even if the response was not received, so blindly retrying could create a duplicate provider charge/work item.

The implementation also documents the exactly-once limitation: database state and a paid external API cannot provide a mathematical exactly-once guarantee across all failure boundaries.

### Verification

The queue configuration uses a 75-second worker timeout and a 90-second `retry_after`, while the OpenAI HTTP request timeout is 60 seconds. Automated tests cover terminal generations, provider failures, and saved-model behavior; all focused Day 4 tests passed.

## Entry 13 — Frontend Polling and Review Regression Tests

### Task

Restore generation state after refresh, show the required status lifecycle, and add the regression coverage requested during the Day 3 re-review.

### Prompt

I asked AI how to keep the Vue page deterministic while the generation runs in the background and how to add regression tests for the Day 3 review fixes without making real provider requests.

### Suggested Solution

AI suggested storing the latest generation state in the project data, polling the owner-only status endpoint about every two seconds while a generation is `pending` or `processing`, stopping on terminal states or navigation, and keeping loading/request state per project. For the regression tests, fake provider responses should cover object-shaped collection values and reasoning-first responses, while a deferred request guard can deterministically test repeated-click protection.

### My Decision

I kept the Day 4 UI loading state per project, so a project with an active generation is disabled while another project can still be started. I used the project's existing Vite+ test tooling rather than adding a separate test framework. The frontend regression test exercises the extracted request guard with deferred promises, which verifies the concurrency behavior without requiring browser E2E dependencies.

During implementation, a TypeScript narrowing error was found in the Vue template around the nullable generation response; I corrected the template accesses with safe optional chaining and reran the type check.

I also corrected the NCP-011 regression test so an empty JSON object is represented as `(object) []` rather than `[]`, because `[]` serializes to a JSON array and would not actually reproduce the reviewed invalid object-shaped input.

### Verification

The requested regression coverage is now committed in the repository. The focused Day 4 feature suite passed 11 tests with 68 assertions, and the frontend suite passed 3 tests. `npm run type-check` passed with no errors.

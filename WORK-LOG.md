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
- **Input tokens:** 280
- **Output tokens:** Returned by the provider and saved with the generation.
- **Problem encountered:** No problem occurred during the successful OpenAI request.

No API key, authorization header, or raw provider response was recorded.

## What I Learned

I learned how to structure an AI feature so that the controller does not contain the provider-specific logic. The OpenAI service handles the prompt and API request, while the data object validates the structured result before it is stored.

I also learned why structured JSON output is more useful for an application than asking an AI provider to return unrestricted Markdown. The application can validate each required field and safely render the resulting data in Vue.

I learned how token usage can be stored directly with the generation that produced it, how provider failures should be converted into controlled application errors, and how HTTP fakes allow the AI integration to be tested without making real API requests.

## End Time

1:00 am, Tuesday, September 8, 2026

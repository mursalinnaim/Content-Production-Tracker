# Content Production Tracker

A Laravel-based content production tracker created as part of a Software Development Internship assignment.

## Technology Stack

- **Backend:** Laravel
- **Frontend:** Vue 3 + TypeScript
- **Frontend integration:** Inertia.js
- **Database:** MySQL
- **Authentication:** Laravel starter kit authentication
- **Testing:** Pest
- **Build tooling:** Vite
- **Development environment:** Laravel Herd, XAMPP MySQL

## Local Installation

Requirements: PHP 8.4+, Composer, Node.js/npm, MySQL, and Git.

```bash
git clone <repository-url>
cd content-production-tracker
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

On Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Configure MySQL in `.env` when needed:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=content_production_tracker
DB_USERNAME=root
DB_PASSWORD=
```

## Projects

Authenticated users can access `/projects` and see only projects they own. Projects contain a title, content type, status, due date, brief, notes, and timestamps.

The user/project relationship is one-to-many: one user can own many projects, while each project belongs to exactly one user.

## AI Content Plan

Authenticated project owners can request a structured content plan using only the project's `title`, `content_type`, `brief`, and `notes`. The OpenAI API key remains server-side. Output is validated before it is stored in `content_generations`.

### Background Generation

Day 4 moves the slow provider call out of the web request:

```text
Vue
 ↓
POST /projects/{project}/generations
 ↓
ownership + project validation
 ↓
create/reuse pending generation
 ↓
database queue
 ↓
202 Accepted

separate queue worker
 ↓
pending → processing
 ↓
existing OpenAIService
 ↓
validate structured result
 ↓
processing → completed / failed
 ↓
Vue polls saved status
```

The web request never calls OpenAI. It stores the accepted prompt and configured model before queueing so later configuration changes do not change a queued request.

A project can have many generations over time, but only one `pending` or `processing` generation may exist at a time. Repeated clicks return the existing active generation instead of creating another job.

The status values are:

- `pending` — Waiting to start
- `processing` — Generating
- `completed` — Completed
- `failed` — Failed

The owner-only status endpoint is:

```text
GET /projects/{project}/generations/{generation}
```

It never calls OpenAI.

### Queue Configuration

The application uses Laravel's database queue. Redis and Horizon are intentionally not required for this assignment. The queue connection uses `after_commit=true`, a 90-second `retry_after`, and a 60-second OpenAI HTTP timeout. The job timeout is 75 seconds.

Start the web application normally:

```bash
composer run dev
```

Start the background worker in a separate terminal:

```bash
composer run dev:queue
```

If the worker is not running, generation remains in `pending` until a worker claims the database queue job.

The exact background-generation design, status lifecycle, duplicate protection, retry policy, and exactly-once limitation are documented in:

```text
docs/BACKGROUND-GENERATION-SPEC.md
```

### Safe Manual Recovery

If a generation remains in `processing` after a worker interruption, do not manually re-queue it immediately. The original provider request may have already been accepted, so retrying it could create a duplicate paid request.

1. Check the generation status and confirm whether it is `pending`, `processing`, `completed`, or `failed`.
2. If the generation is `pending` and its queue job is still present, start the queue worker and allow the existing job to continue.
3. If the generation is `processing`, do **not** create another queue job for the same generation. Leave it for manual review because the outcome of the original provider request may be unknown.
4. If the provider outcome is confirmed to have failed before the request was accepted, the generation can be reviewed for a controlled retry.
5. Do not blindly reset a `processing` generation to `pending` and re-queue it, as this can result in duplicate provider requests and duplicate usage charges.
6. Once the generation is confirmed `completed` or `failed`, no additional job should be created for that generation.

### Retry and Failure Policy

Only an explicit OpenAI `429` rate-limit response receives one additional delayed attempt. Invalid output, missing configuration, ordinary provider errors, and ambiguous timeouts are not automatically retried because a repeated external request can create another paid request.

Failures are stored with a safe error code/message. Raw provider responses, API keys, and authorization headers are not stored.

There is no exactly-once guarantee across a database transaction and a paid external API. A worker could die after the provider accepts a request but before the database result is saved; the implementation does not silently issue another paid request in that ambiguous case.

### Environment Configuration

Add the OpenAI configuration to local `.env` only:

```env
OPENAI_API_KEY=your-api-key
OPENAI_MODEL=gpt-4o-mini
```

Never commit the API key or put it in Vue code, frontend environment variables, screenshots, prompts, or logs.

### Generation Storage

Each generation records the project, status, accepted prompt, configured model, structured response, token usage, safe error information, processing timestamp, completion timestamp, and normal Eloquent timestamps. Existing Day 3 completed/failed records remain valid.

## Content Plan Review

After a generation reaches `completed`, the project owner can review that specific version from the generation history.

The Day 5 workflow is:

```text
Completed generation
        ↓
Open generation history
        ↓
Select a version
        ↓
Edit → Save draft (stored separately from the original response)
        ↓
Accept → immutable accepted snapshot
        ↓
Regenerate with instructions → separate queued generation
        ↓
Select and review the new generation
```

Historical versions are loaded independently. While a selected version is loading or has failed to load, review actions remain unavailable rather than falling back to another generation. Accepted history entries identify their source generation.

The frontend regression suite includes transition/race-condition coverage for delayed historical detail responses, out-of-order version responses, reused active regeneration work, and accepted-source rendering:

```bash
npm run test
```

If regeneration finds an existing pending/processing generation, the request is not submitted again. The entered instructions remain in the form and a notice explains that they were not queued.

The content plan uses the same six application fields throughout generation and editing. The allowed project fields used to construct the AI prompt are `title`, `content_type`, `brief`, and `notes`; selecting those fields does not guarantee that private information a user writes inside them is absent from the prompt.

## Testing

Automated tests use faked OpenAI responses and do not consume API credits. The focused generation tests cover request/queue separation, ownership, incomplete projects, duplicate active requests, status authorization, worker success, provider failure, terminal-generation protection, and saved-model behavior.

```bash
php artisan test tests/Feature/ContentGenerationTest.php
php artisan test
npm run type-check
npm run build
composer ci:check
```

## Final Verification (Day 6 Review Fixes)

The final post-fix local verification was run on the updated branch.

### Backend

```bash
php artisan test
```

- **76 tests passed**
- **3 tests skipped**
- **377 assertions**
- 0 tests failed

The skipped tests are the existing Fortify two-factor-authentication tests because two-factor authentication is not enabled in the local configuration.

### Frontend

```bash
npm run test
```

- **3 test files passed**
- **24 tests passed**

The frontend suite includes regression coverage for historical generation selection/loading, out-of-order responses, reused active regeneration work, and accepted-source rendering.

### Additional Quality Checks

```bash
composer ci:check
npm run type-check
npm run build
```

These checks passed during the final verification run.

## Final Verification (Day 6 Review Fixes)

The final post-fix local verification was run on the updated branch.

- `php artisan test` — **76 passed, 3 skipped, 377 assertions**
- `npm run test` — **24 frontend tests passed across 3 test files**
- `npm run type-check` — passed
- `npm run build` — passed
- `composer ci:check` — passed

The skipped Laravel tests are the existing Fortify two-factor-authentication tests because two-factor authentication is not enabled in the local configuration.

## Security

- Only authenticated users can generate content plans.
- Users can only generate plans for their own projects.
- Ownership is checked before any provider call.
- API credentials remain server-side.
- Only the documented project fields are selected for prompts; users remain responsible for not entering private information into those fields.
- Provider details are not exposed to users.
- AI output is validated before storage.
- Automated tests do not make real provider requests.

## Development-only Demo Account

A demo account is included for local development and testing:

- **Name:** Intern Test
- **Email:** `intern@example.test`
- **Password:** `password`

These credentials are for development only.

## Running the Application

```bash
composer run dev
```

Or run the backend and frontend separately:

```bash
php artisan serve
npm run dev
```

For Day 4 AI generation, also run the database queue worker in another terminal using the command documented above.

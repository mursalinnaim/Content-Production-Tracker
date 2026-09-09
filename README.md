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
php artisan queue:work database --queue=default --tries=2 --timeout=75
```

If the worker is not running, generation remains in `pending` until a worker claims the database queue job.

The exact background-generation design, status lifecycle, duplicate protection, retry policy, and exactly-once limitation are documented in:

```text
docs/BACKGROUND-GENERATION-SPEC.md
```

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

## Testing

Automated tests use faked OpenAI responses and do not consume API credits. The focused generation tests cover request/queue separation, ownership, incomplete projects, duplicate active requests, status authorization, worker success, provider failure, terminal-generation protection, and saved-model behavior.

```bash
php artisan test tests/Feature/ContentGenerationTest.php
php artisan test
npm run type-check
npm run build
composer ci:check
```

## Security

- Only authenticated users can generate content plans.
- Users can only generate plans for their own projects.
- Ownership is checked before any provider call.
- API credentials remain server-side.
- Credentials and unrelated user data are excluded from prompts.
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

# AI Content Plan Specification

## 1. Overview

The Content Production Tracker provides an AI-powered feature that generates a structured content plan for a project.

The feature takes information already stored in the project and sends it to the OpenAI API through the Laravel backend. The generated content plan is validated, stored, and displayed to the authenticated user.

The AI feature is intended to help users turn a project brief into a practical content production plan.

---

## 2. Goals

The feature should:

- Generate a useful content plan from an existing project.
- Use only relevant project information as input.
- Keep the OpenAI API key on the Laravel backend.
- Return a predictable structured response.
- Validate the AI response before storing it.
- Store generated plans so they can be viewed later.
- Ensure users can only generate plans for projects they own.
- Record successful and failed generation attempts safely.
- Record provider token usage when available.
- Provide clear error handling when generation fails.

---

## 3. User Flow

The expected user flow is:

1. The user logs into the application.
2. The user views their projects.
3. The user selects **Generate Content Plan** for one of their projects.
4. Laravel verifies that the authenticated user owns the project.
5. Laravel checks that the project contains the required information.
6. Laravel builds the AI prompt.
7. Laravel sends the request to OpenAI.
8. OpenAI returns a structured content plan.
9. Laravel validates the response.
10. Laravel stores the generation.
11. The frontend displays the generated content plan to the user.

The OpenAI API must never be called directly from the Vue frontend.

---

## 4. Input Data

The AI prompt may use the following fields from the project:

- `title`
- `content_type`
- `brief`
- `notes` (if available)

No unrelated user or system data should be included in the AI prompt.

The prompt must not include:

- User email
- User password
- User ID
- Session information
- API keys
- Authentication headers
- Server configuration
- Other unrelated project or application data

### Example Input

```text
Title: Summer Product Campaign

Content Type: Social Media Campaign

Brief:

Create a campaign promoting the company's new summer product line.

Notes:

Focus on short-form video and Instagram content.
```

---

## 5. AI Output

The AI must return a structured JSON object with the following fields:

```json
{
    "suggested_title": "string",
    "content_brief": "string",
    "outline": [
        {
            "heading": "string",
            "purpose": "string"
        }
    ],
    "key_points": ["string"],
    "production_tasks": ["string"],
    "risks_or_missing_information": ["string"]
}
```

### Field Requirements

#### `suggested_title`

A suitable title for the content based on the project information.

Type:

```text
string
```

#### `content_brief`

A concise description of the recommended content direction.

Type:

```text
string
```

#### `outline`

A list of major sections or content elements.

Each item must contain:

- `heading`
- `purpose`

Type:

```text
array<object>
```

#### `key_points`

Important ideas or messages that the final content should communicate.

Type:

```text
array<string>
```

#### `production_tasks`

Practical tasks required to produce the content.

Examples:

- Research topic
- Prepare script
- Record footage
- Edit video
- Create thumbnail
- Review final content

Type:

```text
array<string>
```

#### `risks_or_missing_information`

Information that may be missing or potential problems that could affect production.

Type:

```text
array<string>
```

---

## 6. Prompt Requirements

The Laravel backend constructs the prompt using the project's:

- Title
- Content type
- Brief
- Notes

The prompt should instruct the AI to:

- Generate a practical content plan.
- Base the plan only on the supplied project information.
- Avoid inventing specific facts about the project.
- Clearly identify missing information.
- Return the required structured JSON format.
- Follow the required output schema.

The OpenAI API key and other server configuration must never be included in the prompt.

The prompt is stored with the generation so the input used to produce a result can be reviewed later. It must not contain secrets or unrelated private information.

---

## 7. API Architecture

The feature uses the following architecture:

```text
Vue Frontend
     |
     | POST /projects/{project}/generations
     v
Laravel Controller
     |
     v
Project Ownership Check
     |
     v
Required Project Data Check
     |
     v
OpenAI Service
     |
     v
OpenAI Responses API
     |
     v
Structured JSON Response
     |
     v
Response Validation
     |
     v
ContentGeneration Model
     |
     v
Database
     |
     v
Vue Frontend
```

The frontend is responsible only for requesting generation and displaying the result.

The Laravel backend is responsible for:

- Authentication
- Authorization
- Project validation
- Prompt construction
- OpenAI communication
- Response validation
- Database storage
- Token usage extraction
- Error handling

The OpenAI integration is isolated in the `OpenAIService` rather than being implemented directly inside the controller.

---

## 8. Authentication and Authorization

Only authenticated users may generate content plans.

Before generating a plan, Laravel must verify that the requested project belongs to the authenticated user.

A user must not be able to generate or access AI generations belonging to another user's project by modifying the project ID in the request.

Project ownership is checked before making any OpenAI request.

Unauthorized requests should return an appropriate HTTP error response.

---

## 9. OpenAI Configuration

The OpenAI API key must be stored as an environment variable.

Example:

```env
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o-mini
```

The API key must not be:

- Hard-coded in source code.
- Stored in Vue files.
- Exposed to the browser.
- Committed to Git.
- Included in API responses.
- Included in prompts.
- Written to application logs.

Laravel reads these values through `config/services.php`.

The OpenAI model is configurable and must not be hard-coded in the service request.

The OpenAI request uses the Responses API with structured JSON schema output.

A reasonable request timeout is configured and automatic retries are not used.

---

## 10. Generation Storage

Each generation attempt is stored in the `content_generations` database table.

A generation is associated with the project that was used to create it.

The current database structure contains:

| Field           | Purpose                                                   |
| --------------- | --------------------------------------------------------- |
| `id`            | Generation identifier                                     |
| `project_id`    | Associated project                                        |
| `status`        | Generation status, such as `completed` or `failed`        |
| `prompt`        | Exact prompt sent to OpenAI                               |
| `response`      | Validated structured content plan                         |
| `model`         | OpenAI model used                                         |
| `input_tokens`  | Input token usage when provided                           |
| `output_tokens` | Output token usage when provided                          |
| `error_code`    | Safe internal error classification for failed generations |
| `created_at`    | Creation timestamp                                        |
| `updated_at`    | Last update timestamp                                     |

Successful generations store the validated content plan in the `response` JSON column.

Failed generations store a safe error code instead of the raw provider response.

Provider response bodies, API keys, authentication headers, and other sensitive information must not be stored.

No separate token-usage table is required for this version. Token usage is stored directly on `content_generations`.

---

## 11. Validation

The Laravel backend must validate the AI response before storing it as a successful generation.

The response must contain:

```text
suggested_title
content_brief
outline
key_points
production_tasks
risks_or_missing_information
```

The following types must be enforced:

```text
suggested_title                  → string
content_brief                    → string
outline                          → array
outline.*.heading                → string
outline.*.purpose                → string
key_points                       → array of strings
production_tasks                 → array of strings
risks_or_missing_information     → array of strings
```

The structured response is converted into the `ContentPlan` data object.

Invalid or malformed AI responses must not be saved as successful generations.

The implementation uses both OpenAI's structured output schema and application-level validation so that the data is validated before persistence.

---

## 12. Error Handling

The system should handle common failure cases gracefully.

Possible failures include:

- User is not authenticated.
- User does not own the requested project.
- Project does not exist.
- Project is missing required information.
- OpenAI API request fails.
- OpenAI API times out.
- OpenAI returns invalid JSON.
- OpenAI returns an empty response.
- AI response does not match the required structure.
- API key is missing or incorrectly configured.
- An unexpected generation error occurs.

Expected generation failures are converted into controlled application errors.

Failed generations are stored with a safe status and error code.

The user receives a generic message such as:

```text
The content plan could not be generated. Please try again later.
```

The user-facing response must never expose:

- OpenAI provider response bodies
- API keys
- Authentication headers
- Stack traces
- Internal exception details
- Server configuration

---

## 13. Frontend Requirements

The Vue frontend provides a way for the user to start generation for each displayed project.

The UI should:

1. Show a **Generate Content Plan** action.
2. Show a loading state while generation is in progress.
3. Disable the generation action while the request is running.
4. Prevent repeated generation clicks during an active request.
5. Display a safe error message if generation fails.
6. Display the generated content plan when successful.

The generated plan should clearly display:

- Suggested title
- Content brief
- Outline
- Key points
- Production tasks
- Risks or missing information

The frontend uses TypeScript types for the project and generated content structures.

The frontend must not contain any OpenAI credentials.

AI output is displayed as text and must not be rendered as unsafe HTML or raw HTML from the provider.

---

## 14. Security Requirements

The implementation must follow these security requirements:

- OpenAI requests must be made server-side.
- API keys must remain server-side.
- Project ownership must be checked before generation.
- Ownership failures must occur before an OpenAI request is made.
- User input must be handled safely.
- AI output must be validated before storage.
- Sensitive API errors must not be exposed to users.
- Raw provider error bodies must not be stored.
- Users must not be able to generate content for another user's project.
- Users must not be able to access another user's generated content through project ID manipulation.
- Secrets must not be included in prompts, responses, logs, or committed source code.

---

## 15. Acceptance Criteria

The feature is considered complete when:

- [ ] An authenticated user can request an AI content plan for their own project.
- [ ] An unauthenticated user cannot generate a plan.
- [ ] A user cannot generate a plan for another user's project.
- [ ] Ownership is checked before the provider request.
- [ ] A project missing required information is rejected before the provider request.
- [ ] Laravel sends the project information to OpenAI.
- [ ] Only the required project fields are included in the prompt.
- [ ] Unrelated user data and secrets are excluded from the prompt.
- [ ] The OpenAI API key remains server-side.
- [ ] The OpenAI model is configurable.
- [ ] The AI response follows the defined JSON structure.
- [ ] Laravel validates the AI response.
- [ ] Valid generations are stored in the database.
- [ ] Failed generations are stored safely.
- [ ] Token usage is stored when provided by OpenAI.
- [ ] The generated plan can be displayed in Vue.
- [ ] Loading and error states are handled.
- [ ] Invalid AI responses are not stored as successful generations.
- [ ] Automated tests use a fake HTTP provider and do not make real OpenAI requests.

---

## 16. Example Generated Response

For a project about a summer product campaign, an acceptable response could look like:

```json
{
    "suggested_title": "Summer Product Campaign",
    "content_brief": "Create a short-form social media campaign that introduces the summer product line and highlights its main benefits.",
    "outline": [
        {
            "heading": "Introduction",
            "purpose": "Introduce the summer product line and establish the main campaign message."
        },
        {
            "heading": "Product Highlights",
            "purpose": "Present the most important features and benefits of the products."
        },
        {
            "heading": "Call to Action",
            "purpose": "Encourage viewers to learn more or take the desired next step."
        }
    ],
    "key_points": [
        "Highlight the new summer product line.",
        "Focus on the main product benefits.",
        "Use concise and engaging social media content."
    ],
    "production_tasks": [
        "Research product information",
        "Write short-form scripts",
        "Record or collect visual assets",
        "Edit the final content",
        "Review the completed content"
    ],
    "risks_or_missing_information": [
        "Target audience is not specified.",
        "Campaign duration is not specified.",
        "Specific product features were not provided."
    ]
}
```

---

## 17. Out of Scope

The first version of this feature will not include:

- Automatic publishing to social media.
- Automatic video generation.
- Image generation.
- Automatic content scheduling.
- AI-generated content revisions.
- Multiple AI providers.
- Streaming AI responses.
- Complex AI conversation history.
- Multiple AI provider fallback or routing.

These features may be considered in future versions.

---

## 18. Implementation Principle

The implementation should remain simple and consistent with the existing application architecture.

The AI feature is separated into clear responsibilities:

```text
Controller
    ↓
OpenAI Service
    ↓
Response Validation
    ↓
ContentGeneration Model
    ↓
Database
```

The `ContentPlan` data object is responsible for representing and validating the required structured content plan.

The `ContentPlanResult` data object carries the validated content plan together with generation metadata such as the prompt, model, and token usage.

The goal is to add the AI functionality without unnecessarily complicating the existing Content Production Tracker.

The first version intentionally uses a single AI provider, OpenAI, and does not introduce provider abstraction or multi-provider support.

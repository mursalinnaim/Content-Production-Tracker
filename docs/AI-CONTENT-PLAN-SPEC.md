# AI Content Plan Specification

## 1. Overview

The Content Production Tracker will provide an AI-powered feature that generates a structured content plan for a project.

The feature will take information already stored in the project and send it to the OpenAI API through the Laravel backend. The generated content plan will then be validated, stored, and displayed to the authenticated user.

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
- Provide clear error handling when generation fails.

---

## 3. User Flow

The expected user flow is:

1. The user logs into the application.
2. The user opens one of their projects.
3. The user selects **Generate AI Content Plan**.
4. Laravel verifies that the authenticated user owns the project.
5. Laravel collects the required project information.
6. Laravel builds the AI prompt.
7. Laravel sends the request to OpenAI.
8. OpenAI returns a structured content plan.
9. Laravel validates the response.
10. Laravel stores the generated plan.
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

The Laravel backend will construct the prompt using the project's:

- Title
- Content type
- Brief
- Notes

The prompt should instruct the AI to:

- Generate a practical content plan.
- Base the plan only on the supplied project information.
- Avoid inventing specific facts about the project.
- Clearly identify missing information.
- Return only valid JSON.
- Follow the required output structure.

The prompt should not expose the OpenAI API key or any server configuration.

---

## 7. API Architecture

The feature will use the following architecture:

```text
Vue Frontend
     |
     | POST /projects/{project}/generations
     ↓
Laravel Controller
     |
     ↓
Project Ownership Check
     |
     ↓
OpenAI Service
     |
     ↓
OpenAI API
     |
     ↓
Structured JSON Response
     |
     ↓
Response Validation
     |
     ↓
ContentGeneration Model
     |
     ↓
Database
     |
     ↓
Vue Frontend
```

The frontend is responsible only for requesting generation and displaying the result.

The Laravel backend is responsible for:

- Authentication
- Authorization
- Prompt construction
- OpenAI communication
- Response validation
- Database storage
- Error handling

---

## 8. Authentication and Authorization

Only authenticated users may generate content plans.

Before generating a plan, Laravel must verify that the requested project belongs to the authenticated user.

A user must not be able to generate or access AI generations belonging to another user's project by modifying the project ID in the request.

Unauthorized requests should return an appropriate HTTP error response.

---

## 9. OpenAI Configuration

The OpenAI API key must be stored as an environment variable.

Example:

```env
OPENAI_API_KEY=
```

The API key must not be:

- Hard-coded in source code.
- Stored in Vue files.
- Exposed to the browser.
- Committed to Git.
- Included in API responses.

Laravel configuration should read the API key from the environment.

---

## 10. Generation Storage

Each successful AI generation should be stored in the database.

A generation should be associated with the project that was used to create it.

The stored generation should contain enough information to retrieve and display the generated plan later.

At minimum, the stored data should include:

- Project ID
- Generated content
- Creation timestamp

The exact database structure may be determined during implementation.

---

## 11. Validation

The Laravel backend must validate the AI response before storing it.

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
suggested_title          → string
content_brief            → string
outline                  → array
outline.*.heading        → string
outline.*.purpose        → string
key_points               → array of strings
production_tasks         → array of strings
risks_or_missing_information → array of strings
```

Invalid or malformed AI responses must not be saved as successful generations.

---

## 12. Error Handling

The system should handle common failure cases gracefully.

Possible failures include:

- User is not authenticated.
- User does not own the requested project.
- Project does not exist.
- OpenAI API request fails.
- OpenAI API times out.
- OpenAI returns invalid JSON.
- AI response does not match the required structure.
- API key is missing or incorrectly configured.

The user should receive a clear error message without exposing sensitive server or API information.

---

## 13. Frontend Requirements

The Vue frontend should provide a way for the user to start generation.

The UI should:

1. Show a **Generate AI Content Plan** action.
2. Show a loading state while generation is in progress.
3. Display an error message if generation fails.
4. Display the generated content plan when successful.

The generated plan should clearly display:

- Suggested title
- Content brief
- Outline
- Key points
- Production tasks
- Risks or missing information

The frontend should not contain any OpenAI credentials.

---

## 14. Security Requirements

The implementation must follow these security requirements:

- OpenAI requests must be made server-side.
- API keys must remain server-side.
- Project ownership must be checked before generation.
- User input must be handled safely.
- AI output must be validated before storage.
- Sensitive API errors must not be exposed to users.
- Users must not be able to access another user's generated content.

---

## 15. Acceptance Criteria

The feature is considered complete when:

- [ ] An authenticated user can request an AI content plan for their own project.
- [ ] An unauthenticated user cannot generate a plan.
- [ ] A user cannot generate a plan for another user's project.
- [ ] Laravel sends the project information to OpenAI.
- [ ] Only the required project fields are included in the prompt.
- [ ] The OpenAI API key remains server-side.
- [ ] The AI response follows the defined JSON structure.
- [ ] Laravel validates the AI response.
- [ ] Valid generations are stored in the database.
- [ ] The generated plan can be displayed in Vue.
- [ ] Loading and error states are handled.
- [ ] Invalid AI responses are not stored as successful generations.

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

These features may be considered in future versions.

---

## 18. Implementation Principle

The implementation should remain simple and consistent with the existing application architecture.

The AI feature should be separated into clear responsibilities:

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

The goal is to add the AI functionality without unnecessarily complicating the existing Content Production Tracker.

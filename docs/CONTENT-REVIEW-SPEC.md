# Content Plan Review Specification

## Purpose

Day 5 adds human review to the existing background content-generation workflow.

A completed AI generation is treated as a draft that the user can review, edit, regenerate, and eventually accept. Original AI responses must remain unchanged, earlier generations must remain available, and an accepted plan must remain unchanged unless the user explicitly accepts another version.

This feature does not replace the existing generation statuses (`pending`, `processing`, `completed`, and `failed`). Generation status describes background AI work, while draft and acceptance describe the user's review state.

## 1. Content Storage

Each completed generation keeps its original validated AI response unchanged.

For each generation:

- **Original AI response:** the validated content returned by the provider. It is immutable after generation completes.
- **Editable draft:** the user's saved edits for that generation. It is stored separately from the original response and may be updated without changing the original response.
- **Regeneration instructions:** the user's instructions used to request another version. They are stored with the new generation for history and explanation.
- **Provider prompt and model:** the intended prompt and configured model used for the generation remain stored with that generation.
- **Accepted plan:** a separate saved snapshot containing the exact content accepted by the user, together with its source generation and acceptance time.

The existing structured content schema remains unchanged:

- `suggested_title`: string
- `content_brief`: string
- `outline`: list of objects containing `heading` and `purpose`
- `key_points`: list of strings
- `production_tasks`: list of strings
- `risks_or_missing_information`: list of strings

The application will not accept arbitrary JSON or HTML for editable content.

## 2. Generation Relationships

A regenerated plan is a new `ContentGeneration` record.

The source generation is never overwritten.

When regeneration creates a new generation, the new generation stores a reference to the completed generation from which it was created.

Conceptually:

```text
Generation #1
├── original AI response
├── editable draft
└── regeneration history
         │
         ▼
Generation #2
├── source generation → #1
├── regeneration instructions
├── original AI response
└── editable draft
```

Further regeneration creates another separate generation:

```text
Generation #1
       │
       ▼
Generation #2
       │
       ▼
Generation #3
```

Earlier generations, their original responses, and their saved drafts remain available.

A regenerated generation uses the selected generation's saved draft when one exists. Otherwise, it uses that generation's original response as the source content for the new prompt.

Regeneration reuses the existing Day 4 queue and provider workflow. It does not introduce a second provider service or parallel queue system.

## 3. Ownership and Status Rules

Every Day 5 action must verify:

1. The authenticated user owns the project.
2. The requested generation belongs to that project.

The second check is required even when the authenticated user owns both projects.

### Review actions

Reading history, saving a draft, accepting a generation, and regenerating a generation require an authenticated owner of the project.

The selected generation must belong to that project.

Edit, accept, and regenerate actions are allowed only for generations with:

```text
status = completed
```

Pending, processing, and failed generations cannot be edited or accepted.

Regeneration may create a new pending generation, but the source generation itself must already be completed.

The server must not trust project IDs, generation IDs, or accepted-version references supplied by the browser.

## 4. Draft Editing

The user can select a completed generation and enter edit mode.

The user can edit all six existing content sections:

- suggested title
- content brief
- outline
- key points
- production tasks
- risks/missing information

Outline entries remain objects with `heading` and `purpose`.

The other collection fields remain lists of strings.

Users can add and remove list entries with simple controls.

Laravel validates the complete submitted structure, including nested outline data. Invalid structures are rejected with useful field errors.

Saving a draft:

- updates only the editable draft;
- does not modify the original AI response;
- does not modify the provider prompt;
- does not modify the model;
- does not modify token usage;
- does not call OpenAI;
- does not enqueue a generation job.

Canceling edit mode discards only unsaved form changes. A previously saved draft remains available after refresh.

The user must save or cancel unsaved edits before accepting or regenerating the selected generation.

## 5. Acceptance

An accepted plan is a separate snapshot of the content selected by the user.

When accepting a completed generation:

1. If a saved draft exists, the draft is accepted.
2. Otherwise, the original AI response is accepted.
3. The exact accepted content is stored as a snapshot.
4. The snapshot records its source generation.
5. The snapshot records the acceptance time.

There is one current accepted plan per project.

Accepting another completed generation replaces the project's current accepted selection, but does not modify or delete any generation history.

Repeatedly accepting the same unchanged content must not create duplicate records or additional side effects.

Later edits to a generation's draft must not modify the accepted snapshot.

Later regeneration must not modify the accepted snapshot.

The accepted snapshot changes only when the user explicitly accepts another version.

Acceptance does not call OpenAI, enqueue generation work, or create production checklist tasks.

## 6. Regeneration

Regeneration is an explicit request for another AI version. It is separate from automatic retry behavior for failed provider requests.

The user must provide non-empty regeneration instructions. Blank or whitespace-only instructions are rejected by Laravel.

A valid regeneration request:

1. Verifies project ownership and generation/project matching.
2. Verifies that the source generation is completed.
3. Verifies that there are no unsaved edits requiring Save or Cancel.
4. Uses the selected generation's saved draft when available, otherwise its original AI response.
5. Includes the new user instructions in the intended prompt.
6. Stores the source generation reference.
7. Stores the regeneration instructions.
8. Captures the configured model at request time.
9. Creates a new generation record.
10. Dispatches the existing background generation job.

The web request must return without calling OpenAI directly.

The queued job uses the saved generation inputs rather than rebuilding them from later application configuration.

A failed regeneration must not modify or destroy:

- the source generation;
- its original AI response;
- its saved draft;
- the current accepted plan.

Only one generation may be pending or processing for a project at a time.

Concurrent regeneration requests must use the existing Day 4 per-project active-generation protection. If work is already active, the existing active generation is returned/shown and the newly entered instructions are not silently substituted into that work.

Unrelated projects remain usable while another project is generating.

## 7. Generation History

Each project displays a simple list of its generations.

The history shows:

- generation date/time;
- generation status;
- which generation is currently accepted.

Completed generations can be opened for review.

Failed generations can be opened to show a safe user-facing explanation without exposing raw provider errors.

Selecting an older generation does not regenerate it.

Refreshing the project restores generation history and the current accepted plan from Laravel.

The active generation is polled independently of the generation currently being viewed. A background completion must not overwrite an open edit form for another generation.

When switching generations with unsaved edits, the UI requires the user to save or cancel before switching, or clearly asks for confirmation before discarding the changes.

Polling stops when the active generation reaches a terminal status and when the component is unmounted or navigation occurs.

## 8. Failure and Duplicate Handling

Provider failures during regeneration are handled by the existing Day 4 background generation workflow.

The failure is stored against the new generation.

The source generation remains intact.

The source generation's draft remains intact.

The current accepted plan remains intact.

Duplicate or concurrent regeneration requests must not create multiple pending/processing generations for the same project.

A second request must not overwrite the prompt or regeneration instructions of work that is already queued or processing.

Validation failures must occur before creating a generation or dispatching a queue job.

Draft saving, history loading, and acceptance must not call the AI provider or dispatch generation work.

## 9. Acceptance Semantics

The distinction between draft and accepted content is intentional:

```text
Original AI response
        │
        ├── remains unchanged
        │
        ▼
Editable draft
        │
        ├── can be changed later
        │
        ▼
Accepted snapshot
        │
        └── remains unchanged until explicitly replaced
```

The accepted snapshot represents the exact content the user chose at the time of acceptance.

Editing the source generation after acceptance does not silently change the accepted plan.

Regenerating from the source generation does not silently change the accepted plan.

To change the final accepted content, the user must explicitly accept another completed generation or explicitly re-accept the desired content according to the UI workflow.

No full audit history of every acceptance change is required for Day 5.

## 10. Data Safety

Only fields required by the UI are returned to the frontend.

Responses must not expose:

- API keys;
- authorization headers;
- stack traces;
- raw provider errors;
- unrelated user data.

Generated and edited strings are rendered as text rather than executable HTML.

Frontend generation, draft, history, and accepted-plan data use explicit TypeScript types. `any` is not used to bypass type errors.

## 11. Scope

This feature intentionally remains small.

Included:

- review of completed generations;
- editing structured content;
- saved drafts;
- generation history;
- regeneration instructions;
- background regeneration;
- preservation of previous generations;
- accepted content snapshots;
- project ownership and generation/project authorization;
- duplicate/concurrent-generation protection.

Not included:

- production task assignment or completion tracking;
- cost dashboards;
- user quota systems;
- additional AI providers;
- streaming;
- RAG;
- agents;
- rich-text editing;
- per-keystroke revision history;
- visual diffing;
- Redis;
- Horizon;
- a general-purpose document versioning framework.

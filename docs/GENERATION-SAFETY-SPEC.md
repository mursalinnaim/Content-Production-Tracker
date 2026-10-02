# Generation Cost and Safety Specification

## 1. Purpose and Scope

This specification defines the small cost, throttling, input-boundary, and safety additions for AI content generation and regeneration.

The feature reuses the existing Laravel controller/service/job structure, database queue, existing cache/database facilities, and `content_generations` records. It does not introduce a generic billing framework, provider abstraction, or general PII-redaction system.

The existing background-generation behavior remains the foundation:

```text
Vue
  ↓
Generation / regeneration controller
  ↓
Validation + per-user limiter + active-generation protection
  ↓
ContentGeneration record
  ↓
Database queue
  ↓
GenerateContentPlan job
  ↓
OpenAIService
  ↓
OpenAI Responses API
```

Draft saving, acceptance, history reads, and polling remain read/edit operations. They do not create provider work, provider charges, or new generation usage/cost.

---

## 2. Allowed Model and Pricing Configuration

The application continues to use an allowlisted/configured model rather than accepting a model name from the browser.

For the current assignment configuration, the allowed model is:

| Model | Input price / 1M tokens | Output price / 1M tokens | Currency |
| --- | ---: | ---: | --- |
| `gpt-4o-mini` | $0.15 | $0.60 | USD |

The rate was checked against OpenAI's official model documentation on **2026-10-02**:

- Source: https://developers.openai.com/api/docs/models/gpt-4o-mini
- OpenAI API pricing page: https://developers.openai.com/api/docs/pricing

The official model page currently lists `gpt-4o-mini` text-token pricing as $0.15 per 1M input tokens and $0.60 per 1M output tokens. The implementation must not obtain prices from a third-party site or from a remembered value.

Pricing is server-side configuration keyed by the allowed model. The browser cannot select arbitrary pricing.

If the configured model is not in the server-side pricing allowlist, new generation work must not be accepted because a trustworthy estimate cannot be calculated.

Cached-input, tool-call, search, image, audio, discount, or other charges are outside this estimate because this feature only estimates the recorded input/output token usage for the existing text generation request.

---

## 3. Token Usage and Estimated Cost

The existing provider integration records:

- `input_tokens`
- `output_tokens`

When the provider returns trustworthy integer token counts, those values remain attached to the generation that produced them.

The estimate is:

```text
estimated USD =
(
    input tokens × input USD per million
    +
    output tokens × output USD per million
) / 1,000,000
```

The calculation must use decimal-safe arithmetic. Floating-point arithmetic must not be used where it can introduce avoidable currency error.

### Storage precision and rounding

The stored estimate uses decimal storage with **12 fractional decimal places**. The calculation is rounded to 12 decimal places using half-up rounding only at the final stored result.

Example:

```text
1 input token × $0.15 / 1,000,000
= $0.00000015
```

This remains a nonzero stored value. The UI must not convert a small nonzero estimate into an unexplained `$0.00`.

The UI should display enough decimal places to preserve a nonzero estimate, up to the stored precision, while trimming unnecessary trailing zeroes.

### Unavailable usage/pricing

Missing input tokens, missing output tokens, missing pricing, or an unrecognized model/rate produces:

```text
Estimated cost (USD): Unavailable
```

It must not be stored or displayed as zero.

Old generations that predate pricing snapshots remain readable. Migration must not invent historical prices for those records.

A failed or timed-out provider request with no trustworthy usage is not evidence of zero provider cost. Such a request must not be labelled "free".

This estimate does not reconcile:

- unknown usage from failed/ambiguous provider attempts;
- provider discounts or credits;
- cached-input or tool-specific charges not represented by the stored usage fields;
- the final provider invoice.

---

## 4. Pricing Snapshot and Historical Stability

When a new generation or regeneration is accepted for queueing, the application snapshots the pricing inputs together with the queued model.

The snapshot represents:

- selected model;
- input USD price per 1M tokens;
- output USD price per 1M tokens;
- currency (`USD`);
- pricing source/check date where needed for auditability;
- the chosen provider output-token cap.

The snapshot belongs to that generation and is immutable after queue acceptance.

The completed generation stores its calculated estimate using the snapshot values and the provider-returned usage.

Changing `OPENAI_MODEL` or changing server-side rates later must not alter:

- the queued model;
- the queued input/output rates;
- the completed generation's historical estimate;
- historical generation usage.

A generation that has no pricing snapshot because it existed before this feature remains valid and reports cost as **Unavailable**.

---

## 5. Shared Per-User Generation Limiter

Initial generation and regeneration use one named Laravel limiter.

Default:

```text
5 submissions per authenticated user per 60 seconds
```

The limit and window are server-side configuration values.

The limiter key is based on the authenticated **user ID**, not project ID. Therefore:

- all projects owned by one user share the same allowance;
- initial generation and regeneration share the same allowance;
- a different authenticated user has a separate allowance.

This is request throttling, not a paid-generation quota.

### Counting policy

After authentication, an eligible generation/regeneration submission reaches the named limiter before normal request validation.

A submission consumes one allowance when it reaches the limiter, including when the request later:

- fails input validation;
- fails stored-content validation;
- finds an active generation and reuses that work.

A request rejected by the limiter itself does not create a generation, dispatch a queue job, or call OpenAI.

The limiter does not replace the existing per-project transaction/lock and active-generation protection.

The following operations do **not** consume the generation allowance:

- history reads;
- generation status polling;
- draft save;
- acceptance.

The deployed application uses the existing shared Laravel cache/database facilities. A process-local array is not acceptable.

### Exhaustion response

On exhaustion:

- HTTP status: **429**
- stable safe error code: `generation_rate_limited`
- safe message explaining that generation requests are temporarily limited;
- retry timing through `Retry-After` or an equivalent explicit `retry_after` field.

The Vue client displays the message without clearing:

- unsent regeneration instructions;
- saved drafts;
- accepted content;
- existing generation history.

The browser does not automatically retry a rejected POST.

A provider HTTP 429 occurring inside a queued worker is a different condition. It remains governed by the existing worker retry policy and is never represented as the application request-limit 429.

---

## 6. Input Bounds

The following assignment defaults are authoritative:

| Input | Maximum |
| --- | ---: |
| Project title | 200 characters |
| Project brief | 5,000 characters |
| Project notes | 5,000 characters |
| Regeneration instructions | 2,000 characters |
| Draft suggested title | 200 characters |
| Draft content brief | 5,000 characters |
| Each collection | 20 entries |
| Each outline heading | 200 characters |
| Each outline purpose | 1,000 characters |
| Each string-list item | 1,000 characters |
| Final composed prompt | 30,000 characters |

These are character limits, not byte limits.

Validation occurs on Laravel even when the browser provides matching guidance. The server validates both:

1. values supplied by the browser; and
2. stored project/source-generation content used to build the prompt.

Older database records may predate these limits and therefore must be checked when they are actually used.

The implementation must reject over-limit input with useful HTTP 422 field errors. It must never silently truncate the user's text.

---

## 7. Exact Validation Order

For an initial-generation or regeneration POST, validation follows this order:

1. Authentication middleware establishes an authenticated user.
2. The shared named generation limiter is checked using that user's ID.
3. Project ownership is checked.
4. Request-shape and request-field validation is performed.
5. Stored project/source-generation content is validated against the same applicable bounds.
6. Regeneration-specific source/content checks are performed.
7. The existing service prepares the exact prompt/model inputs.
8. The final composed prompt is measured and rejected if it exceeds 30,000 characters.
9. Inside the existing project transaction/lock, active-generation protection is checked.
10. The generation record is created with the complete queued configuration snapshot.
11. The existing queue job is dispatched after commit.

The limiter intentionally precedes ordinary validation so failed validation submissions still consume the simple request allowance once they reach the limiter.

No provider request occurs before the generation has passed all application validation and been safely created.

A rejected request leaves existing generations, saved drafts, and accepted snapshots unchanged.

---

## 8. Provider Output Bound and Completion Rule

The Responses API request uses a bounded output-token parameter of:

```text
2048 output tokens
```

This is below the currently documented `gpt-4o-mini` maximum output capacity and is sufficient for the application's structured content-plan response.

The value is server-side configuration, not browser-controlled.

A provider response is successful only when:

- the request itself succeeded;
- the response is complete rather than incomplete/truncated;
- structured output is present;
- the JSON is valid;
- application-level content validation succeeds.

An incomplete/truncated provider response is a safe terminal failure, not completed content.

Earlier successful generations, saved drafts, and the accepted snapshot remain unchanged when a later generation fails.

---

## 9. Retry and Failure Policy

The existing conservative retry policy remains:

- An explicit provider HTTP 429 may receive **one** bounded delayed retry.
- Therefore one generation can make at most two provider attempts under this policy.
- A second provider 429 becomes a terminal safe failure.
- Ordinary provider failures are not automatically retried.
- Ambiguous connection timeouts are not automatically retried because the provider may already have processed the request.
- Malformed JSON or structurally invalid output is a terminal safe failure.
- An incomplete/truncated response is a terminal safe failure.
- Missing configuration is a terminal safe failure.

The retry lifecycle must preserve the original generation's model and pricing snapshot. It must not re-read current pricing or model configuration on the second attempt.

A provider 429 inside the queue worker must remain distinguishable from the application-level request limiter 429.

---

## 10. Browser-Facing Error Contract

Browser-facing generation failures expose only stable application fields.

Expected shape:

```json
{
  "error_code": "generation_rate_limited",
  "message": "Generation requests are temporarily limited. Please try again shortly.",
  "retry_after": 42
}
```

Fields are included only when relevant. `retry_after` is required for the application rate-limit response.

Safe generation failures use stable codes such as:

- `generation_rate_limited`
- `validation_failed`
- `configuration_error`
- `provider_rate_limited`
- `provider_error`
- `invalid_json`
- `incomplete_response`
- `generation_failed`

Provider response bodies, stack traces, exception dumps, headers, API keys, authorization values, and internal serialized model objects must not appear in JSON or Inertia props.

The browser receives only the fields required to render the generation/history/review UI.

---

## 11. Browser Data and Rendering Safety

The backend explicitly selects generation fields for API/Inertia responses.

Generation prompts are internal server data and must not be exposed to the browser unless a future requirement explicitly needs a safe subset.

Credentials remain server-side.

AI-generated and user-provided strings are rendered as text, not executable HTML.

A field allowlist controls which application fields leave the server. It does **not** guarantee that free-form user/project text contains no private information.

This feature therefore does not implement general PII detection or redaction.

Synthetic test data must be used for secret/privacy tests.

---

## 12. No-Charge Operations

The following operations must not create provider work or mutate token usage/cost:

- draft save;
- acceptance;
- history reads;
- generation status polling;
- switching historical versions;
- loading accepted content.

They must not dispatch the generation job or call OpenAI.

---

## 13. Data Model Direction

The implementation should extend the existing `content_generations` record rather than introduce a separate billing subsystem.

The generation needs enough immutable data to reconstruct its estimate later:

- existing `model`;
- existing `input_tokens`;
- existing `output_tokens`;
- snapshotted input rate;
- snapshotted output rate;
- snapshotted currency;
- estimated cost;
- pricing source/check metadata where useful for auditability.

The exact migration column names may follow the repository's existing naming conventions.

Historical records without the new fields remain valid and return cost as **Unavailable**.

---

## 14. Selected Version Display

For a selected completed generation, the UI displays:

- model;
- input token count;
- output token count;
- **Estimated cost (USD)**.

When the user switches generation versions, these values come from the selected generation rather than the currently active generation.

If usage or pricing is unavailable, the selected version shows:

```text
Estimated cost (USD): Unavailable
```

The UI must not substitute current configured pricing for an older generation.

---

## 15. Testing Design Constraints

Automated tests must never depend on the current OpenAI pricing webpage.

Pricing tests use deterministic synthetic rates and verify:

- exact formula results;
- a small nonzero cost;
- rounding boundaries;
- missing usage;
- missing pricing;
- historical records without snapshots;
- configuration changes after enqueue.

Provider tests use fake Responses API fixtures and `Http::preventStrayRequests()`.

No paid provider call is required for this assignment.

The implementation must also preserve the existing Day 5 deferred-response regressions and prove rendered Vue behavior rather than relying only on helper-level tests.

---

## 16. Out of Scope

This feature does not include:

- a generic billing framework;
- user billing accounts;
- invoices;
- payment processing;
- paid-generation quotas;
- provider spend reconciliation;
- general PII-redaction;
- multiple AI providers;
- automatic retry after ambiguous timeouts;
- real API calls in automated tests;
- provider-side discount accounting;
- a guarantee that estimates equal final invoices.

The purpose is to provide deterministic application-side estimates, request throttling, bounded inputs, and safe generation behavior while keeping the existing architecture small.

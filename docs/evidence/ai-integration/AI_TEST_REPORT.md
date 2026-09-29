# HIMS AI Integration Test Report

**Test date:** 2026-09-29 (Asia/Manila)  
**Checklist item:** AI Integration  
**Verification requirement:** AI features perform as expected with accurate responses and acceptable processing time.  
**Overall result:** **Pass with documented limitations**

## Executive Result

The implemented AI paths are operational, grounded in authorized HIMS data, and fail safely. No application source-code defect was confirmed, so no AI feature was rebuilt or changed.

- The focused AI/privacy suite passed **199 tests (1,102 assertions)** in **36.95 seconds**.
- The full repository regression suite passed **1,647 tests (12,466 assertions)** in **270.91 seconds**.
- The production frontend build passed with **87 modules transformed** in **11.36 seconds**.
- Three live synthetic Gemini probes exercised the configured primary-to-fallback sequence. The primary model returned HTTP 429, while the first fallback returned valid responses. End-to-end provider sequence time was **1.55-1.90 seconds**, below the configured 12-second per-request timeout.
- No AI/Gemini application error was present in the latest 3,000 server-log lines. Errors present there were deliberate, unrelated regression-test scenarios.

This is not a formal statistical certification of predictive accuracy. The repository has no documented forecast-error target or historical holdout benchmark (for example, MAE/MAPE). Forecast accuracy is therefore supported by deterministic arithmetic tests, input/output invariants, grounded-record checks, and live response-contract checks rather than an accuracy percentage.

## Test Environment

| Component | Verified value |
| --- | --- |
| Operating environment | Windows, local application environment |
| PHP | 8.2.12 |
| Laravel | 12.63.0 |
| Node.js | 24.20.0 |
| npm | 11.19.0 |
| Automated database | SQLite `:memory:` from `phpunit.xml` |
| Configured application database | MySQL; not read or modified during live AI probing |
| Configured primary model | `gemini-3.6-flash` |
| Configured fallback models | `gemini-flash-lite-latest`, `gemini-3.1-flash-lite` |
| Provider timeout | 12 seconds per model request; 5-second connection timeout |
| Forecast cache | 360 minutes |
| Forecast item cap | 100 items |
| Live probe data | Synthetic prompts only; no user, patient, credential, or production inventory data |

## AI Feature Inventory

### 1. AI stock demand forecasting

**Purpose:** Generate advisory demand, risk, reorder priority, projected stock status, trend, confidence, and reorder quantities for active inventory items.

**Actual implementation:** `AiDemandForecastService` prepares recorded consumption, movement series, current stock, reorder thresholds, lead time, safety stock, stockout events, and pending procurement. Gemini receives minimized numeric inventory context and must return a defined JSON schema. Server validation requires one distinct known item ID per input item, non-negative bounded integer values, allowed enums, and plausible reorder quantities. Invalid output is rejected. A labeled statistical moving-average forecast is used when the key is missing or the provider/model fails. Page loads receive an immediate statistical result and queue the external-model warm-up under a cache lock.

**Entry points:**

- `GET /inventory/demand-forecast`
- `POST /inventory/demand-forecast/refresh`
- Dashboard demand-forecast controls
- `WarmAiDemandForecast` queued job

**Persistence behavior:** Forecast generation caches an advisory snapshot and writes an audit event. It does **not** directly change inventory, create a purchase order, or overwrite actual demand records. A user must separately save a demand plan through the authorized workflow.

### 2. HIMS AI Inventory Assistant

**Purpose:** Answer inventory, stock, supplier, procurement, expiry, movement, requisition, logistics, audit, and forecast questions from authorized HIMS records.

**Actual implementation:** `AiInventoryAssistantService` combines deterministic intent/entity handling, backend-authorized tools, Gemini generation, prompt-protection boundaries, and a grounded deterministic fallback. External responses do not become database facts. Tool declarations are permission-filtered and authorization is checked again before execution.

**Entry points:**

- `POST /dashboard/ai-assistant` (authenticated and rate-limited to 30 requests/minute)
- Conversation/history endpoints scoped to the authenticated owner
- Private attachment endpoint scoped to the conversation owner

**Attachments:** PDF, CSV, XLSX, DOCX, TXT, JPG, JPEG, and PNG are validated. Spreadsheet/document text can be analyzed as untrusted data. Raw image content remains internal and is not sent to Gemini. Attachment analysis does not import or change inventory records.

### 3. Forecast-backed requisition recommendation

**Purpose:** Suggest a department requisition quantity for a selected inventory item.

**Actual implementation:** `AiDemandForecastService::reorderRecommendationForItem()` uses a cached AI forecast only when available; otherwise it uses the deterministic `DemandForecastService`. It factors available stock, reservations, incoming purchase orders, safety stock, reorder point, minimum order quantities, and pack size. The material-requisition controller applies department-replenishment rules and leaves the recommendation advisory.

**Entry point:** `GET /inventory/requisitions/ai-recommendation/{item}`

**Important classification:** This is not always an external-model result. The response explicitly identifies AI versus statistical source internally, and insufficient history returns a manual-entry state.

## Features Reviewed but Not Classified as AI

| Component | Actual mechanism |
| --- | --- |
| `AiChatTitleGenerator` | Local regular-expression and headline rules; it explicitly makes no external AI call |
| `Analytics\RecommendationEngine` | Deterministic thresholds and business rules over supplier, price, bottleneck, and shrinkage metrics |
| Near-expiry, expired, and no-expiry answers | Database date/status queries and deterministic formatting; Gemini may phrase or select an authorized tool, but the record classification is not ML |
| Statistical demand fallback | Deterministic moving-average/trend logic, clearly labeled `Statistical Forecast` |

## Representative Functional and Accuracy Results

| Feature / scenario | Test input | Expected or acceptable behavior | Actual result | Evaluation method | Status |
| --- | --- | --- | --- | --- | --- |
| Demand forecast, valid history | Synthetic inventory and recorded consumption movements | Return exactly the requested item and horizon; integers are non-negative; history drives the output | Validated forecast returned for the known item; horizon and chart totals preserved | Database fixtures, exact assertions, response schema, item-ID allowlist, total-series invariants | Pass |
| Demand forecast, multiple periods | 7, 14, 30, 60, and 90-day dashboard periods | Each result covers its requested period without stale overwrite | Full horizons returned; stale requests are cancelled and results cached by period | Feature assertions over returned series and frontend request IDs | Pass |
| Demand forecast, insufficient/no history | Active item with sparse or no consumption | Low confidence or explicit insufficient-data state; no crash | Statistical result labels limited data; requisition flow asks for manual quantity when no planning basis exists | Exact source/confidence/message assertions | Pass |
| Demand forecast, malformed model data | Unknown item ID or invalid response | Reject untrusted model output and never render it | Invalid AI item IDs rejected; statistical fallback returned | Mocked provider response plus rendered-output assertion | Pass |
| Demand forecast, provider failure | 503/connection/transient error | Retry/fail over, then return labeled fallback without changing inventory | Secondary model attempted; statistical fallback returned where all models failed | HTTP fake sequence, source assertion, cache-expiry assertion, unchanged records | Pass |
| Inventory assistant, exact item | `Tell me about` a fixture item | Name, SKU, stock, and reorder data must match the database | Reply contained the exact fixture item and recorded figures | Exact string/database fixture assertions | Pass |
| Inventory assistant, expiry distinctions | No-expiry, already-expired, and nearing-expiry prompts | Each intent returns only the corresponding record set | Three distinct datasets returned; requested expiry window honored | Known dated batches and inclusion/exclusion assertions | Pass |
| Inventory assistant, multi-turn | Item list followed by ordinal/pronoun questions | Preserve the referenced item without inventing a different record | Follow-up supplier, stock, and pending-order answers remained tied to the selected item | Persisted history and exact item/SKU assertions | Pass |
| Inventory assistant, unrelated question | Weather, geography, joke, poem, or programming prompts | Decline or redirect to HIMS scope; do not fabricate inventory context | Requests were declined without unrelated answers or fake inventory summaries | Negative and positive content assertions | Pass |
| Inventory assistant, external-model success | Mocked valid Gemini content | Return model text with `source=ai` | AI response returned with the expected source and structure | HTTP request fake and JSON assertions | Pass |
| Inventory assistant, provider unavailable | Mocked Gemini 503 | Return verified grounded data and a retry-safe user experience | `source=grounded_fallback`; reply contained the actual low-stock fixture | HTTP failure fake plus exact database-backed content assertion | Pass |
| Attachment analysis | Valid CSV/XLSX/DOCX/TXT and image files | Validate content/type, analyze safely, do not mutate inventory | Supported documents processed; images stayed internal; inventory unchanged | Private fake storage, audit assertions, before/after database assertions | Pass |
| Requisition recommendation | Low stock, surplus stock, incoming PO, and no history | Return structured advisory quantity; avoid duplicate procurement; never force invalid zero for department replenishment | Endpoint structure valid; incoming supply reduced recommendation; insufficient data handled; department suggestion remained at least 1 when a planning basis existed | Exact JSON/database/audit assertions | Pass |

## Failure-Handling Results

| Failure | Verified behavior | Status |
| --- | --- | --- |
| Missing chat input | HTTP 422 validation response | Pass |
| Message over 1,000 characters | HTTP 422 validation response | Pass |
| Missing Gemini key | Labeled grounded/statistical fallback | Pass |
| Primary model unavailable/rate-limited | Configured fallback models are tried | Pass |
| All provider models unavailable | Grounded assistant or statistical forecast returned | Pass |
| Invalid Gemini JSON/schema/item IDs | Response rejected; untrusted values not rendered or saved as facts | Pass |
| Empty active inventory | Safe 422/empty state; failure audit; no inventory mutation | Pass |
| Unsupported, empty, oversized, or content-mismatched attachment | Safe validation error; no chat/attachment persistence for rejected content; retry remains possible | Pass |
| Unauthorized/guest access | Redirect, 403, or owner-scoped 404/403 as appropriate | Pass |
| Prompt injection / secret request / permission bypass | Request blocked or tool denied; no provider/tool privilege escalation | Pass |
| Unexpected controller exception | Generic user-facing 500 response; exception reported server-side without exposing stack trace | Pass |

## Processing-Time Results

The repository defines a **12-second timeout per Gemini model request** but no product-level response-time SLA. The pass criterion used here is completion within that configured timeout, with the fallback sequence producing a valid response.

| Test case | Primary attempt | Fallback attempt | Measured fallback-sequence duration | Result |
| --- | ---: | ---: | ---: | --- |
| Assistant exact-response synthetic probe | HTTP 429 in 575.1 ms | HTTP 200 in 973.0 ms | 1,548.1 ms | Pass via fallback |
| Structured forecast synthetic probe | HTTP 429 in 418.7 ms | HTTP 200 in 1,485.2 ms | 1,903.9 ms | Pass via fallback |
| Insufficient-history synthetic probe | HTTP 429 in 453.9 ms | HTTP 200 in 1,153.2 ms | 1,607.1 ms | Pass via fallback |

Additional representative automated request durations from the full suite:

- Valid AI forecast route and validation: **0.22 s**
- AI assistant exact item query: **0.07 s**
- AI assistant provider-failure fallback: **0.06 s**
- Requisition AI-recommendation endpoint: **0.04 s**
- Multi-turn conversation context: **0.10 s**
- Attachment processing cases: typically **0.05-0.14 s**

Automated durations include framework and in-memory database work; external requests in those tests are mocked. Live timings above cover the configured provider sequence with synthetic data and exclude application database work.

## Loading, Empty, Success, Error, and Retry States

Source and automated tests verify:

- Demand forecast views expose separate loading, success, empty, and error states.
- Forecast requests use skeletons, `aria-busy`, retry controls, request cancellation, and request IDs so older responses cannot overwrite a newer selection.
- The assistant disables duplicate sends while processing, shows a context-aware loading message, renders a safe error, and permits the user to submit again.
- Requisition recommendation UI exposes an item-specific loading state and leaves manual quantity entry available when no recommendation exists.
- Generated assistant text is escaped before Markdown formatting; links are restricted to internal paths or HTTP(S).

An authenticated manual browser/console check was attempted against an isolated SQLite database, but the computer-control environment exposed no browser surface (`apps: []`, `browsers: []`). Visual interaction, browser-console errors, responsive layout, and real focus behavior are therefore **not manually verified in this report**; they are covered only by source inspection, Blade/JavaScript assertions, and the production build.

## Security and Privacy Results

- Authentication is required for every AI endpoint.
- Assistant access requires `ViewInventory` or `ViewReports`; forecast viewing and generation use separate permissions.
- Conversation and attachment reads are owner-scoped.
- Tool declarations are filtered by permission and re-authorized at execution.
- Prompt, history, attachment text, and external context are treated as untrusted data.
- PhilHealth PINs, TINs, Philippine mobile numbers, email addresses, payment cards, and key/value credentials are redacted.
- Structured external payloads remove actor/user IDs, names, emails, phone numbers, contacts, IP addresses, and similar personal fields.
- Forecast prompts exclude item names, SKUs, actor identity, and secrets; only server-selected numeric/operational fields are sent.
- API keys remain server-side and were absent from rendered dashboard output and inspected external payloads.
- AI output cannot directly write inventory, approve procurement, change permissions, or execute arbitrary code/SQL.

Focused adversarial evidence is also documented in `docs/security/ai-security-report.md`.

## Commands and Counts

```text
php artisan test tests/Feature/AiDemandForecastTest.php tests/Feature/AiInventoryAssistantTest.php tests/Feature/AiChatbotConversationHistoryTest.php tests/Feature/AiChatbotAttachmentTest.php tests/Feature/AiPromptProtectionTest.php tests/Feature/Privacy/AiDataSanitizerTest.php
```

Result: **199 passed (1,102 assertions)** in **36.95 s**.

```text
php artisan test
```

Result: **1,647 passed (12,466 assertions)** in **270.91 s**.

```text
npm.cmd run build
```

Result: **Pass**, Vite 7.3.6, **87 modules transformed**, **11.36 s**.

The live provider probe made three synthetic requests through the configured primary/fallback model order and recorded only model name, HTTP status, duration, timeout compliance, and response-contract validity. It did not transmit application records or credentials in prompt content.

## Defects, Fixes, and Retest

**Confirmed application defects:** None.  
**Source-code fixes applied:** None.  
**Evidence added:** This AI Test Report.  
**Retest result:** Focused AI/privacy suite, full regression suite, frontend production build, and live synthetic fallback probes passed within the limits described above.

**Operational finding:** The configured primary model returned HTTP 429 during all three live probes. `gemini-flash-lite-latest` returned HTTP 200 with valid contracts, so the implemented failover preserved functionality. The primary model's quota/availability should be monitored; no application code change is indicated by this result.

## Known Limitations and Residual Risk

1. No historical holdout dataset or accepted MAE/MAPE threshold exists, so no predictive-accuracy percentage can be claimed.
2. Gemini output remains probabilistic. Server schema, range, identity, permission, and plausibility controls reduce risk but do not prove clinical or procurement correctness.
3. The configured timeout applies per model attempt. With three configured models, worst-case total wait can exceed 12 seconds even though the measured fallback sequences were below 2 seconds.
4. Manual authenticated browser interaction and console inspection were unavailable in this environment.
5. Live probing used synthetic data and directly verified provider availability/contract behavior; full application integration used isolated automated tests with mocked provider responses.

## Final Checklist Decision

The existing HIMS AI integration **satisfies the functional, failure-handling, security, privacy, and measured-response requirements for the implemented features**, with no code change required. The checklist should be recorded as **Pass with limitations**, specifically noting the lack of a formal forecast-accuracy benchmark, the current primary-model HTTP 429 condition with successful fallback, and the unavailable manual browser-console verification.

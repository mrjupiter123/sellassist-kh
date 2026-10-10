# AI order suggestions

SellAssist KH can generate a suggestion from a social conversation after an authorized seller explicitly clicks **Generate suggestions**. This feature does not run automatically.

## Data boundary

Each request sends the following to OpenAI:

- at most the 40 latest inbound customer text messages in the conversation;
- active product names, SKUs, public UUIDs, and active variant names, SKUs, and public UUIDs.

It does not send product prices, costs, inventory quantities, payments, API credentials, outbound staff replies, or unrelated conversations. The Responses API request sets `store` to `false` and requests a strict JSON-schema result.

The returned customer and address fields and result payload are encrypted at rest using Laravel's application key. Provider credentials stay in environment configuration. Provider errors saved for operators are deliberately generic so response bodies cannot leak a credential.

## Review boundary

AI output is untrusted. Product and variant UUIDs are resolved again against the active local catalog. A variant is accepted only when it belongs to the resolved product. Unresolved suggestions remain visible for manual selection.

The feature never automatically:

- links or creates a customer;
- creates or confirms an order;
- changes inventory;
- records a payment;
- creates a shipment.

Matched suggestions only prefill the existing customer and reviewed draft-order forms. Normal validation, server-side catalog pricing, permissions, and order workflows remain authoritative.

## Environment setup

Add these values to the server `.env`:

```dotenv
SOCIAL_AI_EXTRACTION_ENABLED=true
OPENAI_API_KEY=replace-with-a-server-side-project-key
OPENAI_ORDER_EXTRACTION_MODEL=gpt-5.4-mini
SOCIAL_AI_LOW_CONFIDENCE_THRESHOLD=0.65
SOCIAL_AI_PROMPT_VERSION=builtin-v1
SOCIAL_AI_EVALUATION_PASS_THRESHOLD=0.85
SOCIAL_AI_EVALUATION_APPROVAL_THRESHOLD=0.85
SOCIAL_AI_EVALUATION_REGRESSION_TOLERANCE=0.02
SOCIAL_AI_RELEASE_MONITORING_WINDOW_DAYS=14
SOCIAL_AI_RELEASE_MONITORING_MINIMUM_SAMPLES=10
SOCIAL_AI_RELEASE_MONITORING_MINIMUM_REVIEWS=5
SOCIAL_AI_RELEASE_SUCCESS_RATE_DROP=0.10
SOCIAL_AI_RELEASE_CONFIDENCE_DROP=0.10
SOCIAL_AI_RELEASE_USEFUL_RATE_DROP=0.15
```

Use a Structured Outputs-capable model available to the OpenAI project. Never place the API key in JavaScript, Blade, a route, a database row, source control, screenshots, or logs.

After editing `.env`, run:

```bash
php artisan optimize:clear
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=AiEvaluationCaseSeeder --force
php artisan optimize
```

The database queue worker must process `integrations`:

```bash
php artisan queue:work --queue=integrations,default --tries=3 --timeout=240
```

On shared cPanel hosting, the existing once-per-minute `--stop-when-empty` cron strategy is sufficient.

The hourly release-degradation monitor is registered with Laravel's scheduler. Configure `php artisan schedule:run` once per minute on cPanel. A degraded release creates one database notification for each active administrator with `social.ai.manage`; the same set of degradation reasons is deduplicated until the condition resolves or materially changes. Notifications are advisory and never execute rollback.

AI administrators can opt into email delivery from **Alerts**. Email is off by default. An opted-in administrator receives a queued email for a new degradation event, and the worker checks their active account, permission, and preference again before sending. The message contains the profile name, aggregate degradation reasons, and a link to the release monitor; it does not include customer data. Configure Laravel mail credentials on the server to deliver mail. With the default `MAIL_MAILER=log`, messages are written to the application log instead of reaching an inbox. Database alerts remain available whether or not email is enabled.

## Operational flow

1. A seller with `social.extract` opens an unconverted conversation.
2. Clicking **Generate suggestions** snapshots the current inbound message IDs and queues one idempotent extraction for that snapshot.
3. The worker submits the limited payload and stores either encrypted suggestions or a safe failure message.
4. The seller refreshes the conversation, reviews the confidence and unresolved matches, and edits the normal forms.
5. Only an explicit form submission enters the trusted customer or draft-order workflow.

## Quality review and usage

Completed suggestions show overall and per-item confidence. Values below `SOCIAL_AI_LOW_CONFIDENCE_THRESHOLD` receive a visible warning; the threshold changes presentation only and never approves or rejects an order automatically.

The application records the response's input, output, and total token counts when the provider returns them. These are operational usage measurements, not a currency estimate, because model pricing can change. No API key or provider response body is stored with the usage counters.

Sellers can classify each completed extraction as useful, corrected, or not useful and separately rate customer fields, product matches, and quantities. Free-text correction notes are encrypted. Submitting feedback never trains a model automatically and never changes customer, order, inventory, payment, or shipment data.

Automated tests use mocked provider responses and synthetic Khmer/English examples. They verify the extraction contract and safety boundaries without sending test data to OpenAI or incurring API usage.

Administrators with `operations.view` can monitor aggregate extraction quality under **Operations**. The dashboard provides fixed 7, 30, and 90-day filters, success and failure rates, low-confidence counts, seller review outcomes, per-model performance, daily token usage, and recent review metadata. It intentionally does not decrypt or display conversations, extracted customer fields, provider payloads, or correction notes.

## Prompt and model profiles

Administrators with `social.ai.manage` can create profile versions under **AI Profiles**. A profile contains a human-readable name, immutable version, OpenAI model identifier, and additional extraction guidance. Only one managed profile is active at a time. When no managed profile is active, the application uses `OPENAI_ORDER_EXTRACTION_MODEL` with the built-in prompt and `SOCIAL_AI_PROMPT_VERSION`.

The permanent safety instructions are always prepended. Profile guidance cannot remove the requirements to ignore customer-supplied instructions, avoid invented facts, use local catalog references, and return suggestions for seller review only.

Profiles have no edit or delete workflow. Improvements are new versions. Activating a new version changes only future extraction requests, while reactivating an older version is the rollback mechanism. Every activation and rollback requires an administrator reason and creates an immutable release-history record containing the prior profile and approved evaluation run. Each extraction records the profile reference, model, prompt version, and SHA-256 hash of its effective instructions. Historical and already queued extractions are never rewritten.

Each release-history row links to aggregate post-release monitoring. The monitor compares the previous profile during the configured pre-release window with the released profile after activation, stopping the candidate window at the next release when applicable. It compares completed-sample success rate, average confidence, reviewed usefulness, and token usage without loading or displaying messages, customer identities, addresses, extraction payloads, or review notes. Threshold breaches produce an advisory manual-rollback recommendation only. They never switch profiles automatically.

Changing the active profile changes the extraction idempotency identity, so the same conversation can be evaluated once under each distinct profile version. API access is still checked only when the queued request runs; administrators should test a new model/version on staging before broader activation.

## Controlled synthetic evaluations

Run `php artisan db:seed --class=AiEvaluationCaseSeeder --force` after deployment to install the curated Khmer and English fixtures. Inputs, synthetic catalog records, expected results, actual provider results, and difference lists are encrypted at rest. The fixtures use invented names, phone numbers, addresses, and UUIDs and never read social conversations, customers, products, orders, or production catalog data.

Administrators can add encrypted synthetic cases and then create a frozen dataset version under **AI Profiles → Run evaluations**. Existing dataset membership is never edited: adding or removing a case requires a new version with release notes. Administrators select both a profile and dataset when starting a run. The frozen case IDs are snapshotted on the run, and each case is sent through the `integrations` queue with `store: false`. The deterministic local scorer compares customer fields, normalized phone numbers, and order items against the expected structured output. It does not use another model as a grader.

`SOCIAL_AI_EVALUATION_PASS_THRESHOLD` controls individual-case pass/fail. Side-by-side comparisons require both runs to use the same frozen dataset. `SOCIAL_AI_EVALUATION_REGRESSION_TOLERANCE` controls the allowed per-case and aggregate score drop; changing a formerly passing case to failing is always a regression. If an approved active profile has a baseline run, candidate approval is blocked when this comparison detects regression. A profile can otherwise be approved only when its completed run meets `SOCIAL_AI_EVALUATION_APPROVAL_THRESHOLD`, contains a result for every snapshotted case, has no provider-error results, and the administrator records release notes. Approval only marks the profile eligible; activation is a separate administrator action. No evaluation result can approve or activate itself.

Official references:

- [Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs)
- [Responses API create method](https://developers.openai.com/api/reference/cli/resources/responses/methods/create)
- [OpenAI API data controls](https://developers.openai.com/api/docs/guides/your-data?popup=false)

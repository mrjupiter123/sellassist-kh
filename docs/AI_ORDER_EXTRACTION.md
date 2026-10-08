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
```

Use a Structured Outputs-capable model available to the OpenAI project. Never place the API key in JavaScript, Blade, a route, a database row, source control, screenshots, or logs.

After editing `.env`, run:

```bash
php artisan optimize:clear
php artisan db:seed --class=RolePermissionSeeder --force
php artisan optimize
```

The database queue worker must process `integrations`:

```bash
php artisan queue:work --queue=integrations,default --tries=3 --timeout=60
```

On shared cPanel hosting, the existing once-per-minute `--stop-when-empty` cron strategy is sufficient.

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

Official references:

- [Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs)
- [Responses API create method](https://developers.openai.com/api/reference/cli/resources/responses/methods/create)
- [OpenAI API data controls](https://developers.openai.com/api/docs/guides/your-data?popup=false)

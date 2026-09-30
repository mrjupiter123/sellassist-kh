# Facebook Messenger order intake

SellAssist receives Facebook Page Messenger events into a reviewed social inbox. Incoming messages never confirm an order, change inventory, record a payment, or create a shipment. Staff must link or create a customer and explicitly convert a conversation into a draft order; normal order permissions and workflows apply afterward.

## Meta configuration

Create a Meta app and configure its Page webhook callback as:

```text
https://YOUR_DOMAIN/api/social/meta/webhook
```

Set server-only values in `.env`:

```dotenv
META_APP_SECRET=
META_WEBHOOK_VERIFY_TOKEN=use-a-long-random-value
META_PAGE_ACCESS_TOKEN=
```

Never expose or commit these values. In Meta, use the same verification token, subscribe the Page to Messenger message events, then add its numeric Facebook Page ID under **Social Inbox → Messenger settings**. The Page must be active locally for messages to be accepted.

Meta signs POST payloads in `X-Hub-Signature-256`. SellAssist validates the HMAC-SHA256 signature with `META_APP_SECRET` before storing anything. Payloads are encrypted at rest, their SHA-256 body hash is the idempotency key, and processing runs on the `integrations` queue.

## Queue worker

```bash
php artisan queue:work --queue=integrations,default --tries=3 --timeout=60
```

On shared cPanel without a persistent process, use the minute cron documented in [cPanel deployment](CPANEL_DEPLOYMENT.md). Administrators can inspect failures and safely request a retry under **Operations**.

## Review workflow

1. A signed webhook creates or updates the Page contact and open conversation.
2. Exact, unambiguous phone or Facebook-name matches are suggestions only; no customer is automatically merged.
3. Staff links an existing customer or creates a new one with Khmer address fields.
4. Staff selects catalog items and converts the conversation to a draft.
5. Product prices and totals are resolved again on the server.
6. A seller reviews the normal order screen and confirms only when ready. Stock remains unchanged while the order is a draft.

Telegram and AI extraction are not included in this increment. They should reuse this reviewed-draft boundary rather than bypassing it.

Official Meta setup reference: [Webhooks for Pages](https://developers.facebook.com/docs/graph-api/webhooks/getting-started/).

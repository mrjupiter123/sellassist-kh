# Delivery provider integrations

## Supported adapter

The first outbound adapter is L192. It uses the officially documented package endpoints:

- `POST /v1/packages` to create a package;
- `GET /v1/packages/{id}` to retrieve tracking state.

Official references: [L192 API introduction](https://docs.l192.com/docs/l192-api), [package creation](https://docs.l192.com/docs/l192-api/8/create-delivery-package), and [package tracking/statuses](https://docs.l192.com/docs/l192-api/26/tracking-your-package).

The adapter supports USD shipments because the published package fields describe package value and COD in USD. KHR shipments remain manual until the provider confirms conversion and settlement behavior. Pickup-request and cancellation calls are not implemented because their complete request contracts were not sufficiently verifiable from the public documentation.

## Configuration

Request an access token from L192, then configure production secrets only in `.env`:

```dotenv
L192_API_BASE_URL=https://developer-stage.l192.com
L192_API_TOKEN=
L192_AUTH_HEADER=Authorization
L192_AUTH_PREFIX=Bearer
L192_WEBHOOK_SECRET=
L192_SENDER_NAME=
L192_SENDER_PHONE=
L192_SENDER_ADDRESS=
L192_SENDER_LAT=
L192_SENDER_LNG=
```

Confirm the authentication header and prefix with the issued merchant credentials before enabling production. In Delivery Providers, select **L192 Delivery** and enable API integration. Pack an order before submitting its shipment.

## Queue worker

Provider calls and webhook processing run on the database queue:

```bash
php artisan queue:work --queue=integrations,default --tries=3 --timeout=240
```

Use a persistent cPanel process where available. Otherwise run `queue:work --stop-when-empty` every minute from cron. Failed jobs remain in `failed_jobs` for review and retry.

## Webhook gateway contract

The inbound endpoint is:

```text
POST /api/delivery/webhooks/{provider_uuid}
```

SellAssist requires:

- JSON request body;
- `X-SellAssist-Signature`: lowercase HMAC-SHA256 of the exact raw body using `L192_WEBHOOK_SECRET` (an optional `sha256=` prefix is accepted);
- optional `X-Delivery-Event-Id`; otherwise the body hash is the idempotency key.

Public L192 pages mention webhooks but do not expose a signature-header contract that can be safely verified here. Until L192 supplies a merchant-specific signed-webhook specification, place a trusted gateway/relay in front of SellAssist that authenticates the provider request and adds the SellAssist HMAC signature. Never expose an unsigned webhook endpoint.

Webhook payloads and API responses are encrypted at rest. Duplicate events are acknowledged without being processed twice. Unknown shipments and statuses are retained as ignored events for diagnosis; processing failures are retained with their error.

## Status mapping

| L192 status | SellAssist status |
| --- | --- |
| `DRAFT` | Pending |
| `SCHEDULE_REQUEST_PICKUP`, `REQUEST_PICKUP` | Ready for pickup |
| `CLAIM_PICKUP`, `PICKUP` | Picked up |
| `ARRIVED`, `TRANSIT`, `DELIVERING` | In transit |
| `DELIVERED` | Delivered |
| `DELETED` | Cancelled when the local transition is safe |

Provider updates advance through the existing transition action rather than writing status fields directly. This preserves order synchronization, stock restoration rules, histories, and idempotency.

# Staging and seller UAT runbook

Staging must use PHP 8.3+ and MySQL 8+ or MariaDB 10.6+. Use a separate database and non-production credentials. Set `APP_ENV=staging`, `APP_DEBUG=false`, a unique `APP_KEY`, HTTPS `APP_URL`, and `SESSION_SECURE_COOKIE=true`.

## Deploy

1. Point the domain document root to `public/`.
2. Run `composer install --no-dev --optimize-autoloader`.
3. Build assets locally or run `npm ci` and `npm run build` on the server.
4. Configure `.env`, then run `php artisan migrate --force`, `php artisan db:seed --class=RolePermissionSeeder --force`, and `php artisan optimize`.
5. Create a unique administrator password and remove demo records before inviting sellers.

## Phone and tablet acceptance session

Test a narrow phone and a tablet in portrait and landscape. Have a real seller complete these tasks without coaching:

- sign in, find/create a customer, and enter a multi-item order;
- select variants, discounts, delivery fee, source, USD/KHR, and an optional payment;
- amend, confirm, pack, ship, and complete an order;
- print or save the bilingual receipt;
- record partial and full payments;
- cancel a confirmed order and verify stock restoration;
- complete a return/refund and review the activity timeline;
- find a low-stock item and adjust stock;
- as an administrator, create and deactivate a staff account.
- create a shipment, print its Khmer/English label, progress pickup through delivery, and reconcile partial/full COD;
- fail and retry a delivery, then verify a returned shipment cancels the order and restores stock once;
- receive a signed Messenger test message, reject an invalid signature, and verify a repeated webhook creates no duplicate message;
- configure a Telegram bot, receive a signed private message, reject an invalid secret, and verify a repeated `update_id` creates no duplicate;
- review a suggested customer match without auto-linking it, then explicitly link or create the customer;
- convert the conversation to a draft order, verify catalog prices are server-resolved, and verify stock is unchanged;
- review pending and failed jobs in Operations and safely retry a controlled test failure;

Record device/browser, task completion, confusing labels, layout overflow, Khmer rendering, address format, currency expectations, and receipt printer/paper size. Acceptance requires no blocked task, no negative stock, correct totals, and no horizontal scrolling in normal forms.

## MySQL concurrency check

SQLite tests verify idempotence, but release acceptance must also exercise target-database row locks:

1. Create one new order for a variant with stock 10 and quantity 3.
2. Open the same order in two authenticated browser sessions.
3. Submit **Confirm** as close together as possible from both sessions.
4. Verify the order is confirmed, stock is 7, and exactly one deduction movement exists for that order/item.
5. Repeat cancellation from two sessions; verify stock returns to 10 and exactly one restoration movement exists.

The second request may succeed idempotently or receive a validation response; it must never create another movement. Preserve application and database logs with the UAT record.

## Integration sign-off

With issued sandbox credentials, create and sync an L192 shipment, validate the tracking mapping, test a provider failure/retry, and replay the same signed webhook twice. For Meta, complete verification, receive one real Page message, repeat its payload, and verify only one encrypted event/message is stored. For Telegram, configure the real staging bot, receive a private message, replay its `update_id`, and confirm the secret header plus encryption/idempotency controls. Confirm queue latency returns to zero and no unexpected failed job remains. Redact tokens, signatures, phone numbers, and addresses from retained evidence.

## Release evidence

Do not mark staging, concurrency, or seller UAT complete from local tests. Store the deployment date/commit, tester names, devices, findings, screenshots, database assertions, and sign-off in the release record.

Copy this matrix into the release record and attach evidence for every completed row:

| Gate | Owner | Status | Evidence |
| --- | --- | --- | --- |
| cPanel deploy, HTTPS, `/up`, production config |  | Pending |  |
| Fresh MySQL/MariaDB migration and permission seed |  | Pending |  |
| Queue cron/worker drains `integrations,default` |  | Pending |  |
| Concurrent confirm/cancel preserves one stock movement |  | Pending |  |
| L192 sandbox create, sync, failure/retry, duplicate webhook |  | Pending |  |
| Meta verification, real message, invalid/duplicate webhook |  | Pending |  |
| Telegram bot, signed private message, invalid/duplicate webhook |  | Pending |  |
| Phone/tablet seller workflow and Khmer rendering |  | Pending |  |
| Isolated backup restore and record reconciliation |  | Pending |  |

The release is signed off only when every row is complete and the product owner plus technical owner record their names and date. Keep rows **Pending** until they are actually performed in staging.

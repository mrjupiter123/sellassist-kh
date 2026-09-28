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

Record device/browser, task completion, confusing labels, layout overflow, Khmer rendering, address format, currency expectations, and receipt printer/paper size. Acceptance requires no blocked task, no negative stock, correct totals, and no horizontal scrolling in normal forms.

## MySQL concurrency check

SQLite tests verify idempotence, but release acceptance must also exercise target-database row locks:

1. Create one new order for a variant with stock 10 and quantity 3.
2. Open the same order in two authenticated browser sessions.
3. Submit **Confirm** as close together as possible from both sessions.
4. Verify the order is confirmed, stock is 7, and exactly one deduction movement exists for that order/item.
5. Repeat cancellation from two sessions; verify stock returns to 10 and exactly one restoration movement exists.

The second request may succeed idempotently or receive a validation response; it must never create another movement. Preserve application and database logs with the UAT record.

## Release evidence

Do not mark staging, concurrency, or seller UAT complete from local tests. Store the deployment date/commit, tester names, devices, findings, screenshots, database assertions, and sign-off in the release record.

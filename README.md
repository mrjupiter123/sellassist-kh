# SellAssist KH

SellAssist KH is a Laravel order-management application for Cambodian online sellers. The current foundation covers customers, products and variants, stock auditing, orders, payments, controlled pre-confirmation amendments, product returns, refunds, administrator-managed staff accounts, order activity history, bilingual receipts, delivery workflow, COD reconciliation, secure Facebook Messenger and Telegram order intake, assigned inbox workflows, per-user unread tracking, queued staff replies, encrypted reply templates, seller-triggered AI order suggestions, permissions, integration health, and a daily dashboard.

## Requirements

- PHP 8.3 or newer with PDO MySQL, Mbstring, OpenSSL, Tokenizer, XML, Ctype, JSON, BCMath, and Fileinfo
- Composer 2
- MySQL 8+ or MariaDB 10.6+
- Node.js 20+ and npm (for frontend assets)
- A web server whose document root points to `public/`

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
```

On Windows, copy `.env.example` to `.env` instead of using `cp`.

Configure `.env` for MySQL/MariaDB:

```dotenv
APP_NAME="SellAssist KH"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sellassist_kh
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Then initialize the database and run the application:

```bash
php artisan migrate --seed
php artisan serve
```

## Development login

The development seeder creates an administrator account:

- Email: `admin@sellassist.test`
- Password: `password`

These credentials are development-only. Change or remove them before using real data.

## Authentication and authorization

Authentication uses Laravel's session guard. Spatie Laravel Permission supplies the `admin` and `staff` roles. The permission seeder grants every operational permission to administrators and appropriate customer, catalog, order, inventory, return, payment, refund, and delivery permissions to staff. Provider maintenance and COD reconciliation remain administrator permissions. Routes require authentication and the relevant permission; the seeded administrator can access all features.

Administrators can create, edit, activate, and deactivate staff. Inactive users cannot sign in, self-deactivation is blocked, and the final active administrator cannot be demoted or deactivated.

## Architecture

The application is a modular monolith. Domain models and use-case logic live under `app/Domain/<Feature>`. Controllers remain HTTP adapters, Form Requests validate and authorize input, and Blade templates use Bootstrap 5 with small Alpine.js interactions.

Order creation, amendments, status changes, stock movements, returns, payments, and refunds use transactions. `OrderCalculator` owns monetary calculations. `AdjustStock` owns all inventory mutations and audit records. `ChangeOrderStatus` owns transition validation, timestamps, and idempotent confirmation/cancellation handling.

Only `draft` and `new` orders may be amended. Catalog prices are reloaded and totals recalculated server-side, and a paid order cannot be reduced below its recorded payments. Only completed orders accept product returns. Return quantities are capped cumulatively against original order items; sellable items can be restocked through audited return movements. Refunds are immutable records tied to their original payment, cannot exceed its unrefunded balance, and never delete payment history.

Order activities are written inside the same transactions as creation, amendments, status changes, payments, returns, and refunds. Customer contact and address data is snapshotted on each order so historical receipts remain stable. Variant maintenance cannot alter stock; all stock changes continue through the inventory domain.

Delivery providers and shipments live under the `Delivery` domain. Shipment transitions are centralized and audited. Courier pickup moves a packed order to shipped, delivery moves it to completed, and a courier return cancels the shipped order through the existing order action so stock restoration stays idempotent. COD is calculated from the server-side outstanding balance, kept synchronized before delivery, and remittances create linked COD payment records. Printable labels use order snapshots.

The optional L192 adapter creates and tracks USD delivery packages through queued HTTP calls. Provider responses and webhook payloads are encrypted at rest; inbound events require HMAC verification and idempotency. See [Delivery provider integrations](docs/DELIVERY_INTEGRATIONS.md) for setup, supported operations, status mappings, and security constraints.

The Messenger adapter verifies Meta's webhook challenge and `X-Hub-Signature-256`, encrypts payloads, deduplicates retries, and queues ingestion. Customer matches are suggestions only. Staff explicitly converts a linked conversation into a server-priced draft order, which cannot affect inventory until the normal confirmation workflow. See [Facebook Messenger order intake](docs/SOCIAL_INTEGRATIONS.md).

The Telegram adapter validates `X-Telegram-Bot-Api-Secret-Token`, accepts private bot messages, encrypts and deduplicates updates, and uses the same reviewed draft-order boundary. Bot credentials remain environment-only, and `social:telegram:configure` registers the webhook without placing the bot token in the database or callback. See [Telegram order intake](docs/TELEGRAM_INTEGRATION.md).

Authorized staff can manually reply from a social conversation. Replies are encrypted and persisted as pending before a queued provider call, then marked sent or failed with a safe operational error. Controllers never call Meta or Telegram directly. Messenger replies require a valid Page access token and must comply with Meta's messaging window and app-review requirements.

The social inbox supports staff assignment, per-user unread filtering, controlled archive/reopen transitions, and encrypted reusable reply templates. Viewing a conversation marks it read only for the current user; a later inbound message makes it unread again. Templates populate the reviewed reply form but never send automatically.

Authorized sellers may explicitly request an AI order suggestion for an open conversation. The queued extraction sends at most 40 inbound text messages and limited active catalog identifiers to OpenAI with response storage disabled. It does not send prices, costs, stock, payments, or credentials. Returned customer and item fields are encrypted locally, catalog references are revalidated server-side, and the seller must review and submit the existing forms. AI output never links customers or creates, confirms, or mutates orders. See [AI order suggestions](docs/AI_ORDER_EXTRACTION.md).

## Tests and formatting

Tests use PHPUnit with an in-memory SQLite database:

```bash
php artisan test
vendor/bin/pint --test
```

To rebuild the local development database and sample data:

```bash
php artisan migrate:fresh --seed
```

## Queues and scheduler

Delivery provider submissions, tracking synchronization, and webhook processing use the database queue. Run:

```bash
php artisan queue:work --queue=integrations,default --tries=3 --timeout=60
```

No Laravel scheduler entry is currently required. On cPanel without a persistent worker, invoke `queue:work --queue=integrations,default --stop-when-empty` every minute through cron.

Messenger and Telegram webhooks, social replies, and AI order suggestions use the same `integrations` queue. Administrators can monitor pending/failed jobs, recent delivery failures, and delivery/social webhook health under **Operations**.

## Stabilization runbooks

- [Staging deployment, mobile seller UAT, and MySQL concurrency verification](docs/STAGING_UAT.md)
- [Database backup and restore drill](docs/BACKUP_RESTORE.md)

These environment-dependent checks require hosting access and human testers. Local automated tests do not constitute staging, seller-UAT, or backup-restore sign-off.

## cPanel deployment

The complete, saveable first-deployment and update checklist is in [cPanel deployment](docs/CPANEL_DEPLOYMENT.md).

Use PHP 8.3+, point the domain document root to `public/`, configure production `.env` values, and then run:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
npm ci
npm run build
php artisan optimize
```

If Node.js is unavailable on the server, build assets locally and deploy the generated `public/build` directory. Ensure `storage/` and `bootstrap/cache/` are writable. Never run `migrate:fresh` in production.

The MySQL/MariaDB connection explicitly uses InnoDB, and indexed strings default
to 191 characters for compatibility with shared-hosting servers that enforce
older index-length limits. If a migration reports error 1071 (`Specified key was
too long`), deploy the latest `AppServiceProvider` and database configuration,
clear cached configuration, and rerun `php artisan migrate --force`. Do not use
`migrate:fresh` on a database that contains data.

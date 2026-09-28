# SellAssist KH

SellAssist KH is a Laravel order-management application for Cambodian online sellers. The current foundation covers customers, products and variants, stock auditing, orders, payments, controlled pre-confirmation amendments, product returns, refunds, administrator-managed staff accounts, order activity history, bilingual receipts, permissions, and a daily dashboard. Social integrations and automation are intentionally out of scope.

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

Authentication uses Laravel's session guard. Spatie Laravel Permission supplies the `admin` and `staff` roles. The permission seeder grants every operational permission to administrators and appropriate customer, catalog, order, inventory, return, payment, and refund permissions to staff. Routes require authentication and the relevant permission; the seeded administrator can access all features.

Administrators can create, edit, activate, and deactivate staff. Inactive users cannot sign in, self-deactivation is blocked, and the final active administrator cannot be demoted or deactivated.

## Architecture

The application is a modular monolith. Domain models and use-case logic live under `app/Domain/<Feature>`. Controllers remain HTTP adapters, Form Requests validate and authorize input, and Blade templates use Bootstrap 5 with small Alpine.js interactions.

Order creation, amendments, status changes, stock movements, returns, payments, and refunds use transactions. `OrderCalculator` owns monetary calculations. `AdjustStock` owns all inventory mutations and audit records. `ChangeOrderStatus` owns transition validation, timestamps, and idempotent confirmation/cancellation handling.

Only `draft` and `new` orders may be amended. Catalog prices are reloaded and totals recalculated server-side, and a paid order cannot be reduced below its recorded payments. Only completed orders accept product returns. Return quantities are capped cumulatively against original order items; sellable items can be restocked through audited return movements. Refunds are immutable records tied to their original payment, cannot exceed its unrefunded balance, and never delete payment history.

Order activities are written inside the same transactions as creation, amendments, status changes, payments, returns, and refunds. Customer contact and address data is snapshotted on each order so historical receipts remain stable. Variant maintenance cannot alter stock; all stock changes continue through the inventory domain.

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

No current workflow depends on an asynchronous job or scheduled task. The database queue tables remain available for later integrations. If queued work is added, run:

```bash
php artisan queue:work --tries=3
```

No cron-based scheduler entry is currently required.

## Stabilization runbooks

- [Staging deployment, mobile seller UAT, and MySQL concurrency verification](docs/STAGING_UAT.md)
- [Database backup and restore drill](docs/BACKUP_RESTORE.md)

These environment-dependent checks require hosting access and human testers. Local automated tests do not constitute staging, seller-UAT, or backup-restore sign-off.

## cPanel deployment

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


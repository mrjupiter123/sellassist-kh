# SellAssist KH

SellAssist KH is a Laravel order-management application for Cambodian online sellers. Phase 1 provides the dependable operational core: customers, products and variants, stock auditing, orders, payments, permissions, and a daily dashboard. Social integrations and automation are intentionally out of scope.

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

Authentication uses Laravel's session guard. Spatie Laravel Permission supplies the `admin` and `staff` roles. The permission seeder grants every Phase 1 permission to administrators and operational view/create/update permissions to staff. Routes require authentication and the relevant permission; the seeded administrator can access all features.

## Architecture

The application is a modular monolith. Domain models and use-case logic live under `app/Domain/<Feature>`. Controllers remain HTTP adapters, Form Requests validate and authorize input, and Blade templates use Bootstrap 5 with small Alpine.js interactions.

Order creation, status changes, stock movements, and payment recording use transactions. `OrderCalculator` owns monetary calculations. `AdjustStock` owns all inventory mutations and audit records. `ChangeOrderStatus` owns transition validation, timestamps, and idempotent stock deduction/restoration.

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

No Phase 1 workflow depends on an asynchronous job or scheduled task. The database queue tables remain available for later integrations. If queued work is added, run:

```bash
php artisan queue:work --tries=3
```

No cron-based scheduler entry is currently required.

## cPanel deployment

Use PHP 8.3+, point the domain document root to `public/`, configure production `.env` values, and then run:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
php artisan optimize
```

If Node.js is unavailable on the server, build assets locally and deploy the generated `public/build` directory. Ensure `storage/` and `bootstrap/cache/` are writable. Never run `migrate:fresh` in production.


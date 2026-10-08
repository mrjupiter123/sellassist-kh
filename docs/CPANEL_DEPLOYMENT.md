# First cPanel deployment and updates

This checklist assumes the application is stored at `/home/CPANEL_USER/sellassist` and the staging domain document root is `/home/CPANEL_USER/sellassist/public`. Replace placeholders with the values shown in your cPanel account.

## First deployment

1. In **cPanel → Domains**, set the domain or subdomain document root to `sellassist/public`. Do not point it to the project root.
2. Upload or clone the project into `/home/CPANEL_USER/sellassist`. Verify that `artisan`, `composer.json`, `app/`, `public/`, `storage/`, and `vendor/` (after Composer) are in that folder.
3. Select PHP 8.3 or newer using **MultiPHP Manager** if **Select PHP Version** is not present. Required extensions include PDO MySQL, Mbstring, OpenSSL, Tokenizer, XML, Ctype, JSON, BCMath, and Fileinfo.
4. Create a MySQL database and user in **MySQL Databases**, grant the user all privileges, and note cPanel's prefixed database/user names.
5. Copy `.env.example` to `.env`, generate a new application key, and configure production values. Keep `APP_DEBUG=false`.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR_DOMAIN
SESSION_SECURE_COOKIE=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=CPANEL_PREFIX_database
DB_USERNAME=CPANEL_PREFIX_user
DB_PASSWORD=STRONG_DATABASE_PASSWORD

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Optional Telegram intake requires environment-only credentials created through `@BotFather`:

```dotenv
TELEGRAM_BOT_TOKEN=
TELEGRAM_WEBHOOK_SECRET=
```

Optional seller-triggered AI suggestions require a server-side OpenAI project key:

```dotenv
SOCIAL_AI_EXTRACTION_ENABLED=true
OPENAI_API_KEY=
OPENAI_ORDER_EXTRACTION_MODEL=gpt-5.4-mini
SOCIAL_AI_LOW_CONFIDENCE_THRESHOLD=0.65
SOCIAL_AI_PROMPT_VERSION=builtin-v1
```

Keep this disabled until the data-sharing boundary in [AI order suggestions](AI_ORDER_EXTRACTION.md) is accepted for the environment. Never expose the API key in the browser or source control.

6. From cPanel Terminal, run from the project directory:

```bash
cd /home/CPANEL_USER/sellassist
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan storage:link
php artisan optimize
```

After setting Telegram credentials and confirming `APP_URL` uses public HTTPS, register its webhook once:

```bash
php artisan social:telegram:configure
```

Do not run `migrate:fresh` on a database containing data. If the server cannot run Node.js, run `npm ci && npm run build` locally and upload the generated `public/build` folder. Otherwise build it on the server before `php artisan optimize`.

7. Ensure `storage/` and `bootstrap/cache/` are writable. Visit `https://YOUR_DOMAIN/up`; Laravel should return a successful health response. Then sign in and immediately create a unique administrator password/account for the environment.
8. Enable HTTPS in cPanel AutoSSL, then turn on **Force HTTPS Redirect** only after the certificate is valid.

## Database queue on shared hosting

If the plan does not provide Supervisor, add a cPanel cron job every minute:

```cron
* * * * * cd /home/CPANEL_USER/sellassist && /usr/local/bin/php artisan queue:work --queue=integrations,default --stop-when-empty --tries=3 --timeout=60 >> /dev/null 2>&1
```

Use the PHP binary path shown by `which php`; hosts may use a versioned path. Open **Operations** after deployment to confirm jobs do not remain pending and to inspect failures. A persistent worker is preferable where the host supports one.

## Safe application update

Back up first, then deploy code/assets and run:

```bash
cd /home/CPANEL_USER/sellassist
php artisan down --retry=60
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan optimize
php artisan up
```

If frontend source changed, build/upload `public/build` before bringing the application up. Test `/up`, login, order creation, queue processing, and integration health after every update.

## Common checks

- A cPanel 404 usually means the domain document root is not the Laravel `public/` directory or `public/.htaccess` is missing.
- A Laravel 500 should be diagnosed in `storage/logs/laravel.log`; do not enable debug publicly.
- MySQL error 1071 means an old shared-hosting index limit. Deploy the current application provider/configuration, run `php artisan optimize:clear`, then rerun `php artisan migrate --force`.
- After changing `.env`, always run `php artisan optimize:clear` before caching again.
- Backups are not proven until the isolated restore procedure in [Backup and restore](BACKUP_RESTORE.md) succeeds.

# Telegram order intake

SellAssist receives private Telegram Bot messages into the same reviewed Social Inbox used by Messenger. It does not ingest group chats, send automatic replies, download attachments, or bypass the trusted order workflow.

## Create and configure the bot

1. Open the official `@BotFather` account in Telegram.
2. Run `/newbot`, choose its display name and username, and copy the bot token.
3. Generate a webhook secret on the server:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

4. Set these server-only `.env` values:

```dotenv
TELEGRAM_BOT_TOKEN=123456789:replace-with-real-token
TELEGRAM_WEBHOOK_SECRET=replace-with-generated-secret
```

5. From the deployed project directory run:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan social:telegram:configure
php artisan optimize
```

The configure command calls `getMe`, creates or updates the Telegram social channel using the bot's immutable numeric ID, registers the HTTPS callback, limits updates to `message`, supplies the secret token, and displays Telegram's current webhook status. Use `--drop-pending` only when old queued Telegram updates should intentionally be discarded.

The callback contains the channel UUID and looks like:

```text
https://YOUR_DOMAIN/api/social/telegram/webhook/CHANNEL_UUID
```

Never paste the bot token into the callback URL, database, browser, screenshots, or source control. Rotate a disclosed token through `@BotFather`, update `.env`, clear configuration, and rerun the configure command.

## Test

Keep the `integrations` queue worker or cPanel cron active. Open the bot from a personal Telegram account, tap **Start**, and send a new private message. Within the cron interval, the conversation should appear under **Social Inbox** and the Operations page should show the event processed.

Telegram sends the configured secret in `X-Telegram-Bot-Api-Secret-Token`. SellAssist rejects requests without the exact secret, encrypts accepted payloads and message contents, uses the bot/update identifier for idempotency, and safely acknowledges retries. Telegram documents that failed webhook deliveries are retried and that `update_id` supports duplicate handling.

## Review boundary

Staff must explicitly link or create a customer, choose catalog products, and convert the conversation into a draft. Catalog prices and totals are resolved server-side. Draft conversion cannot deduct stock, record payment, confirm the order, or create a shipment.

Official references: [Telegram Bot API](https://core.telegram.org/bots/api), [`setWebhook`](https://core.telegram.org/bots/api#setwebhook), and [BotFather setup](https://core.telegram.org/bots/features#botfather).

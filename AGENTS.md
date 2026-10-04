# SellAssist KH Engineering Guide

This is SellMate KH, branded in the application as SellAssist KH.

## Architecture

- This is a Laravel modular monolith. Business logic belongs under `app/Domain` in focused Actions and Services.
- Controllers must remain thin: accept validated input, invoke an Action or query, and return a response.
- Use dedicated Form Requests for HTTP validation and authorization.
- Use PHP backed enums for controlled business states. Do not scatter magic state strings.
- Use Eloquent directly; do not add repository wrappers without a concrete need.
- Keep Blade presentation logic simple and escape user-provided content.

## Data integrity

- Use database transactions for workflows that affect multiple tables.
- Never update inventory directly from controllers, seeders, or order code. Inventory changes go through the inventory domain.
- Every stock change must create a stock-movement audit record.
- Stock cannot become negative through normal workflows.
- Never trust prices, totals, permissions, user IDs, order statuses, or stock quantities from the frontend.
- Order totals must be calculated server-side through `OrderCalculator`.
- Store order-item product, variant, SKU, and price snapshots.
- Preserve order customer/contact/address snapshots for historical receipts.
- Order activities must be recorded inside the same transaction as the operation they describe.
- Order status changes go through `ChangeOrderStatus` and its centralized transition rules.
- Confirmation stock deduction and cancellation restoration must remain idempotent.
- Payment totals cannot exceed the order total unless an explicit overpayment feature is introduced.
- Only draft and new orders may be amended. Amendments must re-resolve catalog prices and recalculate totals server-side.
- Never delete or mutate historical payments to represent a refund. Refunds go through `RecordRefund` and remain linked to the original payment.
- Refund totals cannot exceed the unrefunded amount of the selected payment.
- Product returns go through `CreateOrderReturn`; cumulative return quantities cannot exceed the original order item quantity.
- Restocked returns must create stock movements through `AdjustStock`. Damaged/non-sellable returns remain auditable without changing stock.
- Public routes should use UUIDs rather than sequential database IDs where practical.
- User lifecycle changes must preserve at least one active administrator; inactive users cannot authenticate.
- Shipment status changes go through `ChangeShipmentStatus`; never update shipment or order delivery states directly.
- Shipment COD is calculated from the server-side outstanding order balance. COD reconciliation goes through `RecordCodRemittance` and creates a linked payment.
- Courier pickup, delivery, and return synchronize order status only through `ChangeOrderStatus`.
- External delivery calls must go through a provider adapter and queued job; never call courier APIs from controllers.
- Provider status updates must pass through `ApplyProviderShipmentStatus` and the existing shipment transition action.
- Webhooks must verify signatures before persistence, use stable idempotency keys, and store sensitive payloads encrypted.
- Keep provider credentials in environment configuration; never store or log tokens.
- Social webhooks must verify Meta signatures or Telegram secret headers before persistence, encrypt payloads, and deduplicate retries.
- Telegram bot tokens and webhook secrets remain environment-only. Telegram configuration goes through `social:telegram:configure`; never expose tokens in routes, views, logs, or database records.
- Social identity matches are suggestions only; never merge or link customers without staff review.
- Social conversations may create reviewed draft orders only. They must never directly confirm orders, deduct stock, record payments, or create shipments.
- Staff social replies must be persisted before dispatch and sent only through the queued social sender. Never call Meta or Telegram APIs from controllers.
- Outbound social message bodies and provider responses remain encrypted at rest; provider tokens must never be included in stored errors.
- Social conversation assignment and archive/reopen changes go through their Social-domain actions; assignees must be active users with inbox access.
- Social unread state is per user and is based on the latest inbound message, never on outbound replies.
- Reply templates are encrypted reusable text only. Selecting a template must never auto-send it or trigger any order workflow.
- AI extraction is an explicit seller action only. Never run it automatically for incoming messages.
- AI requests may include only the approved inbound-message snapshot and limited active catalog identifiers. Use `store: false`; never send prices, costs, inventory, payments, credentials, or unrelated customer history.
- Treat every AI field and catalog reference as untrusted. Revalidate product and variant references server-side, encrypt extracted customer fields, and present results only as editable suggestions.
- AI suggestions must never link customers or create, confirm, amend, pay, ship, or otherwise mutate an order without the existing seller-reviewed workflow.

## Development practices

- Avoid unnecessary dependencies and cPanel-incompatible infrastructure.
- Do not modify unrelated functionality.
- Run tests after every significant change and run Pint before handoff.
- Never expose secrets or commit real credentials. Any documented credentials must be development-only.
- Add indexes based on actual lookup/filter paths, not indiscriminately.
- Prevent N+1 queries with explicit eager loading.
- Use meaningful domain exceptions; do not silently catch failures.
- Keep MySQL/MariaDB as the production target and SQLite compatibility for the test suite.
- Maintain backward-compatible migrations once this application has production data; never edit applied production migrations.

## Scope

The implemented core covers local authentication, roles and permissions, customers, products and variants, inventory movements, orders, controlled amendments, payments, returns, refunds, delivery workflow, COD reconciliation, an L192 delivery adapter, secure delivery webhooks, Facebook Messenger and Telegram inbox-to-draft intake, seller-triggered AI order suggestions, integration health, and the operational dashboard. Do not add automatic AI workflows, payment gateways, additional social/courier APIs, multi-tenancy, subscriptions, WebSockets, or microservices unless a later phase explicitly requests them.

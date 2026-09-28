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

The implemented core covers local authentication, roles and permissions, customers, products and variants, inventory movements, orders, controlled amendments, payments, returns, refunds, and the operational dashboard. Do not add social-network APIs, AI parsing, payment gateways, delivery integrations, multi-tenancy, subscriptions, WebSockets, or microservices unless a later phase explicitly requests them.


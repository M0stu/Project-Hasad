# Hasad technical guide

## The central boundary

**Farmer → Hasad → Factory / recycler.** Both customers interact only with Hasad. The application is a brokerage/consignment workflow, with a private operational layer; it does not create a farmer-to-factory directory or messaging channel.

| Information | Farmer | Factory | Authorized Hasad staff |
|---|---|---|---|
| Farmer identity, phone and farm location | Own details | Hidden | Operational access by role |
| Factory identity, phone and destination | Hidden | Own details | Operational access by role |
| Material, grade, moisture, available quantity and listing price | Own requests and sale value | Available listings and own order | Internal batch/order records |
| Internal inspection report | Hidden | Hidden | Inspection/inventory permissions |
| Payment receipts | Own submitted receipt if a buyer | Own submitted receipt | Payment-review permission |
| Full accounting, settings and exports | Hidden | Hidden | Admin/super admin, with settings/access/audit restricted to super admin |

A supplier account receives a separate `HS-SL-...` sale reference, rather than a buyer's order code. Staff must keep counterparty identities out of customer support replies and any independently supplied documents. Transport and customer changes are coordinated through Hasad; buyer-arranged vehicles collect through Hasad's hub process.

## Code map

| Path | Purpose |
|---|---|
| `htdocs/index.php` | Explicit GET routes, POST dispatch, ownership checks, private receipts and staff exports |
| `htdocs/app/bootstrap.php` | Configuration, security headers, secure sessions, locale and safe exception handling |
| `htdocs/app/helpers.php` | Translation, validation, exact money/quantity conversions, rendering and form helpers |
| `htdocs/app/database.php` | PDO prepared statements, transactions, audit writes and persistent rate limits |
| `htdocs/app/auth.php` | SMS verification, separate staff login, permissions and one-time setup |
| `htdocs/app/domain.php` | Inventory, orders, payment review, journals, settlements, processing and ERP mutations |
| `htdocs/views/` | Public and staff views; HTML is escaped at output boundaries |
| `htdocs/assets/` | Original identity, hero asset, responsive CSS and progressive interaction JavaScript |
| `htdocs/database/schema.sql` | InnoDB/utf8mb4 schema and reference-only initial data |
| `htdocs/config/config.php` | Operator-created private configuration; excluded from the delivered ZIP |
| `htdocs/storage/proofs/` | Nonpublic receipt images, accessed only by an authenticated controller |

Arabic/English content is generated server-side through `t(ar, en)`. The language is stored in the session. Document direction changes between RTL and LTR; layout uses CSS logical properties. Styles include keyboard focus, visible labels, reduced-motion handling and mobile navigation. Forms remain usable without the optional selling-form step animation; quotation changes require JavaScript to show a fresh expected total.

## State transitions

| Record | Valid sequence / decision |
|---|---|
| Supply request | `new → scheduled → inspected`; staff may reject/cancel before inspection |
| Batch | `inspected → published`; unreserved stock may be quarantined and reapproved |
| Buyer order | `quoted → payment_review → paid → dispatching → delivered` |
| Rejected transfer | Payment submission becomes `rejected`; order returns to `quoted` |
| Unpaid cancellation | Staff checks actual account, then order becomes `cancelled` and reservation is released |
| Paid cancellation before dispatch | Reservation is released; order becomes `refund_pending` |
| Refund | Partial transfers leave `refund_pending`; full verified amount changes it to `refunded` |
| Procurement | `draft → approved → paid`; only drafts can be cancelled |
| Shipment | `planned → in_transit → completed`, with cancellation of active shipments |
| Processing output | New batch is held as `inspected`; staff approve it before marketplace publication |

Completing a shipment does not itself fulfill a sales order. An administrator confirms the buyer's receipt from the order workflow, which consumes stock and recognizes payables. Cancelling a trip does not silently cancel a purchase.

Expired unpaid reservations generate review tickets when staff open Overview or Orders. Stock remains reserved until a staff member checks for late transfers and cancels or extends the hold. This works without hosting cron and preserves the manual-cancellation boundary.

## Money, weight and quotations

Amounts are integer **piastres** (`100 = EGP 1`). Quantities are integer **grams** (`1,000 = kg 1`; `1,000,000 = tonne 1`). User inputs accept up to two monetary decimals and three weight decimals, including Arabic digits. Negative values, scientific notation and excessive precision are rejected.

```text
material = rounded(quantity_grams × price_per_tonne / 1,000,000)
transport = 0 when buyer-arranged
transport = max(minimum, rounded(quantity × configured route rate)) when Hasad-arranged
service = rounded(material × basis_points / 10,000) + fixed_fee
total = material + transport + service
```

The backend decomposes large quantity/price multiplication to preserve 64-bit integer arithmetic. JavaScript uses BigInt and the same half-up rounding. Backend validation is authoritative; the client cannot choose the charged price.

For example, 2 tonnes at EGP 1,200/tonne with local Hasad transport at EGP 350/tonne and zero service fee yields:

- Materials: EGP 2,400.
- Transport: EGP 700.
- Service: EGP 0.
- Total: EGP 3,100.

`orders` stores every component, rate and fee snapshot. Later setting changes do not modify this order. If the server's fresh quote differs from the expected total submitted by the buyer, the buyer must review the new quote before reserving.

A single request supports 1 kg to 1,000 tonnes. Order values are capped at EGP 1 billion to keep numeric boundaries explicit; larger arrangements go through Hasad staff.

## Consignment and concurrency

Hasad does not book agricultural materials as assets purchased from the farmer. Each batch has an internal seller owner. Physical custody and commercial ownership are separate concepts.

- `quantity_g` is physically present stock.
- `reserved_g` is held for outstanding orders.
- Available stock is their difference.
- Batch locks (`SELECT ... FOR UPDATE`) serialize competing reservations and adjustments.
- SQL constraints enforce nonnegative balances and prevent reservations exceeding stock.
- Reservation, delivery, cancellation, inspection, processing and manual corrections create stock-movement records.
- A processing job can use only available input, requires owner authorization, records output/loss and preserves seller ownership of the output.

Two buyers requesting 6 tonnes each from 9.5 available tonnes are tested concurrently. Exactly one can reserve the quantity.

## Brokerage accounting

The base chart contains bank/online transfers, held buyer funds, seller payables, transport payables, operating-vendor payables, capital, fee income and operating expenses.

| Trigger | Debit | Credit |
|---|---|---|
| Staff confirms actual incoming transfer | Bank — full total | Held buyer funds — full total |
| Buyer receipt of material is approved | Held buyer funds — full total | Seller payable — material; transport payable — shipping; fee income — service fee |
| Verified seller payout | Seller payable | Bank |
| Verified carrier payout | Transport payable | Bank |
| Verified refund before fulfillment | Held buyer funds | Bank |
| Operating purchase invoice approved | Operating expense | Operating vendor payable |
| Operating vendor paid | Operating vendor payable | Bank |
| Online-paid general/project expense | Operating expense | Bank |

Material value and pass-through transport are not Hasad fee revenue. The receiving-account and settlement agreements must match the actual business arrangements before live collection begins. Payments and payouts are recorded after human verification, not initiated by this application.

Every automated journal is balanced, posted within the same database transaction as its business-state change, and protected by a source/event uniqueness key. A separate transfer register prevents the same bank/transfer reference from being reused across payment, settlement, expense and procurement workflows.

The printable customer document is an operational order/payment statement. This release does not integrate with a tax-authority invoice system.

## Authentication and roles

Customers provide name, Egyptian mobile number and their primary use of the service. They receive a six-digit SMS code. Verification is provider-backed in production, limited to five attempts and ten minutes, with persistent per-phone and per-IP sending limits. Successful verification rotates the session identifier and binds matching guest requests/verified seller records to the phone owner. There is no customer email/password authentication.

Staff use separate email/password credentials. Passwords are hashed with PHP's `password_hash`, login attempts are rate-limited, sessions rotate on login, and every sensitive mutation checks the staff permission server-side. The initial super admin requires a unique setup token; setup locks after creation.

| Role | Access |
|---|---|
| Support | Overview, seller intake scheduling, logistics, own tasks and support messages |
| Admin | Operational modules, inspection, inventory, orders, payments, finance, projects, attendance and reports |
| Super admin | All admin functions plus configuration, payment destinations, staff access and audit |

Support cannot reject/cancel supply requests, confirm money, change inventory, export reports or alter fees/destinations. The last active super admin and the current operator cannot be disabled through the access form.

## Data safety and operations

- Prepared statements and allowlisted routes/actions prevent SQL names being supplied by customers.
- CSRF tokens protect POST actions; all financial transitions also validate state under locks.
- Outputs are escaped. Internal free-text inspection reports are not rendered on customer pages.
- Receipts use random non-executable filenames; only JPG/PNG under 1.5 MB are accepted, with MIME and image-dimension checks. Staff access and owning-order access are checked by the receipt controller.
- Staff reports neutralize spreadsheet formulas and limit data exports to 5,000 records.
- Money, private-account and staff pages use no-store caching.
- Security headers include content restrictions, frame denial, MIME protection and HTTPS-only session cookies in production.
- Technical database errors are logged by code, without revealing credentials or SQL details in the customer response.
- Audit records are exposed only to the super admin. No application edit/delete interface is supplied for posted journal entries or audit records. They are not cryptographically tamper-proof against a database administrator.

Take full phpMyAdmin backups and protect configuration/receipt backups. Test restore on a separate database. Configure external uptime checks for the browser-facing site if your chosen monitor supports InfinityFree's browser challenge; this release does not require background daemons or assume webhook access.

## Foundations for later integrations

Automated payments/payouts, an SMS-provider alternative, certified laboratory data, payroll, tax-system invoicing, advanced planning and routing can extend the existing domain boundaries. Preserve the Hasad-only contact model, state transitions, immutable quote snapshots and balanced accounting when adding them.

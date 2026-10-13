# Hasad — حصاد

**Farmer → Hasad (private coordination layer) → Factory / recycler.**

Hasad is the only customer contact point. The marketplace does not reveal suppliers; farmers do not see factory identities. Hasad staff coordinate inspection, consignment custody, payment review, logistics and settlements internally.

This package contains a PHP/MySQL business-pilot application, an original visual identity, a bilingual interactive visual preview, installation instructions, a schema and automated tests. It is intended for your InfinityFree domain. It has not been uploaded to your hosting account.

## Start here

1. Open `preview/Hasad-Preview.html` to review the design and the ERP screens in Arabic or English. The preview uses clearly identified training data and does not submit requests or send SMS.
2. Follow `docs/INSTALL-INFINITYFREE.md` to install the real application.
3. Read `docs/TECHNICAL-GUIDE.md` for the workflow, database model and accounting examples.
4. Use `docs/LAUNCH.md` to enter the real operator, hub, payment and SMS settings and perform the live acceptance steps.

Only upload the **contents of `htdocs/`** to your domain's `htdocs` directory. Keep `tests/`, `tools/`, `docs/`, the preview and ZIP files off the public web root.

## Included application

| Area | Implemented behavior |
|---|---|
| Website | Arabic default, English switch, responsive layout, original logo and harvest image, 30-second animated explainer, reduced-motion support |
| Farmer intake | Two-step form without registration, kg/tonnes, farm pickup or hub drop-off, contact verification and status tracking |
| Customer identity | Phone + SMS code, no customer email/password; separate staff email/password login |
| CRM | Seller, buyer, carrier and operating-vendor records; identifiers stay inside the staff workspace |
| Inspection | Actual weight, A/B/C physical grade, recorded moisture, internal report, identity and seller authorization confirmations |
| Consignment stock | Seller ownership, batch traceability, available/reserved balances, movements, quarantine, publication and audited adjustments |
| Sales | Server-calculated quotations, stock reservation, immutable price snapshot, delivery stages and printable payment/order statement |
| Payments | InstaPay and mobile-wallet transfer instructions, private optional receipts, actual-account staff review, globally unique transfer references |
| Finance | Chart of accounts, balanced double-entry journals, bank balance, held buyer funds, seller/transport balances, partial settlements and refunds |
| Logistics | Farm collections and paid factory deliveries, driver/vehicle assignment and shipment stages |
| Procurement | Operating purchases, vendor liabilities, invoice approval and recorded online payments |
| Partner processing | Completed input/output conversion, loss tracking, seller authorization and preserved output ownership |
| Projects | Budgets, paid expenses, target dates and task progress |
| Team | Staff roles, own-password changes, access controls and daily work-time records |
| Tasks and support | Task board, ownership restrictions for support, customer support conversations and administrative cancellation handling |
| Reporting | Delivered quantities, fee revenue, operating result, processing yield, CSV exports and audit trail |

This covers the requested ERP foundations. Specialized extensions such as payroll, tax-system integration, routing optimization, automated payout APIs and certified feed testing are separate integrations.

## Pilot defaults

- One configurable pilot hub in Sharkia. Its address is intentionally blank until you enter a real location.
- Rice straw, wheat straw, corn stalks, palm residues and sugarcane bagasse. Administrators can add processed products.
- Weights entered in kg or tonnes and stored as integer grams.
- Service fee: **0% + EGP 0** initially, editable by the super admin for new orders.
- Proposed Hasad transport: EGP 350/tonne inside the hub governorate, EGP 650/tonne elsewhere, minimum EGP 500. These are starting assumptions to validate and edit, not researched market rates.
- Buyer-arranged transport: EGP 0 charged by Hasad; coordination and collection remain through Hasad.
- Farm pickup threshold: 1,000 kg; smaller lots use hub drop-off or staff-led aggregation.
- Reservation review deadline: 48 hours. An expired hold is queued for administrative review; stock is not automatically freed before staff check for late transfers.
- No transaction samples, default administrator passwords or real payment destinations in the production schema.
- Fawry is labelled coming soon and cannot be selected by the payment backend.

## What needs your live accounts

Create the hosting database, configure the domain and HTTPS, connect an approved SMS service (the supplied adapter uses Twilio Verify), and enter your own Hasad receiving account/wallet and actual business/hub details. Live SMS delivery and banking receipt reconciliation cannot be verified without those accounts.

Payment confirmation is **manual verification of online transfers**. No automated bank confirmation or automatic money movement is represented as implemented. InfinityFree free hosting blocks incoming provider webhooks; the supplied design uses browser requests and outbound SMS API calls. See the official references in the installation guide.

## Runtime

PHP 8.3+; MySQL 8.0 or MariaDB 10.6+; PDO MySQL, cURL, mbstring, fileinfo and sessions. Apache `.htaccess` support. No Composer, Node.js, shell access, queue worker, cron or URL rewriting is required on hosting.

Tests use PHP CLI and a dedicated `_test` database; the frontend arithmetic test uses Node.js. See `tests/README.md`. Test files are for your development machine only.

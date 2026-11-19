# HASSAD defense demo guide (English)

This guide drives the Arabic-first training environment. Use only synthetic data. Persona identifiers and passwords are supplied by the presenter through environment/configuration; they are never stored in this repository.

## Preparation

1. Apply `htdocs/database/migrations/20261119_001_defense_demo.sql` to an upgraded database, or import the fresh `schema.sql`.
2. Configure a dedicated demo database and set `HASAD_APP_ENV=demo`, `HASAD_DEMO_MODE=1`, `HASAD_DEMO_ALLOW_RESET=yes`, `HASAD_DEMO_SUPPLIER_PHONE`, `HASAD_DEMO_SUPPLIER_PASSWORD`, `HASAD_DEMO_BUYER_PHONE`, `HASAD_DEMO_BUYER_PASSWORD`, `HASAD_DEMO_ADMIN_EMAIL`, and `HASAD_DEMO_ADMIN_PASSWORD` outside version control.
3. Run `php tools/demo_data.php seed`. The command is idempotent and refuses non-demo environments.
4. Keep the Arabic interface selected. Open Supplier and Buyer in separate private browser sessions, and Admin in a third session.

## Scenario-to-slide map

| Slide | Persona and live action | Proof point |
|---|---|---|
| 3 | Supplier: sign in with the configured training credential and open **My account**. | Arabic-first access and strict Hasad-mediated privacy boundary. |
| 4 | Supplier: submit a rice-straw supply request and select pickup or hub drop-off. | Structured intake with category, quantity, governorate and traceable status. |
| 5 | Admin: schedule and inspect the request; choose preparation form, measured weight, grade and moisture. | Predictive-plan input is based on controlled inspection data. |
| 8 | Buyer: open the marketplace; multi-select two governorates, two material categories and a preparation form. | CR-001 combined multi-select filtering across exactly four approved categories. |
| 9 | Buyer: review a batch, quantity and server-calculated quotation; place a reservation. | Transactional reservation and no-oversell invariant. |
| 11 | Admin: open Consignment Stock, edit an unreserved listing, then show the audit trail. | CR-007 optimistic lock, before/after audit, republication gate and preserved quantity/reservation. |
| 12 | Admin: open **Executive analytics**. Explain actual, committed, variance and 30-day estimate-at-completion logic. | CR-004 separate executive decision dashboard using predictive/PMP language. |
| 14 | Admin: open Reports and export the stock workbook. | CR-010 Excel-compatible SpreadsheetML `.xls`; formula-leading text is neutralized. |

## Reset

Run `php tools/demo_data.php reset`. Reset is destructive only inside a configuration explicitly marked as demo and requires `HASAD_DEMO_ALLOW_RESET=yes`. Reseed before the next presentation.

## Presenter cautions

- Never enter real supplier, buyer, payment or contact data.
- Do not claim the rule-based forecast is AI or a commercial guarantee.
- Do not reveal one customer persona's identity in the other persona's screen.
- Team, sponsor, schedule and budget statements belong only in approved controlled presentation material; this guide intentionally introduces none.

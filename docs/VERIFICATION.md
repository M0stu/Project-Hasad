# Development verification

Date: 12 October 2026.

## Passed

- PHP syntax validation across the complete source, views and development scripts.
- JavaScript syntax validation for the application and standalone preview router.
- JavaScript arithmetic checks covering Arabic digits, exact fractional weights, transport minimums, buyer-arranged shipping, fee rounding and input precision.
- **61 automated workflow/security assertions** against PHP 8.3.6 and MariaDB 10.11.14, using the actual InnoDB schema.
- **62 rendered Arabic/English public and ERP pages** checked for document structure, duplicate element IDs, nested forms, CSRF fields and unexpected runtime/test text.
- Self-contained visual-preview packaging with embedded branding/photo assets and guarded preview-only form submissions.

The workflow suite includes exact quotations, oversized arithmetic, stock reservations, immutable fee snapshots, insufficient-stock rejection, client-total tampering, transfer amount mismatch, payment-review permissions, duplicate receipt approval, delivery sequencing, seller/carrier accounting, partial payouts, overpayment rejection, global transfer-reference uniqueness, paid cancellations, partial/full refunds, unpaid cancellations, self-purchase prevention, order ownership, partner-processing loss/ownership, balanced/unbalanced journals, role permissions, CSRF, local-OTP restrictions, wrong/used OTP behavior, staff credential checks, persisted rate limits and CSV formula safety.

The confidentiality checks assert that buyer pages contain no seller name, phone, farm address or internal inspection report; supplier accounts contain no factory name, phone, destination or organization; and marketplace listings omit supplier identity. Concurrent tests launch two independent buyers requesting 6 tonnes from 9.5 available tonnes and verify that exactly one succeeds.

## Verification boundaries

- No browser-based visual, keyboard, animation or mobile-device QA was available in the execution environment. Responsive styles, interaction code and rendered HTML were checked; complete the device steps in `LAUNCH.md`.
- Twilio endpoints follow the official API, but live SMS delivery was not tested without your provider credentials and phone numbers.
- The actual InfinityFree database, domain, SSL configuration and folder protections have not been exercised on your account.
- Optional receipt-upload handling and HTTP security headers require a live-host smoke check; command-line template checks do not replace HTTP testing.
- No real funds were transferred. The application records manual online-transfer confirmation and settlements; it does not connect to a bank balance API or automatically move money.
- This is a bounded business-pilot ERP foundation. No high-volume load certification, external security audit, certified feed testing, payroll or tax-system integration is claimed.

The production import is reference-only, without fixture customers, stock, transactions, default passwords or development OTPs. The standalone preview explicitly displays training-data notices and does not submit forms to a backend.

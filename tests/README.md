# Development tests

Run these on a development machine with PHP 8.3+, PDO MySQL, cURL, mbstring/fileinfo and a local MySQL/MariaDB server. Tests are not an installation step on InfinityFree.

Create a dedicated database ending in `_test`, e.g. `hasad_test`. The workflow suite clears that database and imports the production schema. It explicitly refuses other database names. Never point it at your live database.

Set these environment variables when different from the defaults:

```text
HASAD_TEST_HOST=127.0.0.1
HASAD_TEST_PORT=3306
HASAD_TEST_DB=hasad_test
HASAD_TEST_USER=root
HASAD_TEST_PASSWORD=your-local-test-password
```

Then run:

```bash
php tests/workflows.php
node tests/quote.test.cjs
```

The suite covers exact arithmetic, order/stock transactions, transfer review, accounting, partial settlements and refunds, concurrency, permissions, local-OTP restrictions, persisted OTP failures, rate limits, CSV safety, customer privacy and template rendering. It uses child PHP processes for render checks and two competing reservation attempts.

`test_boot.php` explicitly configures a local test environment and temporary session storage. No default production account, OTP or database credentials are created by application installation. The fixture credentials appear only inside this development suite.

Keep this entire directory outside the public web root. No test is designed to call a live SMS provider or move money.

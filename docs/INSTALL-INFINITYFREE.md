# Install Hasad on InfinityFree

## 1. Prepare the domain and HTTPS

Create your InfinityFree hosting account and add your domain. Enable the SSL certificate for that domain and make sure `https://YOUR-DOMAIN/` opens correctly. The production application requires HTTPS and secure session cookies.

Use the PHP version supplied by the host, with PHP 8.3 or newer. InfinityFree currently advertises PHP 8.4, MySQL 8.0 / MariaDB 11.4, SSL and `.htaccess` support: [official hosting features](https://www.infinityfree.com/).

## 2. Create the MySQL database

In the hosting control panel, open **MySQL Databases** and create a database, for example a database whose final name ends in `_hasad`. Copy these four values from the panel:

- MySQL hostname, usually a `sql...` hostname; do not use `localhost` on InfinityFree.
- Database name including its hosting prefix.
- Database username including its hosting prefix.
- Database password.

Open phpMyAdmin for this database. Select **Import** and import `htdocs/database/schema.sql`. The schema does not try to create a database or change server-wide settings. All tables use InnoDB and utf8mb4.

The schema creates reference materials, accounts and starting settings. It contains no real customers, stock, transactions or login passwords.

## 3. Upload the application

Upload the contents of the package's `htdocs/` directory into the domain's `htdocs/` directory using File Manager or FTP. For a separate domain directory, use the `htdocs` directory belonging to that domain.

The final root should contain `index.php`, `.htaccess`, and the `app`, `views`, `assets`, `config`, `database` and `storage` folders.

Upload the `.htaccess` files too, including those inside private folders. They protect configuration, internal code, SQL and receipt files. Do not upload the outer ZIP, `docs`, `tests`, `tools` or the visual-preview folder.

No `public/` rewrite, virtual-host configuration or Node build step is necessary. Application routes use `index.php?page=...` to work with shared hosting.

## 4. Configure the private PHP file

Copy `config/config.example.php` to `config/config.php`, then edit the copy in your local editor or File Manager. Keep its opening PHP code and direct-access guard.

Set:

- `app_env` to `production`.
- `app_url` to the final HTTPS URL, without a trailing slash. If installed in a subdirectory, include it in the URL.
- `require_https` to `true`.
- The four database fields from step 2, with port `3306` unless the panel specifies another.
- Two **different** unpredictable 64-character hexadecimal values for `app_key` and `setup_token`.

Generate both secrets on your own computer using PHP:

```bash
php tools/generate_keys.php
```

If PHP is not installed locally, use this Windows PowerShell code instead. Run it twice and use different results:

```powershell
$hasadBytes = New-Object byte[] 32
[System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($hasadBytes)
-join ($hasadBytes | ForEach-Object { $_.ToString('x2') })
```

Store credentials locally and in the protected configuration file. Do not paste passwords, tokens or secret configuration into chat or add them to a public repository.

## 5. Create the first super admin

Open:

```text
https://YOUR-DOMAIN/index.php?page=setup
```

Enter your setup token, administrator name, administrator email and a strong password of at least 12 characters. The installer creates the first super admin, starts the staff session and opens Settings.

Setup is locked after the first super admin exists. It cannot be used to replace an existing administrator. No shared or default password is provided. After successful setup, remove or rotate the setup token in `config.php`.

Staff login is separate:

```text
https://YOUR-DOMAIN/index.php?page=admin-login
```

The customer login page uses phone verification and does not accept staff passwords.

## 6. Connect phone verification

The package includes an outbound HTTPS adapter for **Twilio Verify v2**, rather than storing long-term customer passwords.

In your provider account:

1. Create a Verify Service and configure a six-digit SMS verification code.
2. Enable and test the SMS destinations you need, including Egypt, and satisfy the provider's account/sender requirements.
3. Copy the Account SID, Auth Token and Verify Service SID into the `otp` section of `config.php`.
4. Keep `driver` as `twilio` for production.
5. Test with a phone you control and monitor the provider's usage and fraud controls.

The integration calls the official **Verifications** and **VerificationCheck** endpoints. A login succeeds only when the provider returns `approved`. Credentials are not exposed to JavaScript.

Official references:

- [Start a verification](https://www.twilio.com/docs/verify/api/verification)
- [Check a verification](https://www.twilio.com/docs/verify/api/verification-check)
- [Verify documentation](https://www.twilio.com/docs/verify)

`local` codes are available only when `app_env=local`, `driver=local`, and the PHP request originates on `127.0.0.1` or `::1`. They cannot be used for a public demonstration on InfinityFree. For a hosted demonstration, use real SMS verification.

## 7. Enter business, hub and payment details

In **Settings**:

- Enter the actual Hasad operator name and address and a support number.
- Enter the Hasad InstaPay receiving address/account and optional official payment link.
- Enter the Hasad mobile-wallet receiving number if used.
- Review service fees, proposed transport rates and pickup threshold.
- Edit the initial Sharkia hub with a real name/address or deactivate it and add your actual hub.

Acceptable payment links are HTTPS URLs on `ipn.eg`, `instapay.eg` or `www.instapay.eg`. A link only opens the payment destination; it does not verify a transfer. Never enter a farmer's or factory's private account as the platform receiving destination.

Customers pay Hasad, staff confirm receipt, and staff record verified seller/carrier/refund transfers. Only the super admin can change payment destinations or fees.

## 8. Open the pilot and verify it

Review the customer terms and privacy text against the actual business arrangements. Complete the live steps in `docs/LAUNCH.md`. Enable **Open requests and phone sign-in** in Settings when you are ready for a limited pilot.

The launch switch requires operator details, a support number, at least one payment destination and SMS configuration. A database marked as training data cannot be opened in production.

## 9. Check protection and backups

- Opening `/config/config.php` directly must not reveal anything.
- Opening `/database/schema.sql` must be denied.
- Direct access to `/storage/proofs/` must be denied; receipts are served only through the authenticated `proof` route.
- A support account must not see or access Payments, Finance, Reports, Settings, Staff access or Audit.
- Export a complete database backup from phpMyAdmin before launch and regularly during the pilot. Keep private uploaded receipts and the private configuration in your backup plan.

The customer-facing application writes no public database backup files. CSV reports are staff-only, bounded exports and not a full database backup.

## InfinityFree compatibility decision

InfinityFree's official documentation says free hosting requires JavaScript/cookies for inbound requests, blocks incoming API/webhook access, and permits outbound API requests. Therefore this release uses normal browser forms, outbound SMS verification and manual online-transfer reconciliation, without cron or incoming payment webhooks.

[Official browser/API limitations](https://forum.infinityfree.com/t/why-isnt-api-access-working-on-my-website/115198/1)

Automatic payment confirmation will require a supported provider plus a reachable backend/webhook environment, or a provider-supported status-query flow. Moving this PHP/MySQL application to conventional shared hosting or a VPS is possible without changing its basic model.

## If installation fails

| Symptom | Check |
|---|---|
| Platform configuration incomplete | Replace the placeholder `app_key` with a 64-character random hex value; verify `app_url` |
| Setup requests database preparation | Check the exact MySQL hostname/name/user/password and successful SQL import |
| HTTPS required | Open the HTTPS domain, ensure SSL is installed, and keep production HTTPS enabled |
| SMS unavailable | Verify SID/token/service settings, Egyptian destination permission, provider balance and outbound connectivity |
| Quantity cannot be published | Hub must be active with a real address; batch must be inspected, nonempty and unreserved |
| Receipt upload fails | Check `storage/proofs` write permissions; JPG/PNG only, maximum 1.5 MB |
| Transfer reference already recorded | Use the actual unique transaction/statement reference; do not resubmit an already accounted transfer |
| Changes appear to do nothing | Review the displayed error, your staff role, and the record's allowed state transition |

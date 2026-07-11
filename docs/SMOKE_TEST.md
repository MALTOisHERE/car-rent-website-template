# Manual smoke and business workflow tests

Run `php bin/php_syntax_check.php` and `php tests/business_rules.php` first.

## Security and access

1. Confirm unauthenticated access to `/backoffice/` redirects to the central login.
2. Sign in with each role and confirm direct URL access is denied outside its permissions.
3. Submit a POST form without `_csrf`; confirm HTTP 419.
4. Leave a session idle beyond `SESSION_IDLE_TIMEOUT`; confirm reauthentication is required.
5. Fail login repeatedly; confirm the account is temporarily locked without disclosing whether another email exists.
6. Request a password reset for an existing and nonexistent address; confirm identical browser responses.
7. Reset a password; confirm the token cannot be reused and existing sessions are invalidated.

## Primary rental workflow

1. Create a customer with valid age/licence data and add an additional driver/document.
2. Create a vehicle and upload a valid image; reject executable, oversized, or MIME-mismatched files.
3. Create a pending reservation and verify its server-generated price snapshot.
4. Attempt an overlapping reservation for the same vehicle; it must be rejected.
5. Confirm the reservation, generate a contract, mark it signed, and create an amendment.
6. Record advance payment and deposit; create and print an invoice.
7. Record checkout with all six photos; confirm vehicle becomes rented.
8. Record return, compare mileage/fuel/late time, and record damage if applicable.
9. Return or partially retain the deposit with a reason.
10. Confirm dashboard revenue and profitability reflect recorded payments/approved expenses.

## Fleet workflow

1. Schedule maintenance overlapping a requested rental; availability must reject the period.
2. Start maintenance; vehicle must become `maintenance`.
3. Complete maintenance; vehicle must return to `available` if still in maintenance state.
4. Upload an expiring insurance/inspection document and confirm dashboard alert visibility.

## Customer portal

1. Register a fictional customer, update the profile, and upload a document.
2. Search availability by date/time and submit a booking request.
3. Submit modification and cancellation requests.
4. Resolve requests in the back office and verify authorized contract access.


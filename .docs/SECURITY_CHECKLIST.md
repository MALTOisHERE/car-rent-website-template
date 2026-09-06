# Security verification checklist

- [ ] Production database credentials are environment variables and rotated after any exposure.
- [ ] `APP_ENV=production`, `display_errors=Off`, and a protected `error_log` are configured.
- [ ] HTTPS is enforced by the reverse proxy/web server.
- [ ] `/app`, `/database`, `/storage`, SQL dumps, `.env`, and logs return HTTP 403/404.
- [ ] Uploaded content is stored outside executable/public delivery paths.
- [ ] All state changes use POST and a valid CSRF token.
- [ ] All protected routes call `requirePermission()` and agency scoping.
- [ ] Login responses do not enumerate users; lockout behavior is tested.
- [ ] Password-reset tokens are random, hashed at rest, expire, and are single-use.
- [ ] Password reset invalidates previous sessions.
- [ ] PDO emulated prepares remain disabled and all values are parameterized.
- [ ] Financial amounts are `DECIMAL`, calculated server-side, and audited.
- [ ] Reservation confirmation/edit/replacement/extension rechecks conflicts transactionally.
- [ ] Signed contracts are amended/versioned rather than overwritten.
- [ ] Payments and historical records are cancelled/archived, not silently deleted.
- [ ] Database and uploaded-file backups are encrypted and restore-tested.

## Phase 5A finance controls

- [ ] Migration 006 preflight has passed on a production-like copy before finance cutover.
- [ ] Old direct finance mutation routes remain disabled after ledger activity begins.
- [ ] Every finance write supplies an operation-scoped idempotency key.
- [ ] Financial number allocations remain traceable as reserved, consumed, or voided and are never reused.
- [ ] Payments, adjustments, deposit events, cash movements, and issued credit notes are append-only.
- [ ] Excess tender is split between net payment revenue and a dedicated deposit movement.
- [ ] Cash payments, refunds, deposit movements, and approved cash expenses require a locked open register.
- [ ] Expense creators cannot approve their own expense unless the audited owner exception is used with a reason.
- [ ] Unresolved legacy deposits remain read-only until an authorized, reasoned, stale-protected resolution.
- [ ] Finance lookup follows permission and agency authorization; forbidden IDs do not disclose existence.
- [ ] Rental agents cannot read payment history or evidence after submitting an allowed payment.
- [ ] Protected evidence rejects traversal, symlink/junction escape, invalid MIME, missing files, archived records, and cross-agency IDs uniformly.
- [ ] Evidence responses never expose storage paths or original filenames and use private caching plus `nosniff`.
- [ ] Phase 5A true-concurrency, HTTP/security, and cleanup suites pass on the release candidate.
- [ ] Privileged disposable-database migration recovery scenarios pass before production deployment.


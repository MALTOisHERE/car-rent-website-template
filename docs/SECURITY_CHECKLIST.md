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


# Production smoke test checklist

Manual QA script for enterprise deployment sign-off. Run against staging before production release.

**Environment:** staging URL, MySQL, Microsoft SSO enabled, mail configured, queue worker running.

---

## Identity & access

- [ ] SSO login as standard user
- [ ] SSO rejected for wrong domain (staging test account from disallowed domain)
- [ ] Session idle timeout logs user out after `SESSION_IDLE_TIMEOUT` minutes of inactivity

## Approval workflow

- [ ] User in approval-required group: upload → submit → blocked until approved
- [ ] User with approval override `false`: direct send without queue
- [ ] Reviewer approves bundle; uploader notified
- [ ] Reviewer denies bundle; reason required; uploader can edit and resubmit

## Recipient access

- [ ] Invitation + OTP to external email
- [ ] Invitation + OTP to internal email
- [ ] Unverified recipient cannot preview or download

## Share modes

- [ ] Default mode is invitation for new bundles
- [ ] Static link mode works for user in group with `allow_static_links=true`
- [ ] User without allowed group cannot select static link mode
- [ ] Static link preview works with `?auth={preview_token}` on approved bundle
- [ ] Static link UI shows less-secure warning

## Admin & operations

- [ ] Admin: user/group/branding/sharing settings changes persist
- [ ] Admin: audit log viewer shows recent events
- [ ] Admin: audit export CSV for date range
- [ ] Admin: audit export JSON for date range
- [ ] Admin: bundle revoke and delete work

## Background jobs

- [ ] Invitation/approval emails delivered (queue worker processing)
- [ ] `fs:bundle:purge` removes expired bundles
- [ ] `fs:audit:purge` removes logs older than `AUDIT_RETENTION_DAYS`

## Security

- [ ] OAuth callback rate limit returns 429 when exceeded (optional stress test)
- [ ] Download endpoints rate limit under load (optional)
- [ ] Security headers present (`X-Frame-Options`, `X-Content-Type-Options`, CSP)
- [ ] Cookies marked `Secure` and `HttpOnly` over HTTPS

---

**Signed off by:** _______________  
**Date:** _______________  
**Environment:** _______________

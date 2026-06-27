# FileSharing Enterprise Roadmap

Actionable implementation plan for organizational deployment. Work phases in order; check boxes as you complete tickets.

**Last updated:** 2026-06-27  
**Status key:** `[ ]` not started · `[~]` in progress · `[x]` done

---

## Decisions (locked)

| Topic | Decision |
|-------|----------|
| Microsoft tenant | Single tenant only |
| Approval required | Per user **or** per group (not global) |
| Reviewers | Fixed pool (`reviewer` role) |
| Recipients | Internal and external |
| Database | SQLite supported; **MySQL preferred for production** |
| Audit retention | Controlled via `.env` (`AUDIT_RETENTION_DAYS`) |
| Static share links | Optional less-secure mode (keep) |
| Break-glass local admin | No — Azure-only auth |

---

## How to use this document

1. Complete **Phase 0** (infra) before writing app code for SSO/mail.
2. Finish each phase's **Exit criteria** before starting the next phase.
3. Copy ticket IDs (e.g. `P1-1`) into your issue tracker; keep checkboxes in sync.
4. Run `composer test` after each phase that touches app logic.
5. Track phase status in [Progress tracker](#progress-tracker) at the bottom.

---

## Environment variables (reference)

Add these as phases complete. Full list — not all needed on day one.

```env
# ── App ──────────────────────────────────────────
APP_NAME="Secure File Send"
APP_URL=https://files.yourcompany.com
APP_ENV=production
APP_DEBUG=false

# ── Database ─────────────────────────────────────
DB_CONNECTION=mysql              # use sqlite for local dev
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=filesharing
DB_USERNAME=
DB_PASSWORD=
# SQLite: DB_CONNECTION=sqlite, DB_DATABASE=/absolute/path/database.sqlite

# ── Microsoft SSO (single tenant) ────────────────
MICROSOFT_SSO_ENABLED=true
AZURE_CLIENT_ID=
AZURE_CLIENT_SECRET=
AZURE_TENANT_ID=
AZURE_REDIRECT_URI="${APP_URL}/auth/microsoft/callback"
AZURE_ALLOWED_DOMAINS=yourcompany.com

# ── Approval ─────────────────────────────────────
APPROVAL_REQUIRED_DEFAULT=false

# ── Share modes ──────────────────────────────────
DEFAULT_SHARE_MODE=invitation    # invitation | static_link

# ── OTP ──────────────────────────────────────────
OTP_EXPIRY_MINUTES=15
OTP_MAX_ATTEMPTS=5
OTP_RATE_LIMIT_PER_HOUR=5

# ── Audit ────────────────────────────────────────
AUDIT_RETENTION_DAYS=365         # 0 = keep forever
AUDIT_EXPORT_DEFAULT_FORMAT=csv  # csv | json

# ── Mail ─────────────────────────────────────────
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=files@yourcompany.com
MAIL_FROM_NAME="${APP_NAME}"

# ── Session ──────────────────────────────────────
SESSION_LIFETIME=120
SESSION_IDLE_TIMEOUT=60

# ── Upload (existing) ────────────────────────────
UPLOAD_MAX_FILESIZE=1G
UPLOAD_MAX_FILES=100
UPLOAD_PREVENT_DUPLICATES=true
# UPLOAD_LIMIT_IPS= — leave empty when SSO enforced
```

---

## Phase 0 — Planning & infrastructure

**Goal:** Azure, mail, and hosting ready before app work.  
**Depends on:** nothing  
**Blocks:** Phase 3 (SSO), Phase 6 (mail)

### Tickets

- [x] **P0-1** Azure app registration (single tenant)
  - Register app in Entra ID → App registrations
  - Platform: Web; Redirect URI: `{APP_URL}/auth/microsoft/callback`
  - API permissions: `openid`, `profile`, `email`, `User.Read`
  - Create client secret; store in secrets manager
  - **Done when:** Client ID, secret, tenant ID documented for staging/prod

- [x] **P0-2** Outbound mail
  - Configure M365 SMTP relay or Graph API send
  - Verify SPF/DKIM for sending domain
  - Send test to internal + external recipient
  - **Done when:** Test email received both sides

- [x] **P0-3** Environment matrix
  - Dev: SQLite; Staging/Prod: MySQL
  - Document Docker Compose or hosting layout
  - **Done when:** `.env.example` checklist agreed (see env section above)

- [x] **P0-4** Domain & TLS
  - Production URL + HTTPS reverse proxy
  - `APP_URL` matches Azure redirect URI exactly
  - **Done when:** Staging URL loads over HTTPS

### Phase 0 exit criteria

- [x] Azure credentials available for staging
- [x] Test email delivers
- [x] Staging URL live with TLS

---

## Phase 1 — Database foundation

**Goal:** Replace Orbit JSON with SQL; file binaries stay on disk.  
**Depends on:** P0-3  
**Blocks:** all subsequent phases

### Current → target

| Today (Orbit) | Target (SQL) |
|---------------|--------------|
| `storage/content/users/*.json` | `users` table |
| Bundle JSON files | `bundles` table |
| File JSON metadata | `files` table |
| — | `settings` table |

### Tickets

- [x] **P1-1** Core migrations
  - Tables: `users`, `bundles`, `files`, `settings`
  - FKs: `bundles.user_id` → `users.id`, `files.bundle_id` → `bundles.id`
  - Indexes: `bundles.slug`, `bundles.status`, `users.email`
  - **Done when:** `php artisan migrate` succeeds on SQLite and MySQL

- [x] **P1-2** Remove Orbit from models
  - Drop `Orbital` trait from `User`, `Bundle`, `File`
  - Standard Eloquent relationships
  - **Done when:** Models query SQL without Orbit

- [x] **P1-3** Orbit import command
  - `php artisan fs:migrate:orbit`
  - Map `user_username` → `user_id`; preserve slugs, tokens, file paths
  - Idempotent; log skipped/failed records
  - **Done when:** Existing install data imports cleanly

- [x] **P1-4** Dual-DB CI
  - Tests pass on SQLite (default)
  - Optional CI job for MySQL
  - **Done when:** `composer test` green

- [x] **P1-5** Update purge & upload for Eloquent
  - `PurgeFiles`, `UploadController`, `BundleController` use SQL
  - **Done when:** Upload, download, purge work on migrated data

- [x] **P1-6** Bundle status column
  - `status` enum: `draft`, `pending_approval`, `approved`, `denied`, `sent`, `revoked`
  - Default `draft` for new bundles
  - **Done when:** Status persisted; `completeBundle` does not auto-publish (stub until P5)

### Phase 1 exit criteria

- [x] App runs on SQLite (dev) and MySQL (staging)
- [x] Orbit data migrated
- [x] No Orbit dependency in production code path
- [x] All existing tests pass

---

## Phase 2 — Identity, roles, groups & approval policy

**Goal:** Roles and per-user/per-group approval rules before SSO.  
**Depends on:** Phase 1  
**Blocks:** Phase 3, 5

### Approval policy logic

```
requiresApproval(user):
  if user.requires_approval is not null → return that value
  if any group user belongs to has requires_approval = true → return true
  return env('APPROVAL_REQUIRED_DEFAULT', false)
```

User override always wins over group. Any group requiring approval wins over default.

### Tickets

- [x] **P2-1** User roles
  - `roles` table + `role_user` pivot; slugs `user`, `reviewer`, `admin`
  - Users may hold multiple roles; every account retains at least `user`
  - Middleware: `role:admin`, `role:reviewer`
  - **Done when:** Role middleware blocks unauthorized routes

- [x] **P2-2** Groups table
  - `groups`: `id`, `name`, `slug`, `requires_approval`, `allow_static_links`
  - **Done when:** Groups CRUD via migration/seed

- [x] **P2-3** User ↔ group pivot
  - `group_user` many-to-many
  - **Done when:** User can belong to multiple groups

- [x] **P2-4** Per-user approval override
  - `users.requires_approval` nullable boolean
  - `null` = inherit from groups
  - **Done when:** Column + model accessor documented

- [x] **P2-5** `ApprovalPolicy` service
  - `ApprovalPolicy::requiresApproval(User $user): bool`
  - Unit tests for: user override, group flag, env default, combined cases
  - **Done when:** All policy cases tested

- [x] **P2-6** Reviewer pool helper
  - `ReviewerPool::all()` → users with `reviewer` role (includes admin+reviewer)
  - **Done when:** Returns correct users

- [x] **P2-7** User schema for SSO
  - Columns: `email`, `azure_oid`, `name`, `last_login_at`
  - Unique indexes on `email`, `azure_oid`
  - Relax/remove `alpha_num` username-only model
  - **Done when:** Migration applied

- [x] **P2-8** Laravel Auth migration
  - Replace `App\Helpers\Auth` with Laravel guard + `Auth::user()`
  - Update `UploadAccess`, `WebController`, views, `BundleResource`
  - **Done when:** Session auth works; helper deprecated or thin wrapper

### Phase 2 exit criteria

- [x] `ApprovalPolicy` tested with user override + group + default
- [x] Roles enforced by middleware
- [x] Laravel Auth is primary auth mechanism

---

## Phase 3 — Microsoft SSO (single tenant)

**Goal:** Azure-only login; no break-glass local admin.  
**Depends on:** Phase 0 (Azure), Phase 2  
**Blocks:** Phase 4+

### Tickets

- [x] **P3-1** Install Socialite + Azure provider
  - `laravel/socialite`, `socialiteproviders/microsoft-azure`
  - `config/services.php` azure block
  - **Done when:** Packages installed; config reads env

- [x] **P3-2** OAuth routes & controller
  - `GET /auth/microsoft` → redirect
  - `GET /auth/microsoft/callback` → handle token
  - **Done when:** OAuth round-trip works in staging

- [x] **P3-3** JIT user provisioning
  - Create/update user from token: `azure_oid`, `email`, `name`
  - Default role: `user`; default groups: none (admin assigns)
  - **Done when:** First login creates user row

- [x] **P3-4** Tenant & domain lock
  - Reject if token tenant ≠ `AZURE_TENANT_ID`
  - Reject if email domain ∉ `AZURE_ALLOWED_DOMAINS`
  - **Done when:** Wrong tenant/domain gets friendly error

- [x] **P3-5** Login UI
  - "Sign in with Microsoft" button
  - Remove password form when `MICROSOFT_SSO_ENABLED=true`
  - **Done when:** Only SSO login in production

- [x] **P3-6** Disable IP upload bypass when SSO enforced
  - Ignore `UPLOAD_LIMIT_IPS` when SSO enabled
  - **Done when:** Unauthenticated users cannot upload

- [x] **P3-7** Remove routine local password login
  - Disable `POST /login` password flow in prod
  - Keep `fs:user:*` CLI for bootstrap role assignment only
  - **Done when:** No password login in prod config

- [x] **P3-8** SSO feature tests
  - Mock Socialite: success, wrong tenant, JIT create
  - **Done when:** Tests in CI

### Phase 3 exit criteria

- [x] Org user can sign in via Microsoft
- [x] External tenant/domain rejected
- [x] No local password or IP bypass in production

---

## Phase 4 — Admin UI & branding

**Goal:** Operate without CLI; configure org identity from GUI.  
**Depends on:** Phase 3  
**Blocks:** Phase 5–7 (admin views)

**Recommended:** [Filament](https://filamentphp.com) for admin panel.

### Tickets

- [x] **P4-1** Admin panel scaffold
  - `/admin` with `admin` role gate
  - **Done when:** Non-admin gets 403

- [x] **P4-2** Users management
  - List/search; edit `role`, groups, `requires_approval` override
  - **Done when:** Admin can toggle per-user approval from GUI

- [x] **P4-3** Groups management
  - CRUD groups; toggle `requires_approval`, `allow_static_links`
  - Assign members
  - **Done when:** Group approval flag affects `ApprovalPolicy`

- [x] **P4-4** Bundles list (admin)
  - All shares: owner, status, size, file count, created date
  - Filters: status, user, date range
  - **Done when:** Searchable admin bundle table

- [x] **P4-5** Bundle detail (admin)
  - Files, owner, status; actions: revoke, extend expiry, delete
  - **Done when:** Delete removes DB rows + disk files

- [x] **P4-6** Branding settings
  - Keys: app name, logo upload, primary/accent colors, footer text, ToS URL, privacy URL
  - Store in `settings` table
  - **Done when:** Settings persist across requests

- [x] **P4-7** Runtime theme
  - Inject CSS variables + logo URL in `layout.blade.php`
  - **Done when:** Branding visible without redeploy

- [x] **P4-8** Reviewer pool view
  - Read-only list of `role=reviewer` users
  - **Done when:** Ops can see who receives approval queue

### Phase 4 exit criteria

- [x] Admin manages users, groups, approval flags, branding
- [x] Admin sees all bundles

---

## Phase 5 — Approval workflow

**Goal:** Users/groups requiring approval cannot share until fixed-pool reviewer approves.  
**Depends on:** Phase 2 (`ApprovalPolicy`), Phase 4 (admin/reviewer UI)

### Bundle flow

```
Upload (draft)
  → User requires approval?
      YES → Submit → pending_approval → Reviewer approves/denies
      NO  → approved (direct send path)
  → approved → invitations / links (Phase 6 / 8)
```

### Tickets

- [ ] **P5-1** `approval_requests` table
  - `bundle_id`, `requested_by`, `status`, `reviewer_id`, `notes`, timestamps
  - One active request per bundle
  - **Done when:** Migration applied

- [ ] **P5-2** Submit for approval
  - Uploader action on completed bundle
  - Only when `ApprovalPolicy::requiresApproval()` is true
  - Status → `pending_approval`
  - **Done when:** Cannot skip for approval-required users

- [ ] **P5-3** Direct send path
  - When approval not required: status → `approved` without queue
  - **Done when:** Non-approval users send immediately

- [ ] **P5-4** Reviewer queue UI
  - Pending requests list; read-only bundle preview
  - Accessible to `reviewer` and `admin`
  - **Done when:** Reviewer sees pending items

- [ ] **P5-5** Approve action
  - Status → `approved`; record `reviewer_id`, `approved_at`
  - Email uploader (can stub until mail stable)
  - **Done when:** Uploader homepage shows approved

- [ ] **P5-6** Deny action
  - Requires reason; status → `denied`
  - Email uploader with reason
  - **Done when:** Denied bundle not shareable; uploader can edit/resubmit

- [ ] **P5-7** Gate link generation
  - Preview/download links only when `approved` (or static mode in Phase 8)
  - Refactor `completeBundle()` — no auto-publish for approval users
  - **Done when:** Pending bundles have no public links

- [ ] **P5-8** Reviewer notification email
  - Email all users with the `reviewer` role on new pending request (includes users who are also admin)
  - **Done when:** Email received on submit

- [ ] **P5-9** Uploader homepage status
  - Badges: draft, pending, approved, denied, sent, revoked
  - **Done when:** Status matches DB

### Phase 5 exit criteria

- [ ] Approval-required user blocked until reviewer approves
- [ ] Non-approval user sends without queue
- [ ] Per-user override beats group setting (verified manually)
- [ ] Deny requires reason

---

## Phase 6 — Invitations & email OTP

**Goal:** Internal and external recipients verify email before access.  
**Depends on:** Phase 0 (mail), Phase 5 (approved bundles)

### Recipient flow

```
Invitation email (signed link, not raw preview_token)
  → Recipient opens link
  → Request OTP → email 6-digit code
  → Enter OTP → session scoped to bundle + email
  → Preview / download (logged in Phase 7)
```

### Tickets

- [ ] **P6-1** `bundle_recipients` table
  - `bundle_id`, `email`, `verified_at`, `otp_hash`, `otp_expires_at`, `invited_at`
  - Unique `(bundle_id, email)`
  - **Done when:** Migration applied

- [ ] **P6-2** Add recipients UI
  - Uploader adds emails (internal + external) on bundle
  - **Done when:** Recipients saved; validated email format

- [ ] **P6-3** Invitation email
  - Branded template; signed URL
  - Send when bundle is `approved` (or direct-send path complete)
  - **Done when:** Recipient receives invitation

- [ ] **P6-4** OTP request endpoint
  - Generate 6-digit code; hash in DB; env expiry
  - Rate limit per email/hour
  - **Done when:** OTP email sent; brute force limited

- [ ] **P6-5** OTP verify endpoint
  - Correct code → mark `verified_at`; session for bundle
  - Max attempts from env
  - **Done when:** Verified session grants access

- [ ] **P6-6** Gate preview/download on OTP
  - Middleware: invitation mode requires verified recipient session
  - Same flow for internal and external emails
  - **Done when:** Unverified recipient blocked

- [ ] **P6-7** Resend invitation / OTP
  - Uploader or admin can resend; rate limited
  - **Done when:** Resend works without duplicate rows

- [ ] **P6-8** Feature tests
  - invite → OTP → preview → download (internal + external email)
  - **Done when:** Tests in CI

### Phase 6 exit criteria

- [ ] Internal and external recipients complete OTP before access
- [ ] Static raw token not sent in invitation email

---

## Phase 7 — Audit logging & export

**Goal:** Full access trail; retention from env.  
**Depends on:** Phase 1; instrument as Phases 5–6 ship

### Events to log

| Event | When |
|-------|------|
| `bundle.created` | New bundle |
| `bundle.submitted_for_approval` | Submit to queue |
| `bundle.approved` / `bundle.denied` | Reviewer action |
| `invitation.sent` | Email dispatched |
| `otp.requested` / `otp.verified` / `otp.failed` | OTP flow |
| `bundle.previewed` | Preview page view |
| `file.downloaded` | Single file |
| `bundle.zip_downloaded` | ZIP download |
| `access.denied` | Auth/OTP failure |
| `admin.bundle_revoked` | Admin action |
| `sso.login` / `sso.rejected` | SSO success/failure |

### Tickets

- [ ] **P7-1** `audit_logs` migration
  - Append-only: `event_type`, `bundle_id`, `file_id`, `actor_type`, `actor_id`, `recipient_email`, `ip`, `user_agent`, `metadata` JSON, `created_at`
  - **Done when:** No update/delete from app code

- [ ] **P7-2** `Audit` service
  - `Audit::log(string $event, array $context)`
  - **Done when:** Single entry point used everywhere

- [ ] **P7-3** Instrument all events
  - Wire into controllers, middleware, admin actions
  - **Done when:** Each event in table above fires

- [ ] **P7-4** Retention purge command
  - `fs:audit:purge` scheduled; reads `AUDIT_RETENTION_DAYS` (`0` = forever)
  - **Done when:** Old rows purged on schedule

- [ ] **P7-5** Admin audit viewer
  - Filter: date, user, bundle, event type
  - **Done when:** Paginated admin table

- [ ] **P7-6** Export
  - `fs:audit:export --from= --to= --format=csv|json`
  - Admin UI download button
  - **Done when:** Export file generated for date range

- [ ] **P7-7** Failed access logging
  - SSO reject, OTP fail, denied download
  - **Done when:** Failures visible in admin audit view

### Phase 7 exit criteria

- [ ] Every preview/download creates audit row
- [ ] Export works for compliance date range
- [ ] Retention purge respects env

---

## Phase 8 — Optional modes & production hardening

**Goal:** Static links as opt-in; production ready.  
**Depends on:** Phases 5–7

### Tickets

- [ ] **P8-1** Share mode per bundle
  - `share_mode`: `invitation` (default) | `static_link`
  - **Done when:** Mode stored on bundle

- [ ] **P8-2** Static link path (legacy)
  - `/bundle/{slug}/preview?auth={preview_token}` when `share_mode=static_link` and bundle approved
  - UI warning: "Less secure — link alone grants access"
  - **Done when:** Legacy flow works for approved bundles

- [ ] **P8-3** Org default share mode
  - `settings.default_share_mode`
  - **Done when:** New bundles inherit default

- [ ] **P8-4** Restrict static links by group
  - Only groups with `allow_static_links=true` may use static mode
  - **Done when:** Unauthorized users cannot select static link

- [ ] **P8-5** MySQL production guide
  - Add to readme: charset utf8mb4, backups, connection config
  - Note SQLite valid for single-node/small deploys
  - **Done when:** Operator docs complete

- [ ] **P8-6** Queue workers
  - Mail, OTP, notifications on queue (`database` or `redis` driver)
  - Docker/cron documents `queue:work`
  - **Done when:** Emails sent async; no request timeout on send

- [ ] **P8-7** Rate limiting
  - OAuth callback, OTP, download endpoints
  - **Done when:** Limits configurable via env

- [ ] **P8-8** Security headers & session idle timeout
  - Secure cookies, CSP basics, `SESSION_IDLE_TIMEOUT`
  - **Done when:** Documented defaults applied

- [ ] **P8-9** End-to-end smoke test checklist
  - Manual QA script covering full flow (see below)
  - **Done when:** Signed off for production

### E2E smoke test checklist

- [ ] SSO login as standard user
- [ ] SSO rejected for wrong domain (staging test account)
- [ ] User in approval-required group: upload → submit → blocked until approved
- [ ] User with approval override false: direct send without queue
- [ ] Reviewer approves and denies (deny requires reason)
- [ ] Invitation + OTP to external email
- [ ] Invitation + OTP to internal email
- [ ] Static link mode works for allowed group only
- [ ] Admin: user/group/branding changes
- [ ] Admin: audit export CSV/JSON
- [ ] Purge job removes expired bundle
- [ ] Audit retention purge runs

### Phase 8 exit criteria

- [ ] Production deployed on MySQL
- [ ] Default invitation mode; static link opt-in for trusted groups
- [ ] E2E checklist signed off

---

## Cross-cutting tickets

- [ ] **X-1** Update `readme.md` after each phase
- [ ] **X-2** i18n strings for new UI (English first; FR/DE/KR follow)
- [ ] **X-3** Feature flags documented in `.env.example`

---

## Suggested sprint plan

| Sprint | Phases | Deliverable |
|--------|--------|-------------|
| S1 | P0 + P1 | SQL live; data migrated |
| S2 | P2 + P3 | SSO + roles + approval policy |
| S3 | P4 | Admin panel + branding + group/user toggles |
| S4 | P5 | Approval workflow |
| S5 | P6 | Invitations + OTP |
| S6 | P7 + P8 | Audit, static link option, prod hardening |

---

## Progress tracker

Update as you go. GitHub issues: [Enterprise Roadmap milestone](https://github.com/horizonagturf/filesharing/milestone/1).

| Phase | GitHub | Status | Started | Completed | Notes |
|-------|--------|--------|---------|-----------|-------|
| P0 — Infrastructure | [#9](https://github.com/horizonagturf/filesharing/issues/9) | `[ ]` | | | |
| P1 — Database | [#10](https://github.com/horizonagturf/filesharing/issues/10) | `[x]` | 2026-06-27 | 2026-06-27 | Orbit → SQL; `fs:migrate:orbit` |
| P2 — Roles & policy | [#11](https://github.com/horizonagturf/filesharing/issues/11) | `[x]` | 2026-06-27 | 2026-06-27 | Roles, groups, ApprovalPolicy, Laravel Auth |
| P3 — Microsoft SSO | [#13](https://github.com/horizonagturf/filesharing/issues/13) | `[x]` | 2026-06-27 | 2026-06-27 | Socialite Azure; JIT provisioning; tenant/domain lock |
| P4 — Admin & branding | [#12](https://github.com/horizonagturf/filesharing/issues/12) | `[x]` | 2026-06-27 | 2026-06-27 | Filament `/admin`; users, groups, bundles, branding, reviewers |
| P5 — Approval workflow | [#14](https://github.com/horizonagturf/filesharing/issues/14) | `[ ]` | | | |
| P6 — Invitations & OTP | [#16](https://github.com/horizonagturf/filesharing/issues/16) | `[ ]` | | | |
| P7 — Audit logging | [#15](https://github.com/horizonagturf/filesharing/issues/15) | `[ ]` | | | |
| P8 — Hardening | [#17](https://github.com/horizonagturf/filesharing/issues/17) | `[ ]` | | | |

---

## Program definition of done

- [ ] Single-tenant Microsoft login only (no break-glass)
- [ ] MySQL in production; SQLite works in dev
- [ ] Per-user and per-group approval enforced
- [ ] Fixed reviewer pool approves/denies with audit trail
- [ ] Internal + external recipients via invitation + OTP
- [ ] Static links optional per bundle/group
- [ ] Admin: users, groups, shares, branding, audit export
- [ ] `AUDIT_RETENTION_DAYS` controls log lifecycle

# CAS — Centralized Administration System · Planning

## 1. Overview
A single admin console that manages staff identities, roles, org structure and access to internal apps. It is also the SSO provider (OAuth2 / OpenID Connect) for the other portfolio projects.

**Portfolio signal:** enterprise architecture, security, API design, multi-app integration.

## 2. Goals
- One sign-in for all connected apps (Asset Inspection, IT Service Desk, E-commerce Admin, QR Restaurant, Mentoring Portal).
- Central RBAC with per-app roles.
- Configurable multi-level approvals reusable by other apps.
- Full audit trail of every sign-in and change.

**Out of scope (v1):** multi-tenancy, LDAP/AD sync, mobile app, payroll/HR records.

## 3. Modules
| Module | Key features |
|---|---|
| Auth | Login (staff ID/email), MFA (TOTP + email OTP), lockout, password reset/policy, SSO provider |
| Dashboard | KPIs, 14-day sign-in chart, app health, recent activity, pending approvals |
| Users | CRUD, filters, bulk actions, CSV import (queued), invite, lock/unlock, transfer history |
| Roles & permissions | Role CRUD, module × action grid, per-app role mapping |
| Organization | Unit tree (HQ → division → unit), positions & grades, unit heads |
| Connected apps | Register OAuth client, redirect URIs, scopes, rotate secret, enable/disable |
| Approvals | Workflows + steps, request inbox, approve/reject/request info, SLA/overdue |
| Audit log | Filter/search, export CSV, append-only |
| Settings | Password, MFA, session, lockout policies; email templates (EN/MS) |

## 4. Tech stack
| Layer | Choice |
|---|---|
| Backend | Laravel 12, PHP 8.3 |
| Frontend | Inertia + React + Tailwind (Pages/Components shared with the static demo) |
| Database | PostgreSQL |
| Auth / SSO | Laravel Passport (OAuth2 + OIDC), Fortify for login/MFA |
| RBAC | spatie/laravel-permission |
| Org tree | kalnoy/nestedset |
| Queue / cache | Redis + Horizon |
| Import | Laravel Excel (queued chunks) |
| Tests | Pest, Laravel Dusk (critical flows) |
| Quality | Larastan (level 6+), Pint |
| i18n | Bilingual EN/MS UI in v1 (`react-i18next` or Laravel lang files shared via Inertia props) |
| DevOps | Docker (Sail), GitHub Actions, static demo on Cloudflare Pages (no server hosting for now) |

## 5. Architecture
```
            ┌──────────────────────────────┐
            │            CAS               │
            │  Admin UI · OAuth2/OIDC IdP  │
            │  REST API v1 · Approval API  │
            └──────┬───────────┬───────────┘
        OIDC login │           │ webhooks / API
   ┌───────────────┼───────────┼───────────────┐
Asset Inspection  Service Desk  E-commerce  QR Restaurant
```
- Modular folders: `app/Domain/{Identity,Organization,Access,Apps,Approvals,Audit,Settings}` (each: Models, Actions, Policies, Events).
- Business logic in **Action** classes; controllers stay thin.
- Domain **events** → listeners write audit logs and send notifications (queued).
- Policies + `Gate::before` for Super Admin.
- API versioned under `/api/v1`, Passport scopes guard endpoints.

## 6. Database
Full schema: `cas-schema.dbml` (25 tables), targeting **PostgreSQL** (`json` → `jsonb`, native partitioning for `audit_logs`). Groups:
- Organization: `org_units`, `positions`
- Identity: `users`, `user_assignments`, `password_histories`, `user_imports`
- RBAC: spatie tables + `module`/`action` on `permissions`
- Apps: `oauth_clients` (Passport), `applications`, `application_role`, `application_user`
- Security: `sessions`, `login_attempts`, `audit_logs`
- Approvals: `approval_workflows`, `approval_workflow_steps`, `approval_requests`, `approval_actions`
- System: `settings`, `email_templates`, `notifications`

## 7. SSO / API contract
| Endpoint | Purpose |
|---|---|
| `GET /oauth/authorize` | Auth code + PKCE |
| `POST /oauth/token` | Token exchange / refresh |
| `GET /oauth/userinfo` | OIDC claims: `sub`, `staff_id`, `name`, `email`, `org_unit`, `app_role` |
| `GET /.well-known/openid-configuration` | Discovery |
| `POST /oauth/logout` | Back-channel single logout |
| `GET /api/v1/users` | `users.read` scope |
| `GET /api/v1/org-units` | `org.read` scope |
| `POST /api/v1/approval-requests` | Other apps submit requests (`approvals` scope) |
| Webhook `approval.completed` | CAS notifies source app |

## 8. Security checklist
- [ ] Bcrypt/Argon2id, password history (last 5), expiry
- [ ] MFA required for admin roles, recovery codes
- [ ] Rate limiting + lockout (5 attempts / 15 min)
- [ ] Encrypted `mfa_secret`, client secrets hashed
- [ ] PKCE for all clients, short-lived access tokens, refresh rotation
- [ ] CSRF, secure/HttpOnly/SameSite cookies, CSP headers
- [ ] Authorization via policies on every action (no UI-only checks)
- [ ] Audit log append-only (no update/delete routes)
- [ ] Session revoke on role change / deactivation

## 9. Roadmap
| Phase | Scope | Est. |
|---|---|---|
| 0 · Setup | Repo, Docker, CI (Pint, Larastan, Pest), base layout from mockup, demo scaffold (`resources/js/demo`, `vite.demo.config.js`, `build:demo`, Cloudflare Pages project, banner + fake login) | 5 days |
| 1 · Identity | Auth, MFA, lockout, users CRUD, org tree, positions | 2 weeks |
| 2 · Access | Roles, permission grid, policies, seeders | 1 week |
| 3 · Audit | Events → audit logs, login attempts, audit UI + export | 4 days |
| 4 · SSO | Passport + OIDC, app registry, per-app roles, userinfo claims | 2 weeks |
| 5 · Approvals | Workflow engine, inbox, SLA job, API + webhook | 2 weeks |
| 6 · Settings & polish | Policies UI, email templates, dashboard, CSV import | 1 week |
| 7 · Integrate | Connect Asset Inspection via SSO, demo deploy | 1 week |

## 10. Definition of done (per feature)
- Policy + Pest feature tests (happy path + unauthorized)
- Audit event recorded
- Seeder data covers the screen
- Larastan passes, CI green

## 11. Repo & portfolio
- Repo name: `cas-laravel`
- README: problem, GIF demo, architecture diagram, ERD image, stack, setup (`sail up`), demo accounts
- Demo accounts: `superadmin` / `hr.officer` / `dept.head` / `staff` (reset nightly)
- Live demo (Cloudflare Pages static demo) + CI badge + test coverage badge
- GIF of the real SSO flow, recorded locally
- `docs/`: ADRs (why Passport, why nested set, approval engine design)

## 12. Artifacts
- UI mockup: https://claude.ai/artifact/UiZrEsCX8K3DrMjN3fW6xn (9 screens + shared sidebar/top bar)
- Schema: `cas-schema.dbml`

## 13. Decisions (former open questions — all resolved)
1. **Admin UI: Inertia + React** (not Livewire/Vue), so the real app and the static demo share the same React pages.
2. **Database: PostgreSQL.**
3. **Bilingual UI (EN/MS) in v1.**
4. **Hosting: no server for now.** Static demo on Cloudflare Pages, same approach as StockWise but without a duplicate UI project. See §14.

## 14. Static demo (Cloudflare Pages)
**Shared UI**
- All screens live in `resources/js/Pages/` and `resources/js/Components/`.
- Pages are "dumb": data and callbacks arrive via props (`users`, `onSave`, `onDelete`). No direct `router`/`axios` calls inside Page components; wrap those in the Inertia entry.

**Real app** (`resources/js/app.jsx`)
- Normal Inertia setup; Laravel controllers pass props.
- Runs locally with one command (`./vendor/bin/sail up` or `docker compose up`) plus seeders with demo accounts.

**Static demo** (`resources/js/demo/`)
- `main.jsx`: React Router entry rendering the same Pages with dummy data.
- `data/seed.json`: dummy data matching `cas-schema.dbml`.
- `store.js`: `useDemo(key)` hook with fake CRUD persisted in localStorage (prefix `cas:`), try/catch with fallback to seed data. `resetDemo()` clears `cas:*` keys.
- Persistent top banner: "Demo mode: sample data, stored in your browser" with a Reset button.
- Fake login: any credentials → dashboard; MFA step is UI only.
- SSO, email, queues and real auth are not simulated beyond the UI.

**Build**
- `vite.demo.config.js`: root `resources/js/demo`, output `dist-demo/`.
- `package.json`: `"build:demo": "vite build --config vite.demo.config.js"`.
- Cloudflare Pages: build command `npm run build:demo`, output dir `dist-demo`. SPA routing must survive refresh (`_redirects`: `/* /index.html 200`).

**Portfolio**
- README: live demo + repo links, GIF of the real SSO flow (recorded locally), architecture diagram, ERD, local setup, demo accounts, CI + test badges.
- Demo footer: "View source on GitHub".

**Later (not now):** if a server becomes available, a VPS (Singapore region) + Coolify hosting all portfolio apps as subdomains. Keep the code host-agnostic (config via `.env`, Docker-ready).

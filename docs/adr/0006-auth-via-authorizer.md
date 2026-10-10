# ADR 0006: Client sign-in through Authorizer (researched, not decided)

Status: proposed, researched 2026-10-10 against Authorizer 2.4.1. Nothing is built.
Paths below are inside github.com/authorizerdev/authorizer (cloned at tag 2.4.1 / main of 2026-10-06). "Unconfirmed" means I could not verify it from the source.

## Context
The next phase is a client portal. Brand contacts (Marketing Manager gives feedback, Brand Director gives final sign-off) get one Client role with a `client_signoff` flag, and each client belongs to one brand. The PM app keeps roles, brands and permissions in its own database. An identity provider would only prove who the person is (OIDC). Agency staff keep the app's own username and password login.

## 1. What Authorizer is and how it runs
- A self-hosted Go server (`go.mod`, `main.go`), Apache 2.0. One static binary, or the image built by `Dockerfile` (Alpine, non-root, ports 8080 HTTP, 9091 gRPC, 8081 metrics).
- Databases (`internal/storage/provider.go`): Postgres, MySQL, MariaDB, SQL Server, CockroachDB, YugaByte, PlanetScale, libSQL and **SQLite** (pure Go driver `modernc.org/sqlite`, WAL mode, `internal/storage/db/sql/provider.go`). Also Mongo, Cassandra, Scylla, Arango, DynamoDB, Couchbase.
- Redis is optional (README line "If you do not configure a Redis server, sessions will be persisted until the instance is up or not restarted"). Without Redis, a restart signs everyone out of Authorizer (README, section on Redis). Our own PHP session is not affected.
- Config is **command-line flags only** in v2. It does not read `.env` or environment variables (`README.md`, `MIGRATION.md`, `cmd/root.go`). Required: `--admin-secret`, `--client-id`, `--client-secret`, `--url`, database flags, JWT key flags.
- There is an admin dashboard at `/dashboard` (`web/dashboard`, route in `internal/server/http_routes.go`) for users, webhooks, email templates and audit logs. Settings are not editable there.
- Footprint: no official memory figure. `perf/README.md` benchmarks a 2 vCPU / 2 GB container (about 28 logins per second). A Go binary with SQLite for a few dozen users should fit in 256 MB; this is my estimate, unconfirmed.

## 2. Protocols and flows
- OIDC discovery: `/.well-known/openid-configuration` (`internal/http_handlers/openid_config.go`). Endpoints: `/authorize`, `/oauth/token`, `/userinfo`, `/oauth/revoke`, `/oauth/introspect`, `/logout` (advertised as `end_session_endpoint`), JWKS at `/.well-known/jwks.json`.
- Authorization code with PKCE is supported. `code_challenge_methods_supported` is advertised; S256 and plain are accepted unless `--oauth2-1-strict` is set (`authorize.go`). Public clients can use `none` auth plus PKCE. We should use a confidential client (client secret) plus PKCE plus nonce.
- Tokens are JWTs. Signing is RS256 by default in dev; the code supports RS/ES/HS families (`internal/crypto/`). Use RS256 or ES256 so our PHP can verify offline with JWKS. HS256 would not work (no public key).
- Refresh: standard `refresh_token` grant at `/oauth/token`; `offline_access` scope. Refresh lifetime default 30 days (`--refresh-token-expires-in`). Access token lifetime: 30 minutes (code fallback in `internal/token/auth_token.go`; I found no flag for it, unconfirmed).
- Logout: RP-initiated logout with `id_token_hint` and `post_logout_redirect_uri` (`logout.go`). Without the hint it shows a confirm page. Optional back-channel logout via `--backchannel-logout-uri`.
- Claims: `sub`, `email`, `email_verified`, `roles`, `allowed_roles`, names (`auth_token.go`, `openid_config.go`). A custom script can add claims (`--custom-access-token-script`). We do not need roles from it.
- Redirect URLs: exact-match list via `--redirect-uris` for the default client, plus `--allowed-origins` (`cmd/root.go`). Per-client lists exist in the client registry (`authorizer_clients`, CHANGELOG 2.4.0). Unconfirmed: how the dashboard edits them.

## 3. Sign-in methods for clients
| Need | Support | Evidence |
|---|---|---|
| Email + password | Yes, on by default | `--enable-basic-authentication` |
| Magic link | Yes, off by default | `--enable-magic-link-login`, `internal/service/magic_link_login.go` |
| Email OTP | Yes, as an MFA step, not as a standalone login (unconfirmed) | `--disable-email-otp` |
| Google, Microsoft, others | Yes (10 providers) | README, `cmd/root.go` |
| TOTP MFA, passkeys | Yes; `--enforce-mfa` | `cmd/root.go` |
| Email verification | Yes, but it refuses to start without SMTP | `cmd/root.go` line ~522 |
- SMTP is required for verification, magic links, resets and invites: `--smtp-host/-port/-username/-password/-sender-email`. Any transactional relay works.
- Branding: the login app is a bundled React page (`web/app`) with `--organization-name` and `--organization-logo`, plus editable email templates. Colour and layout changes mean rebuilding the web app (`make build-app`), which is a fork cost. Unconfirmed: any theme setting in the dashboard.
- Invite-only: `--enable-signup=false` blocks self sign-up (`signup.go`, `magic_link_login.go`). The admin API `_invite_members` (`internal/graph/schema.graphqls` line 1588, `service/admin_access.go`) creates accounts and emails a magic-link or set-password invite, and does not check the signup flag. So invite-only is possible. Unconfirmed by running it. Also admin `_admin_signup`/user create calls exist in the same schema.
- Social login caveat: with signup off, a Google login for an unknown email should be refused. Unconfirmed; test it before offering social login to clients.

## 4. Hosting
Cannot run on Xneelo shared hosting (long-running process, our own CLAUDE.md constraint). Needs: an always-on host, HTTPS with a custom domain `auth.slash301.com` (DNS in Cloudflare, proxy on is fine), a persistent disk for SQLite, outbound SMTP, 256 to 512 MB RAM.
| Option | Fit | Notes (pricing not verified, check at purchase) |
|---|---|---|
| Small VPS (Hetzner, DigitalOcean, Vultr, Xneelo VPS) | Best | Docker or the bare binary under systemd, Caddy for TLS, SQLite file on local disk, nightly copy off the box. Roughly 5 to 7 USD a month. |
| Fly.io | Good | Small machine plus a volume; one machine only, since SQLite cannot be shared. |
| Railway | Good | Official template exists (README); needs `--trusted-proxies=100.64.0.0/10` (CHANGELOG 2.4.1). Volume or its Postgres. |
| Render | Works with a paid disk | Free tier sleeps and has no disk: not usable. |
| Managed Postgres instead of SQLite | Optional | Better backups, costs more. Not needed for a few hundred users. |
Reverse proxy: set `--trusted-proxies` to list every hop (Cloudflare ranges plus the proxy), or client IPs and lockouts misbehave (CHANGELOG 2.4.1).
SQLite on a volume is supported and is the cheapest path. Single instance only. Back it up by copying the file while stopped, or with `sqlite3 .backup`.

## 5. Integration plan for PHP
- **Library or own code:** write about 200 to 250 lines of our own (one class under `app/Http/`). A vendored library (for example jumbojett/OpenID-Connect-Client) works but drags in a JWT dependency and style that breaks our no-Composer, Go-portable rules (`go-portable-php` skill). Own code needs only `curl`, `openssl_verify`, `random_bytes`.
- Parts: (1) fetch and cache discovery and JWKS in `data/` (1 hour, refetch on unknown `kid`); (2) `/auth/login` creates state, nonce, PKCE verifier in the session and redirects; (3) `/auth/callback` checks state, posts the code to the token endpoint with the verifier, verifies the ID token signature (RS256 with `openssl_verify`, convert JWK n/e to PEM; ES256 needs DER to raw signature conversion, so pick RS256), then checks `iss`, `aud`, `exp`, `nonce`, `email_verified`; (4) lookup, then `session_regenerate_id` and the normal PHP session.
- Mapping: first look up `users.auth_subject = sub`. If none, match the verified email to an existing, active Client user, then store `auth_subject` once (new additive migration). Never create users on callback. Staff accounts are not linkable through this path (Client role only), so a client mailbox cannot become staff. Brand membership stays in our database.
- Revocation is ours: set the PM user inactive and the next sign-in fails, even if Authorizer still knows them.
- Logout: destroy the PHP session, then redirect to `/logout?id_token_hint=...&post_logout_redirect_uri=...` (store the ID token in the session; it is short).
- Staff password login is untouched. Config switch per role: staff password, clients OIDC.
- Tests: a stub OIDC provider in `tests/`, as in the earlier plan.

## 6. Security and maintenance signals
- License Apache 2.0 (`LICENSE`). Governance files present (`GOVERNANCE.md`, `MAINTAINERS.md`); the README mentions a CNCF Sandbox application (`ADOPTERS.md`), which is not an acceptance.
- Cadence: latest commit 2026-10-06; releases 2.4.0 (2026-08-19) and 2.4.1 (2026-09-03); many rc tags (`CHANGELOG.md`, `git ls-remote`). Active, but moves fast. The shallow clone hides commit history, so contributor count is unconfirmed.
- `SECURITY.md`: private reports via GitHub advisories, 72 hour acknowledgement, 30 day fix target for critical or high. The contact is a personal Gmail address with a TODO for a proper mailbox. Small maintainer base, treat as a risk.
- Advisories: 2.4.1 fixes GHSA-93hc-xq3w-xw87 (critical, admin lockout bypass via spoofed IP, affects 2.4.0-rc.16 and later) and GHSA-vq29-8q3c-3hrm (high, machine token treated as user in OpenFGA). Neither touches our flow directly, but we must stay patched and set `--trusted-proxies`. CI runs govulncheck and OpenSSF Scorecard (`.github/workflows`).
- Keep the admin dashboard off the public internet if possible (IP allow-list in Cloudflare), and use a long `--admin-secret`. Keep `--jwt-secret` and a separate `--encryption-key` in backups; losing the encryption key locks out TOTP users.

## 7. Recommendation
| | Own login first (invite links + passwords, later magic links) | Authorizer from day one |
|---|---|---|
| Time to portal | Fast; no new host | Slower: host, SMTP, DNS, OIDC client first |
| Running cost | None | VPS about 5 to 7 USD a month plus upkeep and patching |
| Failure points | One | Two (auth host down means clients cannot sign in) |
| Features | Password reset, invites, throttling to build by hand | MFA, social, magic link, resets included |
| Branding | Fully ours | Limited without forking the web app |
| Risk | We own password storage and reset flows | Young project, one visible maintainer |
| Later switch | Easy if `auth_subject` and a login seam exist | n/a |

Recommended default: **ship the portal on our own login first**, with single-use invite links and a password (or our own emailed magic link), strong hashing, throttling and a per-brand invite flow. Build it behind a small "identity provider" seam and add the nullable `users.auth_subject` column now. Move clients to Authorizer when there are many client contacts, a request for MFA or Google/Microsoft login, or a security questionnaire that demands it. Use Authorizer from day one only if the owner already wants a VPS and MFA for clients at launch.

## 8. Owner decisions still needed
- Own login first, or Authorizer from day one?
- If Authorizer: which host (VPS preferred), who patches it, who owns backups?
- Which client methods: password, magic link, Google or Microsoft, MFA for Brand Directors?
- SMTP provider and sending domain (SPF, DKIM) for `auth.slash301.com` mail.
- Is a limited login page (logo and name only) acceptable, or is full branding a must (forking the web app)?
- Do clients sign in only by invite, with the agency inviting every contact?
- Blocker to clear before building: verify invite-only behaviour and social sign-in with signup disabled on a test instance.

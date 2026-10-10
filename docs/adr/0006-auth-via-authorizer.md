# ADR 0006: Authentication through Authorizer (planned)

Status: proposed (owner request, 2026-10-10). Not built yet; scheduled after the beta gate.

## Context
The owner wants to use Authorizer (github.com/authorizerdev/authorizer, open source, latest release 2.4.1) for sign-in once the app moves past the beta. Today the app has its own username and password login (`app/Http/Handlers/AuthHandlers.php`), shared with the legacy API through one PHP session.

Authorizer is a self-hosted authentication server written in Go. It speaks OAuth 2.0 and OpenID Connect, has its own database, and offers features such as social logins, magic links, email verification and multi-factor authentication (confirm the exact feature set against the release we pick).

Constraint: Authorizer runs as a long-lived server process. Xneelo shared hosting runs PHP per request and cannot keep a Go server running. Authorizer therefore needs its own small host (a VPS or a container platform), with its own database, on a subdomain such as `auth.slash301.com`.

## Decision
1. Run Authorizer as a separate service. The PM app stays on Xneelo.
2. The PM app becomes an OpenID Connect client: authorization code flow with PKCE, a state value and a nonce, tokens checked against Authorizer's published keys. No browser-side token storage: after the callback the app creates its normal PHP session, so Datastar requests, CSRF and the legacy API keep working unchanged.
3. Identity lives in Authorizer; authorisation stays in the PM app. Roles, brands, job assignments and the Policy rules remain in our database. A new additive column `users.auth_subject` (the OIDC `sub`) links an Authorizer account to a PM user. First sign-in links by verified email, once, and only for an existing active PM user; nobody is created automatically.
4. Keep the local password login as a fallback that an admin can switch off in config, so a problem with the auth host never locks the owner out.
5. Logout ends the PM session and redirects to Authorizer's logout.

## Consequences
- Hosting cost and upkeep for one more service and its database; backups for it too.
- The login page gains "Sign in with Slash 301" (Authorizer) and keeps the password form while the fallback is on.
- Demo mode (`data/.demo_mode`) is unaffected and stays a separate owner switch.
- Fits the Go port: the Go app uses the same OIDC flow with a standard library client.
- Work items when scheduled: choose the host, deploy Authorizer and its database, register the PM app as a client (redirect URI `https://projects.slash301.com/slash301pm/auth/callback`), migration for `users.auth_subject`, callback and logout handlers, account-linking screen in `/admin/users`, tests with a stub OIDC provider, and a rollback plan (turn the fallback back on).

## Open questions for the owner
- Which host for Authorizer (for example a small VPS, Railway, Fly.io or Render)?
- Which sign-in methods: email and password, magic link, Google or Microsoft accounts, MFA for admins?
- Should clients (brand contacts) sign in through Authorizer as well, or only agency staff at first?

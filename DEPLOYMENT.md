# EPIC World — Production Deployment (Hostinger)

This document covers deploying EPIC World to Hostinger shared hosting: the
expected directory layout, the deployment procedure, production
configuration, and the backup/rollback plan. It never contains real
credentials — every value below is a placeholder or a command.

## 1. Directory layout

Hostinger (like most shared hosting) serves whatever is in the domain's
public web root — commonly `public_html/` — directly. Laravel's own web
root is the `public/` folder inside the project, **not** the project
root: the project root contains `app/`, `vendor/`, `.env`, and other
files that must never be reachable over HTTP.

Two supported layouts, in order of preference:

**A — project root outside the web root (preferred).** Clone/upload the
whole project to a directory *outside* `public_html/` (e.g. a sibling
folder such as `epicworld/`), then either point the domain's document
root directly at `epicworld/public` (Hostinger's hPanel lets you set a
custom document root per domain), or symlink/copy `public/`'s contents
into `public_html/` and edit `public/index.php`'s two `require`
statements to point at the real `../epicworld/vendor/autoload.php` and
`../epicworld/bootstrap/app.php`. This keeps `app/`, `.env`, `vendor/`,
`storage/`, `tests/`, and `.git/` outside the web root entirely.

**B — project root is `public_html/`'s parent, `public/` is symlinked
in.** If the host only serves `public_html/` and won't accept a custom
document root, upload the project one level above `public_html/` and
replace `public_html/` itself with a symlink to the project's `public/`
directory. Verify Hostinger's shared-hosting plan actually allows
symlinks before relying on this — some restrict it. If it doesn't, layout
A's document-root approach is the fallback.

Either way, verify directly, after deploying, that these all fail
(404 or connection refused), not 200:

```
https://your-domain/.env
https://your-domain/.git/config
https://your-domain/composer.json
https://your-domain/vendor/autoload.php
https://your-domain/storage/logs/laravel.log
```

## 2. Required PHP extensions and tooling

Laravel 13 / PHP 8.4 needs: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`,
`xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl`. Hostinger's hPanel
"PHP Configuration" screen lets you pick the PHP version (select 8.4) and
toggle extensions — confirm each of these is enabled before deploying.
Composer is required; Hostinger's shared hosting plans typically provide
it via SSH (`composer --version`) — if not, run `composer install`
locally/in CI and upload the built `vendor/` directory instead.

## 3. Deployment procedure

### 3.1 Upload / clone the project

Via Git (preferred, if SSH + git are available on the plan) or by
uploading a build archive via Hostinger's File Manager / SFTP.

### 3.2 Configure `.env`

Copy `.env.example` to `.env` on the server and fill in real values —
never commit this file, never copy a local development `.env` over it.
See section 4 below for the full production contract.

### 3.3 Install dependencies

```
composer install --no-dev --optimize-autoloader
```

`--no-dev` excludes `laravel/boost`, `laravel/pail`, `laravel/pao`,
`phpunit`, `mockery`, and other dev-only tooling — none of it is needed
at runtime (see the Milestone 17 dependency audit in the final report).

### 3.4 Build frontend assets

Build locally or in CI and upload `public/build/` — Hostinger shared
hosting typically has no Node.js runtime for `npm run build` to use
server-side.

```
npm ci
npm run build
```

### 3.5 Generate the production key

**Only on the production installation itself**, after `.env` is in
place:

```
php artisan key:generate
```

Never copy a local/development `APP_KEY` into production — it's the key
that encrypts sessions and signed URLs; sharing it across environments
defeats that isolation.

### 3.6 Database

```
php artisan migrate --force
```

`--force` is required because `APP_ENV=production` otherwise refuses to
run migrations without it (a deliberate Laravel safeguard, not
something to bypass casually — see section 6, rollback).

### 3.7 Seed

```
php artisan db:seed --class=EpicWorldSeeder
```

Always safe to run (and re-run) in production: it only
`updateOrCreate()`s the fixed category/topic taxonomy — no fake
articles, no fake engagement, no admin account, no secrets. Confirmed
in the Milestone 17 seeder audit.

`DiscoverySourceSeeder` is a **separate, deliberate choice**: it
activates two real, live public feeds (NASA press releases, Hacker News
front page via hnrss.org) and scheduled discovery will start pulling
from them the moment it runs. Run it only when you're ready for
discovery to actually start:

```
php artisan db:seed --class=DiscoverySourceSeeder
```

No seeder creates a user. Create the first administrator explicitly
(there is no artisan command for this — use `php artisan tinker` once,
interactively, on the production server, never in a script or `.env`):

```
php artisan tinker
>>> \App\Models\User::create(['name' => 'Your Name', 'email' => 'you@example.com', 'password' => bcrypt('a-strong-password-you-choose-here'), 'role' => \App\Models\User::ROLE_ADMIN]);
```

### 3.8 Storage

```
php artisan storage:link
```

Only needed if/when the application starts serving files out of
`storage/app/public` (nothing does yet — `featured_image` is a plain URL
field, and there is no upload endpoint; see the Milestone 17 upload
audit). Safe to run regardless; it's a no-op if nothing uses the link
yet. Confirm the host supports symlinks first (see section 1B) — if not,
this step is deferred until a future milestone that needs it, and the
publicly-linkable subset of `storage/app/public` would need to be copied
instead of symlinked.

### 3.9 Cache (production optimization)

Validated in Milestone 17 — all three commands succeed against this
application's actual route/config/view structure:

```
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Important caveat, confirmed during this milestone's own testing:**
once `config:cache` has run, Laravel stops reading `.env` altogether and
serves the cached array instead — so these commands must be run *after*
`.env` is in its final, correct production state, and re-run after
*any* `.env` change (`php artisan config:cache` again, or
`php artisan optimize:clear` then re-cache). Running the test suite
against cached config produces wrong results (this is exactly why
`composer.json`'s own `test` script runs `php artisan config:clear`
first) — never run these cache commands in an environment where tests
are also run.

### 3.10 Scheduler

Hostinger's cron editor (hPanel → Advanced → Cron Jobs), one entry:

```
* * * * * php artisan schedule:run >> /dev/null 2>&1
```

This single entry drives both `stories:discover` and `editorial:process`
— their own cadence, overlap protection, and batch limits are configured
in `config/discovery.php` / `config/editorial.php` (env-driven), not in
the cron entry itself. Do not add a second cron line for either command
directly.

### 3.11 Permissions

The web server process needs to **write** to exactly:

```
storage/
bootstrap/cache/
```

(and `storage/logs/`, `storage/framework/{cache,sessions,views}`
specifically, which live under `storage/`). Nothing else — the
application, `vendor/`, `public/build/`, and every other directory only
need to be **readable** by the web server. Typical Hostinger/shared-host
permissions:

```
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

(adjust the group to match the PHP-FPM user Hostinger runs as, if
`775`/`664` isn't sufficient — never `777`, which grants write access to
every user on a shared box, not just the web server's own user/group).

### 3.12 Smoke test

After deploying, verify in order:

1. `GET /` returns 200 with real category/tag navigation.
2. `GET /robots.txt` and `GET /sitemap.xml` return 200.
3. `GET /login` returns 200; log in as the administrator created in 3.7.
4. `/admin` dashboard loads with real (zero, on a fresh install) counts.
5. `/admin/feeds` shows the two seeded feeds (if `DiscoverySourceSeeder`
   was run) as inactive-until-first-run.
6. Confirm `https://your-domain/.env` returns 404 (see section 1).
7. Confirm `APP_DEBUG=false` is in effect: visit a route that 404s
   (`/article/does-not-exist`) and verify it shows a plain "not found"
   page, not a stack trace.

## 4. Production `.env` contract

Every variable below must have a real value in production; none of
these are given real values here.

```
APP_NAME=EPIC World
APP_ENV=production
APP_KEY=                      # generated on the server itself - see 3.5
APP_DEBUG=false
APP_URL=https://your-real-domain
APP_TIMEZONE=UTC

LOG_CHANNEL=stack
LOG_LEVEL=error                # not "debug" - production should not log every request's detail

DB_CONNECTION=mysql
DB_HOST=127.0.0.1              # or Hostinger's provided DB host
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_SECURE_COOKIE=true     # HTTPS only - see .env.example's own note
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

CACHE_STORE=database           # no Redis needed on shared hosting

AI_PROVIDER=gemini
GEMINI_API_KEY=

DISCOVERY_SCHEDULE_ENABLED=true
EDITORIAL_SCHEDULE_ENABLED=true
```

`SESSION_SECURE_COOKIE=true` requires the connection Laravel actually
sees to be HTTPS. If Hostinger terminates TLS at a proxy/load balancer
in front of the app and forwards plain HTTP internally, Laravel needs
`TrustProxies` configured for that proxy or the secure-cookie flag will
prevent the session cookie from being set at all — **verify this on
Hostinger specifically** before flipping it on; this is exactly the kind
of thing that must be checked on the real host, not assumed from local
testing (see the final report's "REQUIRES HOSTINGER VERIFICATION"
items).

## 5. HTTPS / security headers

`SecurityHeaders` middleware (added in Milestone 17) already sends
`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and a
conservative `Permissions-Policy` on every response. It deliberately
does **not** send `Strict-Transport-Security` or a
`Content-Security-Policy` yet — HSTS must never be enabled until HTTPS
in production is confirmed working (an early HSTS header can lock
visitors out of a domain that briefly serves plain HTTP), and a CSP
needs its allowlist built against the real production asset origins
(Vite's built assets, Google Fonts, and a future AdSense integration)
rather than guessed at. Add both as a focused follow-up once HTTPS is
confirmed live on the real domain.

## 6. Backup and rollback

**Before every deployment:**

1. **Database backup.** Hostinger's hPanel provides a MySQL/MariaDB
   export tool, or `mysqldump -u <user> -p <database> > backup-$(date +%Y%m%d-%H%M%S).sql`
   over SSH if available. Store it outside the web root.
2. **Application backup.** Not usually needed separately if deploying
   from Git (the previous commit *is* the backup — see below), but if
   deploying by file upload, keep the previous release directory
   (`releases/<timestamp>/` style, or a `.tar.gz` of the previous
   `public_html`/project folder) until the new one is verified.
3. **Record the current Git commit** (`git rev-parse HEAD` on the
   server, or note the last-deployed commit hash) before deploying the
   next one.
4. **Back up `.env` outside Git** — it's already `.gitignore`d and must
   never be committed; keep a copy in a password manager or the host's
   own secrets storage, not in the repository or a plain file inside the
   web root.

**Rollback procedure, if a deployment causes a problem:**

1. **Application rollback.** Re-deploy the previous Git commit (or
   restore the previous release directory, if deploying by upload).
   `git checkout <previous-commit>` on the server (or re-run the
   deployment procedure at that commit), then re-run
   `composer install --no-dev --optimize-autoloader` and
   `npm run build` (or re-upload its output) if dependencies or assets
   changed between the two commits.
2. **Database rollback — prefer a forward fix.** Do **not** run
   `php artisan migrate:rollback` against production without knowing
   exactly which migration(s) it will reverse and what data that
   destroys — a `nullOnDelete()`/`cascadeOnDelete()` foreign key or a
   dropped column can silently take real data with it. If the schema
   itself is the problem, write a new, forward migration that corrects
   it instead of reversing history. Only run an actual rollback when
   you have verified, migration-by-migration, exactly what its `down()`
   method does and that no production data depends on what it removes —
   and only after taking a fresh backup first regardless.
3. **Restore from backup** only if the forward-fix approach isn't
   viable (e.g. the deploy corrupted data, not just schema) — restore
   the `mysqldump` backup taken in step 1 above.
4. **Cache rebuild.** After any rollback: `php artisan optimize:clear`,
   then re-run the section 3.9 cache commands against the
   now-current code.
5. **Scheduler verification.** Confirm `stories:discover` and
   `editorial:process` are still registered correctly at the rolled-back
   commit: `php artisan schedule:list` (or inspect `routes/console.php`
   directly) — a rollback to a commit predating Milestone 15/16 would
   remove one or both commands entirely, which is a legitimate reason to
   roll forward with a fix instead of back.
6. **Smoke test.** Re-run the section 3.12 checklist against the
   rolled-back deployment before considering the rollback complete.

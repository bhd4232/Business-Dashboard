# Staging Environment Setup (Website API Integration)

A separate Coolify Application already exists for this — it's an empty
shell (not yet connected to a git branch or configured). This walks through
wiring it up so the website team has somewhere to test against that never
touches production data. It follows the same steps as
[`deployment.md`](../deployment.md)'s "First Deploy", just pointed at its
own domain and database instead of production's.

## 1. Connect the Application to this repo

In the Coolify dashboard, open the staging Application → **Source**:

- Repository: this repo, branch `main` (same code as production — staging
  differs by environment/database, not by a separate branch).
- Build pack: **Nixpacks** (the committed `nixpacks.toml` is picked up
  automatically, same as production).

## 2. Create a separate database

**Do not point staging at the production database.** In the Coolify
project, add a new **Resource → MySQL** (or PostgreSQL) specifically for
staging. Note its internal host, port, database name, username, and
password — these go into the environment variables below.

## 3. Environment variables

Start from [`.env.production.example`](../../.env.production.example) and
change:

```env
APP_ENV=staging
APP_URL=https://staging.your-domain.com
ASSET_URL=https://staging.your-domain.com

DB_CONNECTION=mysql
DB_HOST=<the staging MySQL resource's internal host>
DB_PORT=3306
DB_DATABASE=<the staging database name>
DB_USERNAME=...
DB_PASSWORD=...

QUEUE_CONNECTION=database
SESSION_DRIVER=file
CACHE_STORE=file
```

`QUEUE_CONNECTION=database` is enough for staging (no need for a separate
Redis resource just to test the API) — but a queue worker still needs to run
for webhook deliveries and order-processing jobs to actually fire; see step
5. Everything else (mail, Firebase push, etc.) can stay as in the example
file or be left blank — the website API integration doesn't depend on them.

Attach a persistent volume covering `storage/` (same requirement as
production, see `deployment.md`'s "File Uploads" section) so uploaded images
survive a redeploy.

## 4. First deploy

Deploy the Application in Coolify, then run these once (Coolify's "Execute
Command" on the running container, or its post-deploy command hook):

```bash
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 5. Run a queue worker

Webhook deliveries and order processing jobs are queued — without a worker
running, nothing configured against `QUEUE_CONNECTION=database` ever
actually fires. Add a second Coolify service (same repo/image) whose start
command is:

```bash
php artisan queue:work --sleep=1 --tries=3 --max-time=3600
```

## 6. Seed test data

This is the one place `demo:refresh`/`db:seed` is safe to run without
reservation — it's a disposable staging database, not production:

```bash
php artisan db:seed --force
```

This gives the website team a real company with sample products, orders,
and customers to test against, using the same `DemoDataSeeder` this
project's local dev environment already relies on.

## 7. Generate credentials and hand them over

From the staging admin panel: **Settings → Integrations → Website API**:

1. **Generate API key** — copy the token immediately, it's shown once.
2. Set **Webhook delivery URL** to the website team's own staging endpoint,
   generate a **Webhook signing secret**, save, then click **Send test
   webhook** to confirm delivery reaches them.

Hand the website team:

- Staging base URL: `https://staging.your-domain.com/api/v1`
- The generated API key
- The webhook signing secret

They can now develop and test their whole integration — reading
products/stock, posting orders and inquiries, receiving webhooks — without
any risk to live data.

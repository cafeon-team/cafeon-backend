# CafeON environment configuration

Laravel always reads `.env` from the project root. Local development uses the
existing untracked `.env`. Production uses `.env.production.example` as a safe
template from which the server's untracked `.env` is created.

## Local development

Use `D:\cafeon-backend\.env` directly. Fill in only local database and provider
credentials. Never use production credentials in this file, and never commit
it.

## Ubuntu production

For a first deployment only, create the server environment file from the
production template without overwriting an existing file:

```bash
test ! -e .env && cp .env.production.example .env
php artisan key:generate
```

Then enter the hosting database credentials and production provider secrets
directly on the server. Do not copy the completed file back into Git.

For an existing production installation, do not recreate `.env` or run
`key:generate`. Compare `.env.production.example` with `.env` and add only
missing keys. Preserve the existing `APP_KEY` and production secrets.

After changing production configuration:

```bash
php artisan config:clear
php artisan config:cache
```

## Deployment rule

- Commit `.env.production.example`, never a populated environment file.
- Local: `D:\cafeon-backend\.env`
- Production: `/home2/wa26b01/myapp/.env`
- `git pull` updates `.env.production.example` but leaves both real `.env`
  files untouched.
- Back up the production database before running pending migrations.
- Keep `APP_ENV=production` and `APP_DEBUG=false` in production.
- Set `FRONTEND_ORIGINS` only to confirmed HTTPS frontend origins.

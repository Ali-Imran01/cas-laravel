# CAS: Centralized Administration System

Admin console for staff identities, roles, org structure and app access, and the SSO (OAuth2/OIDC) provider for the other portfolio projects.

Status: Phase 0 (setup). See `docs/cas-planning.md` for the full plan and `docs/cas-schema.dbml` for the schema.

## Stack
Laravel 12 · Inertia + React · Tailwind 4 · PostgreSQL · Pest · Larastan · Pint

## Local setup
```bash
cp .env.example .env
composer install && npm install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm run dev
./vendor/bin/sail artisan queue:work   # needed for CSV user imports and other queued jobs
```

## Static demo (Cloudflare Pages)
Same `resources/js/Pages` and `Components` as the real app, with sample data kept in localStorage.
```bash
npm run build:demo   # output: dist-demo/
```
Cloudflare Pages: build command `npm run build:demo`, output directory `dist-demo`.

## Checks
```bash
vendor/bin/pint --test && vendor/bin/phpstan analyse && vendor/bin/pest
```

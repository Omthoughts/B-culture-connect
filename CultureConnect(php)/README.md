# CultureConnect (Zenith)

This repository contains the CultureConnect PHP application. The goal is a polished, secure, and neuro-aesthetically tuned experience.

Quick setup (Windows / XAMPP)

1. Copy `.env.example` to `.env` and edit DB credentials:

```powershell
copy .env.example .env
# then edit .env in your editor
```

2. Start MySQL from XAMPP and create the database (if not created):

```powershell
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS cultureconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
```

3. Run the migration:

```powershell
mysql -u root -p cultureconnect < "c:\xampp\htdocs\CultureConnect(php)\database\migrations\20260102_create_schema.sql"
```

4. Test DB connection:

```powershell
php .\scripts\check_db.php
```

5. Seed a test user and a sample post (optional):

```powershell
php .\scripts\seed.php
```

6. Open in browser:

```
http://localhost/CultureConnect(php)/explore.php
```

Files added/important
- `config/database.php` — environment-aware PDO bootstrap (reads `.env`).
- `core/security.php` — loader for the main `SecurityManager` with helper functions.
- `post.php`, `edit_post.php`, `logout.php` — single-post, editor and logout flows.
- `api/save_post.php`, `api/like_post.php`, `api/comments.php` — JSON endpoints with CSRF and rate-limiting.
- `database/migrations/20260102_create_schema.sql` — migration to create core tables.
- `scripts/check_db.php`, `scripts/seed.php` — helpers for local testing.

Security notes
- CSRF: all forms and state-changing API calls must include a server-generated token (see `SecurityManager::generateCSRFToken()`).
- Rate limiting: Redis-backed rate limiter in `SecurityManager` protects sensitive endpoints.
- CSP: `SecurityManager` adds a CSP header; avoid inline JS/CSS in production and move them to external files to remove `unsafe-inline`.
- Sessions: `SecurityManager` configures secure cookie flags and session regeneration.

Next steps
- Move any remaining inline JS/CSS into `/assets/js` and `/assets/css` and remove `unsafe-inline` from CSP.
- Add integration tests for APIs (comments, save, like) and end-to-end manual checks.

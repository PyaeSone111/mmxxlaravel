# MMXX Laravel API

Laravel backend for MMXX: admin auth, categories CRUD, video upload CRUD.

## Setup

1. **Database**: Uses SQLite by default (`.env` has `DB_CONNECTION=sqlite`). For MySQL, update `.env` and run `php artisan migrate`.
2. **Storage**: `php artisan storage:link` (already run) links `public/storage` to `storage/app/public` for video files.

## Default admin user (seeded)

- **Email:** `admin@mmxx.local`
- **Password:** `password`

## Run

```bash
php artisan serve
```

API base: `http://localhost:8000`

## API overview

- **POST** `/api/admin/login` — Body: `{ "email", "password" }`. Returns `{ "user", "token" }`. Only admin users can log in.
- **POST** `/api/admin/logout` — Requires `Authorization: Bearer <token>`.
- **GET** `/api/admin/me` — Current admin user.

**Categories** (all require admin auth):

- `GET /api/admin/categories` — List
- `POST /api/admin/categories` — Create
- `GET /api/admin/categories/{id}` — Show
- `PUT /api/admin/categories/{id}` — Update
- `DELETE /api/admin/categories/{id}` — Delete

**Videos** (all require admin auth):

- `GET /api/admin/videos` — List (optional `?category_id=`)
- `POST /api/admin/videos` — Create (multipart: `title`, `category_id`, `video` file, optional `description`, `duration_seconds`, `thumbnail`)
- `GET /api/admin/videos/{id}` — Show
- `POST /api/admin/videos/{id}` — Update (multipart, same fields; omit file to keep current)
- `DELETE /api/admin/videos/{id}` — Delete

**Public** (no auth):

- `GET /api/categories` — List categories
- `GET /api/videos` — List videos
- `GET /api/videos/{id}` — Show video
- `GET /api/categories/{id}` — Show category

Videos are stored under `storage/app/public/videos/`. Serve via `APP_URL/storage/videos/...`.

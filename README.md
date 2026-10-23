# NovaX Institute Management System

Production-shaped Laravel 12 institute management system foundation with role dashboards, normalized academic data, exam generation, attendance, study materials, analytics, rankings, notifications-ready announcements, and REST APIs for a future Flutter app.

## Stack

- Laravel 12, PHP 8.2+ locally. PHP 8.3+ is recommended for deployment if that is your institute standard.
- SQLite for local development by default; MySQL 8 works by updating `.env`.
- Bootstrap 5 dashboard UI.
- Laravel queues, scheduler, notifications, storage, validation, policies-ready MVC structure.

## Implemented Modules

- Roles: `super_admin`, `teacher`, `student`
- Web auth: login, logout, remember me, session timeout middleware
- API auth: bearer token table compatible with mobile clients
- Admin dashboard: students, teachers, courses, subjects, attendance, exams, materials, average score
- Teacher workflows: exams, balanced random question selection, attendance, material upload
- Student workflows: dashboard, attendance %, upcoming exams, results-ready APIs, analytics endpoint
- Database: users, API tokens, students, parents, teachers, courses, subjects, batches, question bank, exams, attempts, answers, attendance, materials, announcements, analytics, rankings, settings, activity logs
- Scheduler: `analytics:refresh-students` runs daily at 01:00
- REST API endpoints under `/api/*`
- Feature tests for login, role access, and API bearer auth

## Demo Accounts

All demo passwords are `password`.

- Super Admin: `admin@nonax.test`
- Teacher: `teacher@nonax.test`
- Student: `student@nonax.test`

## Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve
```

Open `http://127.0.0.1:8000/login`.

On this machine the project was scaffolded with Composer using Git source installs because PHP zip/unzip support is missing. Enabling the PHP `zip` extension or installing `unzip`/`7z` will make future installs much faster.

## MySQL 8 Setup

Update `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nonax_ims
DB_USERNAME=root
DB_PASSWORD=
```

Then run:

```bash
php artisan migrate:fresh --seed
```

## API Quick Start

Login:

```bash
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Accept: application/json" \
  -d "email=student@nonax.test" \
  -d "password=password"
```

Use the returned token:

```bash
curl http://127.0.0.1:8000/api/profile \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

Available API routes include profile, dashboard, courses, subjects, notes, attendance, daily quiz, mock test, monthly test, results, analytics, notifications, and leaderboard.

## Operations

Run queues:

```bash
php artisan queue:work
```

Run the scheduler:

```bash
php artisan schedule:work
```

Refresh analytics manually:

```bash
php artisan analytics:refresh-students
```

Run tests:

```bash
php artisan test
```

## Next Production Hardening Steps

- Install Laravel Sanctum if you want official Sanctum token tables/classes instead of the included lightweight bearer-token implementation.
- Add `maatwebsite/excel` and a PDF package such as `barryvdh/laravel-dompdf` for full Excel/PDF exports.
- Add policy classes per resource before opening write routes to more granular staff roles.
- Configure mail, queue backend, backups, and object storage for real deployment.

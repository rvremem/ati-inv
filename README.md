# ati-inv

ATI Inventory System — a Laravel application for multi-location inventory management across ATI's branches.

## Features

- Item / parts inventory with per-location stock
- Machine records and contract tracking
- Stock requests, receiving, and defective-item handling
- Role-based access (SA, Developer, Service Desk, and branch roles)
- Reporting and activity logging

## Requirements

- PHP 8.2+
- Composer
- SQL Server (with the `sqlsrv` / `pdo_sqlsrv` PHP extensions)

## Setup

```bash
composer install

cp .env.example .env
# fill in DB_* values for your SQL Server instance

php artisan key:generate
php artisan migrate
php artisan serve
```

The app is then available at http://localhost:8000.

## Configuration

All environment-specific settings live in `.env`, which is **not** committed. Use
`.env.example` as the template — it lists every key the app reads, with placeholder
values.

## Project layout

| Path | Contents |
| --- | --- |
| `app/` | Models, controllers, middleware |
| `routes/` | Web and API route definitions |
| `resources/views/` | Blade templates |
| `database/migrations/` | Schema migrations |
| `public/` | Web root and built assets |

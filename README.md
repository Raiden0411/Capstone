# Capstone Booking Platform

A multi-tenant tourism booking platform for Victorias City, Negros Occidental, Philippines.

Tourists discover destinations, activities, and events through an interactive map and directory; business owners onboard through a KYB workflow and manage listings, bookings, and documents through an admin console; platform administrators review applications, monitor analytics, and manage users across all tenants.

Built with **Laravel 13**, **Livewire 4**, **Tailwind CSS v4**, **MySQL**, and **MapLibre GL JS**.

---

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Test credentials](#test-credentials)
- [Architecture overview](#architecture-overview)
- [Common tasks](#common-tasks)
- [Scheduled jobs](#scheduled-jobs)
- [Production deployment](#production-deployment)
- [Troubleshooting](#troubleshooting)
- [License](#license)

---

## Requirements

| Component | Minimum | Notes |
|---|---|---|
| PHP | 8.3 | 8.5 tested and recommended |
| Composer | 2.x | |
| Node.js | 20.x | For Vite asset building |
| MySQL | 8.0 | Or MariaDB 10.6+ |
| PHP extensions | `pdo_mysql`, `gd`, `mbstring`, `dom`, `zip` | `imagick` optional (faster image processing) |

The system runs on Laragon (Windows), Laravel Herd, Valet, or any LAMP/LEMP stack.

---

## Installation

```bash
# 1. Clone the repository
git clone <repository-url> Capstone
cd Capstone

# 2. Install PHP dependencies
composer install

# 3. Install JavaScript dependencies
npm install

# 4. Copy the environment template
cp .env.example .env

# 5. Generate an application key
php artisan key:generate

# 6. Edit .env and set:
#    - DB_DATABASE, DB_USERNAME, DB_PASSWORD  (your MySQL credentials)
#    - APP_URL                                (the URL you'll browse to)
#    - LEGAL_CONTROLLER_NAME, LEGAL_CONTROLLER_EMAIL
#    - MAIL_* and PAYMONGO_* are optional for local dev

# 7. Create the database
mysql -u root -e "CREATE DATABASE Capstone CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 8. Run migrations and seed demo data
php artisan migrate:fresh --seed

# 9. Create the storage symlink
php artisan storage:link

# 10. Build frontend assets
npm run build

# 11. Start the development server
php artisan serve
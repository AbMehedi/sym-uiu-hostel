# 🏨 Hostel Management System

A full-featured hostel management web application built with **Symfony 7.4**, **PHP 8.2**, **MySQL 8**, and **Twig** templating. It supports three user roles — **Admin**, **Supervisor**, and **Student** — and handles everything from room assignments and admission requests to complaint tracking and PDF report generation.

---

## 📋 Table of Contents

- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Project Structure](#-project-structure)
- [Prerequisites](#-prerequisites)
- [Getting Started (Docker — Recommended)](#-getting-started-docker--recommended)
- [Getting Started (Local — Without Docker)](#-getting-started-local--without-docker)
- [Environment Variables](#-environment-variables)
- [User Roles](#-user-roles)
- [Database Migrations](#-database-migrations)
- [Running Tests](#-running-tests)
- [Contribution Guide](#-contribution-guide)
- [Common Commands Cheatsheet](#-common-commands-cheatsheet)

---

## ✨ Features

| Feature                     | Description                                              |
|-----------------------------|----------------------------------------------------------|
| 🔐 Authentication           | Role-based login (Admin, Supervisor, Student)            |
| 🛏️ Room Management          | Create, assign, and track room availability & status     |
| 📋 Admission Requests       | Students can request hostel admission                    |
| 🔄 Room Change Requests     | Students can request room transfers                      |
| 🛠️ Complaint System         | Students file complaints; supervisors resolve them       |
| 📢 Announcements            | Admin broadcasts notices to all users                    |
| 💬 Chat Messages            | Internal messaging between users                         |
| 🔧 Supervisor Tasks         | Task assignment and tracking for supervisors             |
| 💰 Repair Cost Tracking     | Log and manage repair costs                              |
| 📄 PDF Report Generation    | Export reports as PDFs using DomPDF                      |

---

## 🛠 Tech Stack

| Layer       | Technology                            |
|-------------|---------------------------------------|
| Backend     | PHP 8.2, Symfony 7.4                  |
| ORM         | Doctrine ORM 3.x + Migrations         |
| Templating  | Twig 3.x                              |
| Database    | MySQL 8.0                             |
| Assets      | Symfony AssetMapper + Stimulus + Turbo|
| PDF Export  | DomPDF 3.x                            |
| Testing     | PHPUnit 11.x                          |
| Dev Tools   | Symfony Web Profiler, Maker Bundle    |
| Containers  | Docker + Docker Compose               |
| DB Admin UI | phpMyAdmin                            |

---

## 📁 Project Structure

```
hostel-management/
├── docker-compose.yml          # Orchestrates php, db, and phpmyadmin services
├── .env                        # Root-level env (Docker overrides)
│
└── backend/                    # Symfony application root
    ├── Dockerfile              # PHP 8.2 CLI image with pdo_mysql & Composer
    ├── composer.json           # PHP dependencies
    ├── .env                    # App-level environment variables
    ├── .env.dev                # Dev-specific overrides
    ├── .env.test               # Test environment overrides
    │
    ├── src/
    │   ├── Controller/
    │   │   ├── AdminController.php        # Admin dashboard & management actions
    │   │   ├── StudentController.php      # Student portal actions
    │   │   ├── SupervisorController.php   # Supervisor task & complaint handling
    │   │   ├── SecurityController.php     # Login/logout
    │   │   ├── RegistrationController.php # New user registration
    │   │   ├── PdfController.php          # PDF report generation
    │   │   └── HomeController.php         # Landing page
    │   │
    │   ├── Entity/
    │   │   ├── User.php                  # Core user entity (roles, auth)
    │   │   ├── Student.php               # Student profile
    │   │   ├── Supervisor.php            # Supervisor profile
    │   │   ├── Room.php                  # Room details & status
    │   │   ├── RoomAssignment.php        # Room ↔ Student assignments
    │   │   ├── RoomChangeRequest.php     # Room transfer requests
    │   │   ├── AdmissionRequest.php      # Admission applications
    │   │   ├── Complaint.php             # Student complaints
    │   │   ├── ComplaintUpdate.php       # Complaint status updates/comments
    │   │   ├── Announcement.php          # Admin announcements
    │   │   ├── ChatMessage.php           # Internal messages
    │   │   ├── SupervisorTask.php        # Task assignments
    │   │   ├── RepairCost.php            # Repair cost records
    │   │   └── Report.php               # Generated report metadata
    │   │
    │   ├── Enum/
    │   │   ├── Role.php                 # ADMIN | SUPERVISOR | STUDENT
    │   │   ├── RoomStatus.php           # AVAILABLE | OCCUPIED | MAINTENANCE
    │   │   ├── ComplaintStatus.php      # OPEN | IN_PROGRESS | RESOLVED
    │   │   ├── ComplaintCategory.php    # Categories of complaints
    │   │   ├── AdmissionStatus.php      # PENDING | APPROVED | REJECTED
    │   │   ├── AssignmentStatus.php
    │   │   ├── RequestStatus.php
    │   │   ├── TaskStatus.php
    │   │   └── ReportType.php
    │   │
    │   ├── Form/                        # Symfony Form types
    │   ├── Repository/                  # Doctrine repositories
    │   ├── Security/
    │   │   ├── LoginFormAuthenticator.php
    │   │   └── Voter/                   # Authorization voters
    │   └── Kernel.php
    │
    ├── templates/               # Twig HTML templates
    │   ├── base.html.twig
    │   ├── admin/
    │   ├── student/
    │   ├── supervisor/
    │   ├── security/            # Login page
    │   ├── registration/
    │   ├── home/
    │   └── pdf/
    │
    ├── config/
    │   ├── packages/            # Bundle configuration (doctrine, security, etc.)
    │   ├── routes/
    │   ├── routes.yaml
    │   └── services.yaml
    │
    ├── migrations/              # Doctrine database migrations
    ├── tests/                   # PHPUnit test suite
    ├── assets/                  # JS/CSS source files (AssetMapper)
    ├── public/                  # Web root (index.php entry point)
    └── var/                     # Cache, logs (git-ignored)
```

---

## ✅ Prerequisites

Make sure the following are installed on your machine:

### For Docker Setup (Recommended)
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (includes Docker Compose v2)
- Git

### For Local Setup (Without Docker)
- PHP 8.2+ with extensions: `pdo`, `pdo_mysql`, `ctype`, `iconv`, `intl`, `opcache`
- [Composer 2.x](https://getcomposer.org/)
- MySQL 8.0+
- Git
- (Optional) [Symfony CLI](https://symfony.com/download) — for `symfony serve`

---

## 🐳 Getting Started (Docker — Recommended)

This is the fastest way to get the project running with **zero local PHP/MySQL setup**.

### 1. Clone the repository

```bash
git clone <repository-url>
cd hostel-management
```

### 2. Configure environment variables

Copy the backend env file and fill in any secrets:

```bash
cp backend/.env backend/.env.local
```

Open `backend/.env.local` and set your `APP_SECRET`:

```dotenv
APP_SECRET=some-random-32-char-string-here
```

> **Note:** The database credentials are already pre-configured in `docker-compose.yml` to work out of the box. No changes needed for local dev.

### 3. Start all services

```bash
docker compose up -d --build
```

This spins up:
| Service      | URL                          | Description             |
|--------------|------------------------------|-------------------------|
| App (PHP)    | http://localhost:8000        | Symfony application     |
| MySQL        | `localhost:3307`             | Database (port 3307 avoids XAMPP conflicts) |
| phpMyAdmin   | http://localhost:8080        | Database admin UI       |

### 4. Install PHP dependencies

```bash
docker compose exec php composer install
```

### 5. Run database migrations

```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

### 6. (Optional) Load fixtures / seed data

If fixture data exists:

```bash
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

### 7. Open the app

Visit → **http://localhost:8000**

### Stopping the project

```bash
docker compose down
```

To also remove database volumes (⚠️ deletes all data):

```bash
docker compose down -v
```

---

## 💻 Getting Started (Local — Without Docker)

### 1. Clone the repository

```bash
git clone <repository-url>
cd hostel-management/backend
```

### 2. Install dependencies

```bash
composer install
```

### 3. Configure environment

```bash
cp .env .env.local
```

Edit `.env.local` and set your local database URL:

```dotenv
DATABASE_URL="mysql://YOUR_DB_USER:YOUR_DB_PASS@127.0.0.1:3306/hostel_db?serverVersion=8.0"
APP_SECRET=some-random-32-char-string-here
APP_ENV=dev
```

### 4. Create database & run migrations

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Serve the application

Using Symfony CLI:
```bash
symfony serve
```

Or using PHP built-in server:
```bash
php -S localhost:8000 -t public
```

### 6. Open the app

Visit → **http://localhost:8000**

---

## 🔑 Environment Variables

The following are the key environment variables used by the application. Set these in `backend/.env.local` (never commit secrets to `.env`).

| Variable                | Default (Docker)                                          | Description                        |
|-------------------------|-----------------------------------------------------------|------------------------------------|
| `APP_ENV`               | `dev`                                                     | Application environment            |
| `APP_SECRET`            | *(empty — must be set)*                                   | Symfony secret key for CSRF/tokens |
| `DATABASE_URL`          | `mysql://hostel_user:secret@db:3306/hostel_db?serverVersion=8.0` | Doctrine DB connection string |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=0`                      | Message queue transport            |
| `MAILER_DSN`            | `null://null`                                             | Email transport (disabled by default) |
| `DEFAULT_URI`           | `http://localhost`                                        | Base URL for CLI-generated links   |

---

## 👤 User Roles

The system has three roles defined in `src/Enum/Role.php`:

| Role           | Access                                                                   |
|----------------|--------------------------------------------------------------------------|
| **ADMIN**      | Full access — manage users, rooms, assignments, announcements, reports   |
| **SUPERVISOR** | Manage complaints, repair costs, supervisor tasks, announcements         |
| **STUDENT**    | Submit admission requests, complaints, room change requests, view room   |

---

## 🗄️ Database Migrations

Migrations are managed with Doctrine Migrations Bundle.

```bash
# Run all pending migrations
php bin/console doctrine:migrations:migrate

# See migration status
php bin/console doctrine:migrations:status

# Generate a new migration after changing an Entity
php bin/console doctrine:migrations:diff

# Apply a specific migration version
php bin/console doctrine:migrations:execute --up 'App\Migrations\VersionXXX'
```

---

## 🧪 Running Tests

The test suite uses **PHPUnit 11**.

```bash
# Inside Docker
docker compose exec php php bin/phpunit

# Locally
php bin/phpunit
```

Test environment variables are loaded from `backend/.env.test`.

---

## 🤝 Contribution Guide

We welcome contributions! Please follow these steps to keep the codebase consistent.

### Branching Strategy

```
main            → Production-ready code (protected)
develop         → Integration branch (base for all feature branches)
feature/<name>  → New features  (e.g., feature/room-filter)
fix/<name>      → Bug fixes     (e.g., fix/complaint-status-update)
chore/<name>    → Maintenance   (e.g., chore/update-dependencies)
```

### Step-by-Step Workflow

**1. Fork & Clone**
```bash
git clone <your-fork-url>
cd hostel-management
```

**2. Create a branch from `develop`**
```bash
git checkout develop
git pull origin develop
git checkout -b feature/your-feature-name
```

**3. Make your changes**
- Follow PSR-12 coding standards for PHP
- Add or update Twig templates in `backend/templates/`
- Create Entities in `backend/src/Entity/` and generate migrations
- Register new services in `backend/config/services.yaml` if needed

**4. Generate a migration if you changed an Entity**
```bash
docker compose exec php php bin/console doctrine:migrations:diff
docker compose exec php php bin/console doctrine:migrations:migrate
```

**5. Write or update tests**
```bash
docker compose exec php php bin/phpunit
```

**6. Commit with clear messages**
```bash
git add .
git commit -m "feat: add room filter by capacity"
# Use conventional commits: feat | fix | chore | docs | refactor | test
```

**7. Push and open a Pull Request**
```bash
git push origin feature/your-feature-name
```
- Open a PR against the `develop` branch
- Fill in the PR description: what changed, why, and how to test

### Code Style Conventions

- **PHP**: Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) standards
- **Twig**: Keep templates clean; avoid logic — move it to the Controller or a Twig Extension
- **Entities**: Always use PHP attributes for Doctrine mappings (`#[ORM\Column]`, `#[ORM\ManyToOne]`, etc.)
- **Enums**: Use PHP 8.1+ backed enums (already established in `src/Enum/`)
- **Routes**: Define routes using `#[Route]` PHP attributes on controller methods
- **Forms**: Create reusable Form Types in `src/Form/`
- **Commits**: Use [Conventional Commits](https://www.conventionalcommits.org/) format

### What NOT to Commit

- `backend/.env.local` — local secrets
- `backend/var/` — cache & logs
- `backend/vendor/` — Composer packages

These are all covered in `.gitignore`.

---

## 📟 Common Commands Cheatsheet

| Task                                 | Docker Command                                                   | Local Command                                            |
|--------------------------------------|------------------------------------------------------------------|----------------------------------------------------------|
| Start all services                   | `docker compose up -d`                                           | `symfony serve` or `php -S localhost:8000 -t public`     |
| Stop all services                    | `docker compose down`                                            | Ctrl+C                                                   |
| Install dependencies                 | `docker compose exec php composer install`                       | `composer install`                                       |
| Clear Symfony cache                  | `docker compose exec php php bin/console cache:clear`            | `php bin/console cache:clear`                            |
| Run migrations                       | `docker compose exec php php bin/console doctrine:migrations:migrate` | `php bin/console doctrine:migrations:migrate`       |
| Create a new migration               | `docker compose exec php php bin/console doctrine:migrations:diff` | `php bin/console doctrine:migrations:diff`             |
| Generate Entity / Controller         | `docker compose exec php php bin/console make:entity`            | `php bin/console make:entity`                            |
| List all routes                      | `docker compose exec php php bin/console debug:router`           | `php bin/console debug:router`                           |
| Run tests                            | `docker compose exec php php bin/phpunit`                        | `php bin/phpunit`                                        |
| Open shell in PHP container          | `docker compose exec php bash`                                   | —                                                        |
| View logs                            | `docker compose logs -f php`                                     | Check `backend/var/log/dev.log`                          |

---

## 🆘 Troubleshooting

**Port 3307 already in use?**
> Another MySQL or XAMPP instance may be running. Stop it, or change the port mapping in `docker-compose.yml` from `"3307:3306"` to another free port.

**`APP_SECRET` error on startup?**
> Set a 32-character random string in `backend/.env.local` for `APP_SECRET`.

**Composer install fails inside Docker?**
> Try running `docker compose up --build` again to rebuild the image with fresh dependencies.

**Migrations fail with "Table already exists"?**
> The database may have been partially set up. Run `php bin/console doctrine:migrations:status` to check which migrations are pending.

**Permission errors on `var/` directory?**
> Run: `docker compose exec php chmod -R 777 var/`

---

## 📄 License

This project is proprietary. All rights reserved.

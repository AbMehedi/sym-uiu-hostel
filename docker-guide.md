# 🐳 Docker Setup Guide

This guide walks you through setting up and running the Hostel Management System using Docker. Docker runs the entire application stack (Symfony PHP App, MySQL Database, and phpMyAdmin) with zero local PHP/MySQL configuration needed.

---

## 🛠️ Prerequisites

Before you start, make sure you have the following installed:
* [Docker Desktop](https://www.docker.com/products/docker-desktop/) (includes Docker Compose)
* Git

---

## 🚀 Step-by-Step Setup

Follow these commands in your terminal to get the project up and running:

### 1. Clone the repository and enter the directory
```bash
git clone <repository-url>
cd sym-uiu-hostel
```

### 2. Configure environment variables
Create a local environment file by copying the template:

**Windows PowerShell:**
```powershell
copy backend/.env backend/.env.local
```

**Git Bash / macOS / Linux:**
```bash
cp backend/.env backend/.env.local
```

> [!IMPORTANT]
> Open `backend/.env.local` and set a secure 32-character random string for `APP_SECRET`:
> ```dotenv
> APP_SECRET=your_random_32_character_string_here
> ```

### 3. Build and start the containers
Spin up the MySQL database, web server, and phpMyAdmin in the background:
```bash
docker compose up -d --build
```

### 4. Install PHP dependencies
Install all backend PHP packages using Composer:
```bash
docker compose exec php composer install
```

### 5. Setup database schema and run migrations
Create the database tables by executing all pending migration scripts:
```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

### 6. Seed the database with default data
Populate the database with test profiles, rooms, tasks, and initial settings:
```bash
docker compose exec php php bin/console app:seed-db
```

---

## 🌐 Services and Access URLs

Once the containers are running, you can access the services via these URLs on your local machine:

| Service | Host URL | Internal Container Port | Description |
| :--- | :--- | :--- | :--- |
| **Symfony Web App** | [http://localhost:8500](http://localhost:8500) | `8000` | The main website interface |
| **phpMyAdmin** | [http://localhost:8585](http://localhost:8585) | `80` | Web-based database manager |
| **MySQL DB** | `localhost:3307` | `3306` | Database connection port |

---

## 🔑 Default Login Credentials

You can log in to the web app using the following seeded accounts (all accounts share the password: `password`):

* **Admin:** `admin@hostel.com`
* **Supervisors:** 
  * `supervisor.bh1a@hostel.com` or `supervisor.bh1b@hostel.com` (Boys Hostel I)
  * `supervisor.bh2a@hostel.com` or `supervisor.bh2b@hostel.com` (Boys Hostel II)
  * `supervisor.gh@hostel.com` (Girls Hostel)
* **Students:** `student1@hostel.com` through `student16@hostel.com`

---

## 🗃️ Common Maintenance Commands

### Resetting & Re-seeding the database (Clean Slate)
If you need to wipe your database and start fresh with clean seed data:

```bash
# 1. Drop database
docker compose exec php php bin/console doctrine:database:drop --force

# 2. Re-create database
docker compose exec php php bin/console doctrine:database:create

# 3. Re-run migrations
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# 4. Re-seed data
docker compose exec php php bin/console app:seed-db
```

### Deleting the test database
To drop the testing database (`hostel_db_test`):
```bash
docker compose exec php php bin/console doctrine:database:drop --force --env=test
```

### Stopping the containers
Stop all running services without losing database data:
```bash
docker compose down
```

### Stopping and wiping database volumes
Stop all services and delete the database storage (⚠️ **This deletes all data permanently**):
```bash
docker compose down -v
```

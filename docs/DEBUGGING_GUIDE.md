# Debugging Guide

## Table of Contents
1. [Getting Started](#getting-started)
2. [Essential Commands](#essential-commands)
3. [Useful Resources](#useful-resources)
4. [Common Problems](#common-problems)
5. [Debugging Workflow](#debugging-workflow)
6. [Database Inspection](#database-inspection)
7. [Code-Level Debugging](#code-level-debugging)

---

## Getting Started

### Quick Startup Checklist

If something is broken, run these commands in order:

```powershell
# Step 1: Start Docker services
docker compose up -d

# Step 2: Check status
docker compose ps

# Step 3: Install PHP dependencies
docker compose exec php composer install

# Step 4: Apply database migrations
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# Step 5: Load demo data
docker compose exec php php bin/console app:seed-db

# Step 6: Clear cache
docker compose exec php php bin/console cache:clear
```

✅ If all commands complete without errors, your setup is ready.

---

## Essential Commands

### Service Management

```powershell
# Check which services are running
docker compose ps

# Start all services
docker compose up -d

# Stop all services
docker compose down

# Rebuild images and start fresh
docker compose up -d --build --pull always

# Stop and remove volumes (WARNING: deletes database)
docker compose down -v
```

### Dependency & Cache Management

```powershell
# Install or update PHP packages
docker compose exec php composer install

# Update all packages
docker compose exec php composer update

# Clear Symfony application cache
docker compose exec php php bin/console cache:clear

# Clear cache and show environment
docker compose exec php php bin/console cache:clear -e prod
```

### Database Commands

```powershell
# Run pending migrations
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# Create a new migration from changes
docker compose exec php php bin/console make:migration

# Show migration status
docker compose exec php php bin/console doctrine:migrations:status

# Load demo data
docker compose exec php php bin/console app:seed-db

# Execute raw SQL (enters MySQL prompt)
docker compose exec db mysql -u hostel_user -psecret hostel_db
```

### Container Access

```powershell
# Enter PHP container shell
docker compose exec php bash

# Enter MySQL container shell
docker compose exec db bash

# Exit container
exit
```

### Logging & Monitoring

```powershell
# View real-time PHP logs
docker compose logs -f php

# View real-time database logs
docker compose logs -f db

# View real-time phpMyAdmin logs
docker compose logs -f phpmyadmin

# View all logs
docker compose logs -f

# View last 50 lines only
docker compose logs --tail=50

# Stop following logs
Ctrl+C
```

---

## Useful Resources

### Web Interfaces

| Service | URL | Purpose |
|---------|-----|----------|
| Symfony App | http://localhost:8000 | Main application |
| phpMyAdmin | http://localhost:8888 | Database admin panel |
| Database | localhost:3307 | MySQL (from host) |

### phpMyAdmin Access
- **Server:** db
- **Username:** hostel_user
- **Password:** secret
- **Database:** hostel_db

### Docker Desktop
- **Dashboard:** View all containers and logs
- **Stats:** CPU, memory, network usage
- **Volumes:** Manage persistent data

---

## Common Problems

### Problem 1: Services Not Running

**Symptoms:**
- `docker compose ps` shows `Exited` or `Dead` status
- Cannot connect to app on localhost:8000

**Solution:**
```powershell
# Check what went wrong
docker compose logs php

# Try starting again
docker compose up -d

# If it still fails, rebuild
docker compose up -d --build
```

---

### Problem 2: Port 8080 Conflict

**Symptoms:**
- Error: "Port 8080 is already in use"
- Cannot access phpMyAdmin

**Why:**
- Windows reserves certain port ranges (including 8080)
- This workspace uses port 8888 instead

**Solution:**
```powershell
# Use the correct port
http://localhost:8888

# If you see a different port error, check docker-compose.yml
# Current mappings:
# App: 8000
# DB: 3307
# phpMyAdmin: 8888
```

---

### Problem 3: Changes Don't Show in Browser

**Symptoms:**
- Modified PHP code doesn't run
- Updated CSS doesn't display
- New database records don't appear

**Solution:**
```powershell
# Clear Symfony cache
docker compose exec php php bin/console cache:clear

# Hard refresh browser (Ctrl+Shift+Delete on most browsers)
# Or open in private/incognito mode

# If still broken, check:
# 1. Database query returns the data?
# 2. Twig template uses correct field name?
# 3. Record actually saved to database?
```

---

### Problem 4: Dependencies Not Found

**Symptoms:**
- "Class not found" errors
- "Method does not exist" errors
- Code that worked now broken

**Solution:**
```powershell
# Reinstall dependencies
docker compose exec php composer install

# If that doesn't work, clear and rebuild
docker compose exec php composer install --no-cache
docker compose exec php php bin/console cache:clear
```

---

### Problem 5: Database Migrations Fail

**Symptoms:**
- Migration error when running `doctrine:migrations:migrate`
- New entity not mapped to database

**Solution:**
```powershell
# Check migration status
docker compose exec php php bin/console doctrine:migrations:status

# View pending migrations
docker compose exec php php bin/console doctrine:migrations:list

# If migration is broken, you may need to:
# 1. Fix the migration file
# 2. Rollback (if possible): doctrine:migrations:execute --down
# 3. Run again
```

---

### Problem 6: Seed Data Doesn't Load

**Symptoms:**
- Database is empty after running `app:seed-db`
- Seed command shows no error but no data appears

**Solution:**
```powershell
# Check seed command output
docker compose exec php php bin/console app:seed-db -v

# Verify migrations ran first
docker compose exec php php bin/console doctrine:migrations:status

# Query database directly
docker compose exec db mysql -u hostel_user -psecret hostel_db -e "SELECT COUNT(*) FROM users;"

# If still empty, reset and try again
docker compose down -v
docker compose up -d
# Then run all startup commands again
```

---

### Problem 7: Button Click Does Nothing

**Symptoms:**
- Form submit button doesn't work
- No error in browser console
- No network request sent

**Solution:**

**Step 1:** Check browser developer tools (F12)
- Network tab - do you see a request?
- Console tab - any JavaScript errors?

**Step 2:** Check the Twig template
```twig
{# Look for form action and submit button #}
<form action="{{ path('route_name') }}" method="POST">
    <button type="submit">Save</button>
</form>
```

**Step 3:** Check the controller
```php
// Does the route exist?
#[Route('/admin/something', name: 'admin_something', methods: ['POST'])]
public function handleSomething(): Response { ... }
```

**Step 4:** Check the logs
```powershell
docker compose logs -f php
```

---

### Problem 8: Data Inconsistency Across Views

**Symptoms:**
- Data appears different in admin vs student view
- Same record shows different values in different pages
- Count is wrong in one place but right in another

**Solution:**
```powershell
# Check database directly
docker compose exec db mysql -u hostel_user -psecret hostel_db

MySQL> SELECT * FROM complaints WHERE id = 123;
MySQL> SELECT COUNT(*) FROM repair_costs WHERE complaint_id = 123;
```

Then compare with what the page shows:
- If database is correct and page is wrong → template or controller issue
- If database is wrong → controller query or save logic issue

---

## Debugging Workflow

### The 6-Step Debugging Process

1. **Verify Service Status**
   ```powershell
   docker compose ps
   ```

2. **Check Route Exists**
   - Look in `backend/src/Controller/` for the route
   - Verify `#[Route(...)]` annotation

3. **Verify Data Load**
   - Add debug statement in controller
   - Check logs: `docker compose logs -f php`

4. **Check Template Rendering**
   - Inspect HTML in browser (Right-click → Inspect)
   - Look for unexpected values or missing fields

5. **Query Database**
   - Access phpMyAdmin at http://localhost:8888
   - Verify the record actually exists

6. **Clear Cache & Retry**
   ```powershell
   docker compose exec php php bin/console cache:clear
   ```

---

## Database Inspection

### Using phpMyAdmin (GUI)

1. Open http://localhost:8888
2. Login with:
   - Server: db
   - Username: hostel_user
   - Password: secret
3. Select `hostel_db` database
4. Browse tables and data

### Using MySQL CLI (Command Line)

```powershell
# Enter MySQL prompt
docker compose exec db mysql -u hostel_user -psecret hostel_db

# Useful queries:

# Count records
MySQL> SELECT COUNT(*) FROM users;

# Find specific record
MySQL> SELECT * FROM students WHERE id = 1;

# Check relationships
MySQL> SELECT s.*, r.room_number FROM students s 
       LEFT JOIN room_assignments ra ON s.id = ra.student_id
       LEFT JOIN rooms r ON ra.room_id = r.id;

# Find orphaned records
MySQL> SELECT * FROM complaints WHERE student_id NOT IN (SELECT id FROM students);

# Exit MySQL
MySQL> EXIT;
```

### Common Queries

**Room occupancy check:**
```sql
SELECT r.id, r.room_number, r.current_occupancy, 
       COUNT(ra.id) AS actual_count
FROM rooms r
LEFT JOIN room_assignments ra ON r.id = ra.room_id
GROUP BY r.id;
```

**Complaint status distribution:**
```sql
SELECT status, COUNT(*) FROM complaints GROUP BY status;
```

**User activity:**
```sql
SELECT u.id, u.email, u.roles, s.id as student_id, sup.id as supervisor_id
FROM users u
LEFT JOIN students s ON u.id = s.user_id
LEFT JOIN supervisors sup ON u.id = sup.user_id;
```

---

## Code-Level Debugging

### Enable Verbose Output

```powershell
# Run any command with -v for verbose
docker compose exec php php bin/console app:seed-db -v
```

### Add Debug Output

```php
// In your controller:
use Symfony\Component\VarDumper\VarDumper;

public function myAction(): Response {
    $data = $this->repo->findAll();
    VarDumper::dump($data);  // Will display in logs
    // ...
}
```

### Check Logs in Real Time

```powershell
# Watch PHP errors as they happen
docker compose logs -f php --tail=100

# In another terminal, trigger the action
# The logs will show any errors
```

### Use Browser Console

Press F12 in your browser:
- **Console** tab - JavaScript errors
- **Network** tab - HTTP requests and responses
- **Elements** tab - Inspect HTML and CSS

---

## Quick Reference

### The 30-Second Fix

If something is broken:
```powershell
docker compose down -v  # Stop and delete database
docker compose up -d    # Start fresh
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console app:seed-db
docker compose exec php php bin/console cache:clear
```

Then open http://localhost:8000 and try again.

### URLs Reference

- **App:** http://localhost:8000
- **phpMyAdmin:** http://localhost:8888
- **MySQL:** localhost:3307 (from host)
- **Logs:** `docker compose logs -f php`

---

## Still Stuck?

1. **Check logs first:** `docker compose logs -f php`
2. **Query database:** phpMyAdmin or MySQL CLI
3. **Read the error message carefully** - it usually tells you what's wrong
4. **Google the error** - you're probably not the first
5. **Ask for help** - with logs and steps to reproduce

---

## Related Documents

- Quick Setup: [README.md](README.md)
- Building Features: [IMPLEMENTATION_GUIDE.md](IMPLEMENTATION_GUIDE.md)
- Found a Bug?: [BACKEND_AUDIT.md](BACKEND_AUDIT.md) or [FRONTEND_AUDIT.md](FRONTEND_AUDIT.md)

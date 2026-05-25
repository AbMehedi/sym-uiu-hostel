# Implementation Guide

## Table of Contents
1. [Overview](#overview)
2. [Current Architecture](#current-architecture)
3. [General Workflow](#general-workflow)
4. [Codebase Rules](#codebase-rules)
5. [File Locations](#file-locations)
6. [Phase-Based Build Sequence](#phase-based-build-sequence)
7. [Development Commands](#development-commands)
8. [Quality Checks](#quality-checks)

---

## Overview

This guide explains how to add or improve features in the hostel management system without breaking existing structures. Follow this guide when:
- Building new features
- Fixing bugs
- Refactoring code
- Adding endpoints
- Creating UI improvements

---

## Current Architecture

### Technology Stack
- **Framework:** Symfony 7.4
- **Language:** PHP 8.2
- **Database:** MySQL 8 with Doctrine ORM
- **Templating:** Twig 3.x
- **Frontend:** HTML5, CSS3, vanilla JavaScript + Stimulus
- **Containerization:** Docker Compose

### Project Structure
```
backend/
├── src/
│   ├── Controller/     # Request handlers
│   ├── Entity/        # Data models
│   ├── Repository/    # Custom queries
│   ├── Enum/         # Status/type enums
│   ├── Form/         # Form types
│   ├── Command/      # CLI commands
│   └── Security/     # Auth & authorization
├── templates/        # Twig HTML templates
├── migrations/       # Database schema versions
├── config/          # Symfony configuration
├── public/          # Web root (index.php)
└── docker-compose.yml
```

---

## General Workflow

### Building a New Feature (Step by Step)

**Step 1: Plan the Data Model**
- Identify what records need to be stored
- Determine the relationships between entities
- Plan any new enum types needed

**Step 2: Create/Update the Entity**
- Add or modify `backend/src/Entity/*.php`
- Add appropriate Doctrine annotations
- Create getters and setters

**Step 3: Create Database Migration**
```powershell
docker compose exec php php bin/console make:migration
```

**Step 4: Add Repository Methods**
- Extend `backend/src/Repository/*.php` if custom queries are needed
- Keep queries simple and well-named
- Add documentation for complex queries

**Step 5: Build Controller Action**
- Add route(s) to `backend/src/Controller/` 
- Handle GET (page rendering) and POST (form submission) separately
- Load data from repositories
- Apply validation
- Persist changes to database
- Return appropriate responses

**Step 6: Create/Update Twig Template**
- Create or modify `backend/templates/`
- Use real data from the controller
- Add empty states when no data exists
- Ensure responsive design

**Step 7: Add Seed Data**
- Update `backend/src/Command/SeedDbCommand.php`
- Create realistic demo records
- Include edge cases (pending, approved, rejected, etc.)

**Step 8: Add Tests** (if important workflow)
- Create integration test for the route
- Create repository test if custom queries were added
- Test happy path and error cases

---

## Codebase Rules

### Must Follow
- ✅ Keep status/type values in enums (e.g., `ComplaintStatus::Pending`)
- ✅ Keep controller logic focused on routing, not business logic
- ✅ Use repository methods for all database queries
- ✅ Never hardcode sample data in templates when database can provide it
- ✅ Use flash messages for user feedback
- ✅ Add validation before creating records

### Must Not
- ❌ Store counts that could drift from source records (use computed properties or queries)
- ❌ Use fallback logic that silently chooses a wrong entity
- ❌ Mix database queries in templates
- ❌ Create records for users/rooms that don't belong to the current user
- ❌ Hardcode URLs or routes

### Prefer
- 📌 Query the database for truth, never sample arrays
- 📌 Repository methods over inline queries
- 📌 Form builders for user input
- 📌 Empty states over "no data" messages
- 📌 Type hints and PHPDoc comments

---

## File Locations

| What | Where |
|------|-------|
| HTTP Controllers | `backend/src/Controller/` |
| Data Models | `backend/src/Entity/` |
| Database Queries | `backend/src/Repository/` |
| Status Enums | `backend/src/Enum/` |
| HTML Templates | `backend/templates/` |
| CLI Commands | `backend/src/Command/` |
| Forms | `backend/src/Form/` |
| Migrations | `backend/migrations/` |
| Tests | `backend/tests/` |

---

## Phase-Based Build Sequence

### Phase 1: Fix Source-of-Truth Data
- Reconcile `Room.currentOccupancy` with active `RoomAssignment` records
- Remove fallback room logic from complaint creation
- Add occupancy reconciliation command
- See: [PROJECT_TRACKER.md - Phase 1](PROJECT_TRACKER.md#phase-1-fix-source-of-truth-data)

### Phase 2: Restore Core Workflows
- Implement chat message persistence and retrieval
- Wire complaint update and repair-cost endpoints
- Ensure all action buttons post to real routes
- See: [PROJECT_TRACKER.md - Phase 2](PROJECT_TRACKER.md#phase-2-restore-core-workflows)

### Phase 3: Replace Mock Frontend Content
- Remove placeholder room images
- Replace hardcoded demo names with real data
- Add graceful empty states
- See: [PROJECT_TRACKER.md - Phase 3](PROJECT_TRACKER.md#phase-3-replace-mock-frontend-content)

### Phase 4: Expand Seed Data and Testing
- Expand `SeedDbCommand.php` with multiple scenarios
- Add integration tests for key routes
- Add repository tests
- See: [PROJECT_TRACKER.md - Phase 4](PROJECT_TRACKER.md#phase-4-expand-seed-data-and-testing)

---

## Development Commands

### Setup & Maintenance
```powershell
# Start Docker stack
docker compose up -d

# Check service status
docker compose ps

# Install PHP dependencies
docker compose exec php composer install

# Run database migrations
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# Create a new migration
docker compose exec php php bin/console make:migration

# Seed demo data
docker compose exec php php bin/console app:seed-db

# Clear cache
docker compose exec php php bin/console cache:clear
```

### Common Development Tasks
```powershell
# Enter the PHP container for multiple commands
docker compose exec php bash

# Run tests
docker compose exec php php bin/phpunit

# Generate API docs (if applicable)
docker compose exec php php bin/console api:generate

# Check Doctrine mapping
docker compose exec php php bin/console doctrine:mapping:info
```

### Debugging
```powershell
# View real-time logs
docker compose logs -f php

# Access phpMyAdmin
# http://localhost:8888

# Access the app
# http://localhost:8000
```

---

## Quality Checks

### Before Marking a Feature as Done

Answer these questions:

1. **Data Persistence**
   - [ ] Does the page show real data after refresh?
   - [ ] Does the backend save the change?
   - [ ] Does the database contain the new record?

2. **Cache & State**
   - [ ] Does the feature work after cache clear?
   - [ ] Do changes appear immediately without manual refresh?

3. **Consistency**
   - [ ] Does the same record appear in all relevant views (admin/supervisor/student)?
   - [ ] Are counts calculated from the same source records?

4. **Validation**
   - [ ] Are validation errors shown to the user?
   - [ ] Can invalid input be prevented at the database level?

5. **Edge Cases**
   - [ ] Does it work with no data (empty lists)?
   - [ ] Does it handle missing relationships gracefully?

### Pre-Commit Checklist
- [ ] Code follows the rules in [Codebase Rules](#codebase-rules)
- [ ] No sample data is hardcoded in templates
- [ ] All new routes are tested
- [ ] Database migrations are included
- [ ] Seed data is expanded if needed
- [ ] Documentation is updated (this guide, PROJECT_TRACKER.md, or audit files)

---

## Example: Adding Room Photo Upload

Here's how you would implement room photo uploads using this guide:

### 1. Plan Data Model
- Add `photo_path` field to Room entity
- Store relative path like `/uploads/rooms/room-123.jpg`

### 2. Create Migration
```powershell
docker compose exec php php bin/console make:migration
```

### 3. Add Controller Method
```php
#[Route('/admin/rooms/{id}/photo', name: 'admin_room_photo', methods: ['POST'])]
public function uploadRoomPhoto(int $id, Request $request, RoomRepository $repo, EntityManagerInterface $em): Response {
    $room = $repo->find($id);
    if (!$room) return $this->redirectToRoute('admin_rooms');
    
    $file = $request->files->get('photo');
    if ($file) {
        $filename = bin2hex(random_bytes(8)) . '.' . $file->guessExtension();
        $file->move($this->getParameter('uploads_dir'), $filename);
        $room->setPhotoPath('/uploads/rooms/' . $filename);
        $em->flush();
        $this->addFlash('success', 'Room photo updated.');
    }
    
    return $this->redirectToRoute('admin_rooms');
}
```

### 4. Add Twig Template
```twig
<form action="{{ path('admin_room_photo', {id: room.id}) }}" method="post" enctype="multipart/form-data">
    <input type="file" name="photo" accept="image/*" required>
    <button type="submit">Upload Photo</button>
</form>

{% if room.photoPath %}
    <img src="{{ asset(room.photoPath) }}" alt="Room photo">
{% else %}
    <div class="placeholder">No photo</div>
{% endif %}
```

### 5. Add Seed Data
Update `SeedDbCommand.php` with a few example photos.

### 6. Test
- Can you upload a photo?
- Does it appear after refresh?
- Does it appear in the database?
- Does it work after cache clear?

---

## Additional Resources

- [Symfony Documentation](https://symfony.com/doc/current/)
- [Doctrine ORM](https://www.doctrine-project.org/)
- [Twig Template Engine](https://twig.symfony.com/)
- Project Trackers: [PROJECT_TRACKER.md](PROJECT_TRACKER.md)
- Troubleshooting: [DEBUGGING_GUIDE.md](DEBUGGING_GUIDE.md)

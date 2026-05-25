# Project Tracker

## Table of Contents
1. [Project Goal](#project-goal)
2. [Current Status](#current-status)
3. [Phases Overview](#phases-overview)
4. [Detailed Phase Breakdown](#detailed-phase-breakdown)
5. [Phase Execution Order](#phase-execution-order)
6. [Success Criteria](#success-criteria)
7. [Quick Checklist](#quick-checklist)

---

## Project Goal

**Transform the hostel management system from a partially-wired prototype into a fully functional, data-driven application.**

Success means:
- Frontend, backend, and database show the **same real data**
- Users can complete workflows **end-to-end**
- No **hardcoded demo data** or client-side-only features
- System is **reliable, testable, and maintainable**

---

## Current Status

### Achievements ✅
- [x] Docker stack runs successfully
- [x] PHP dependencies install correctly  
- [x] Database migrations apply successfully
- [x] Seed command creates demo data
- [x] Core entities exist for all major features
- [x] Base controller and template structure is in place

### Remaining Work 🔄

**Critical Issues:**
- [ ] Chat system is not persisting messages to database
- [ ] Complaint creation has unsafe fallback logic
- [ ] Room occupancy can drift from actual assignments

**High Priority:**
- [ ] Home page uses placeholder images
- [ ] Multiple screens show hardcoded demo content
- [ ] Seed data is insufficient for realistic testing

**Medium Priority:**
- [ ] Frontend empty states need improvement
- [ ] Some workflows lack end-to-end testing
- [ ] Mobile responsiveness needs verification

---

## Phases Overview

| Phase | Goal | Priority | Status | Est. Effort |
|-------|------|----------|--------|-------------|
| **Phase 0** | Stabilize environment | High | ✅ Complete | 2-3 hours |
| **Phase 1** | Fix data integrity | High | 🔄 In Progress | 8-10 hours |
| **Phase 2** | Restore core workflows | High | 🔄 In Progress | 12-15 hours |
| **Phase 3** | Polish frontend | High | 🔄 In Progress | 10-12 hours |
| **Phase 4** | Expand & test | Medium | ⏳ Not Started | 6-8 hours |

---

## Detailed Phase Breakdown

### Phase 0: Stabilize the Environment ✅

**Goal:** Make the project easy to run and verify from a clean checkout.

**Completed Items:**
- [x] Docker Compose stack starts successfully on Windows
- [x] PHP container runs Composer, migrations, cache clear, and seed
- [x] phpMyAdmin accessible on port 8888 (workaround for Windows port reserves)
- [x] Database migrations apply without errors
- [x] Documentation created for all setup steps

**Remaining Items:**
- [ ] Update setup instructions if Docker ports change
- [ ] Add environment notes for new contributors
- [ ] Verify setup works after complete clean clone

**Exit Criteria:**
- ✅ New developer can run: `docker compose up -d` → composer install → migrate → seed → open http://localhost:8000
- ✅ No manual configuration or setup guessing required
- ✅ All services running without errors

---

### Phase 1: Fix Source-of-Truth Data 🔄

**Goal:** Make the database the single trusted source for rooms, assignments, complaints, and counts.

**Completed Items:**
- [x] Core entities exist: Room, RoomAssignment, Student, Supervisor, Complaint, RepairCost
- [x] Complaint total calculation already derived from RepairCost records
- [x] Room occupancy field exists and is updated during assignments

**Remaining Items:**
- [ ] **Reconcile occupancy** - Match `Room.currentOccupancy` with active `RoomAssignment` records
  - Add computed property: `getActualOccupancy()` to Room entity
  - Create console command: `app:reconcile-occupancy`
  - Add validation to prevent overcrowding

- [ ] **Review all occupancy updates** - Audit every location incrementing/decrementing count
  - Check: `AdminController.php` lines 53, 115, 135, 165, 175, 186, 206
  - Check: `SupervisorController.php` lines 171, 181, 192
  - Ensure all paths sync correctly

- [ ] **Remove fallback room logic** - Prevent complaints/requests without real assignment
  - Fix: `StudentController.php` line 140 (complaint fallback)
  - Fix: `StudentController.php` line 185 (room change fallback)
  - Return validation error instead of fallback

- [ ] **Add occupancy safety** - Stop assignments when room is full
  - Validate capacity before creating assignment
  - Show error if room is at limit

- [ ] **Test data consistency** - Verify room status matches occupancy
  - Cannot create 5 assignments for 4-person room
  - Occupancy cannot drift more than 1 record

**Exit Criteria:**
- ✅ Room occupancy always equals active assignment count
- ✅ Occupancy can be rebuilt from database anytime
- ✅ Complaints always link to student's actual room
- ✅ Room cannot exceed capacity
- ✅ Admin can trust all occupancy numbers

---

### Phase 2: Restore Core Workflows 🔄

**Goal:** Make the main student, supervisor, and admin actions work end-to-end with database persistence.

**Completed Items:**
- [x] Complaint submission workflow exists
- [x] Room change request workflow exists
- [x] Announcement creation exists
- [x] Room assignment workflow exists
- [x] Complaint status updates exist in backend
- [x] Repair cost tracking exists
- [x] Supervisor approval workflow exists

**Remaining Items:**
- [ ] **Wire chat persistence** - Store and retrieve messages from database
  - Create POST `/student/chat/send` endpoint
  - Create GET `/student/chat/roommate/{id}/history` endpoint
  - Create GET `/student/chat/supervisor/history` endpoint
  - Load real roommates from RoomAssignment
  - Load supervisor from block assignment

- [ ] **Load real conversations** - Replace hardcoded demo data
  - Remove JavaScript demo messages from templates
  - Query ChatMessage table on page load
  - Show real roommate list
  - Implement pagination for long conversations

- [ ] **Verify all workflows** - Ensure every button posts to real endpoint
  - Test complaint submission → creates complaint → appears in list
  - Test room change request → creates request → awaits approval
  - Test approval → updates status → appears to student
  - Test repair cost entry → calculates total → shows in complaint

- [ ] **Consistency across views** - Same data in admin/supervisor/student views
  - Complaint appears in all three role views
  - Status updates appear immediately in all views
  - Costs calculated same way everywhere

**Exit Criteria:**
- ✅ Chat messages persist after page refresh
- ✅ Message history loads on page open
- ✅ All state-changing buttons create database records
- ✅ Same record appears consistently in all role views
- ✅ Workflows work end-to-end without broken links

---

### Phase 3: Replace Mock Frontend Content 🔄

**Goal:** Remove demo content and make the UI reflect live data.

**Completed Items:**
- [x] Frontend pages and layouts exist for admin, student, and supervisor
- [x] Consistent Twig base template structure
- [x] Shared CSS and JavaScript

**Remaining Items:**
- [ ] **Remove placeholder images** - Add room photo system
  - Add `photo_path` field to Room entity
  - Create migration
  - Add upload handler in admin panel
  - Display photos in home page and room listings
  - Add fallback for missing photos

- [ ] **Remove hardcoded names** - Use real database values
  - Replace "Sakib Rahman", "Nadia Hossain" with actual roommates
  - Replace "Supervisor Rahman" with actual supervisor
  - Remove demo contact data from all views

- [ ] **Add empty states** - Handle "no data" gracefully
  - "No complaints yet" message on empty list
  - "No announcements" placeholder
  - "No messages" placeholder in chat
  - Empty states should be visually appealing

- [ ] **Improve loading feedback** - Show action progress
  - Loading spinner when fetching data
  - Saving indication when posting
  - Success/error messages after actions

- [ ] **Test responsive design** - Works on all screen sizes
  - Dashboard on mobile (verify sidebar collapses)
  - Forms on tablet (verify fields stack)
  - Chat on mobile (verify message bubbles fit)

**Exit Criteria:**
- ✅ No hardcoded demo data in production views
- ✅ All content comes from database
- ✅ Empty states are handled gracefully
- ✅ Mobile layout remains usable
- ✅ Same data visible in all matching views

---

### Phase 4: Expand Seed Data & Testing ⏳

**Goal:** Create realistic test dataset and protect important workflows.

**Completed Items:**
- [x] `app:seed-db` command exists and runs

**Remaining Items:**
- [ ] **Expand seed data** - Create diverse test scenarios
  - Create 10-15 students (various admission states)
  - Create 3-5 supervisors (different blocks)
  - Create 15-20 rooms (across multiple floors/blocks)
  - Create various complaint states (pending, in-progress, resolved)
  - Create pending and approved room changes
  - Create admission requests in different states
  - Create announcements from different sources

- [ ] **Add integration tests** - Protect key workflows
  - Test student can file complaint
  - Test supervisor can review complaint
  - Test admin can approve room assignment
  - Test admin can process admission request
  - Test room change workflow

- [ ] **Add repository tests** - Verify complex queries
  - Test occupancy calculation
  - Test complaint filtering
  - Test room availability check
  - Test user role queries

- [ ] **Regression testing** - Ensure nothing breaks
  - Test fresh migration from scratch
  - Test seed on clean database
  - Test main routes return 200 OK
  - Test auth workflows

**Exit Criteria:**
- ✅ 50+ seed records created (realistic dataset)
- ✅ All major workflows have test coverage
- ✅ Broken routes detected before manual testing
- ✅ Fresh clone → migrate → seed → test passes
- ✅ Demo data covers pending, approved, rejected, resolved states

---

## Phase Execution Order

✅ **Phase 0** - Already complete (environment stable)

🔄 **Phase 1** - Start here (data integrity is foundation)
→ Estimated effort: **8-10 hours**

🔄 **Phase 2** - After Phase 1 (workflows depend on clean data)
→ Estimated effort: **12-15 hours**

🔄 **Phase 3** - After Phase 2 (polish after functionality works)
→ Estimated effort: **10-12 hours**

⏳ **Phase 4** - Last (testing after features are stable)
→ Estimated effort: **6-8 hours**

**Total Estimated Time:** 36-45 hours

---

## Success Criteria

### For the Overall Project

- [x] Docker environment is reproducible and reliable
- [ ] Database is always the source of truth
- [ ] Chat messages are persisted and searchable
- [ ] Room occupancy is automatically calculated and verified
- [ ] No hardcoded demo data remains in production code
- [ ] All workflows work end-to-end
- [ ] Important workflows have test coverage
- [ ] A new developer can understand the codebase

### For Each Feature

**Chat:**
- [ ] Messages persist after page refresh
- [ ] User can see conversation history
- [ ] Supervisor can review interactions
- [ ] Can search message history

**Room Management:**
- [ ] Cannot exceed room capacity
- [ ] Occupancy is automatically calculated
- [ ] Room photos display correctly
- [ ] Status reflects actual state

**Complaints:**
- [ ] Always linked to student's actual room
- [ ] Status changes are tracked
- [ ] Repair costs are summed correctly
- [ ] Supervisor can review and update

**Admin Panel:**
- [ ] All counts match database
- [ ] Actions create database records
- [ ] No silent failures or fallbacks
- [ ] Audit trail visible

---

## Quick Checklist

### Before Starting Phase 1
- [x] Docker is running
- [x] Database is migrated
- [x] Seed data loaded
- [x] App opens at http://localhost:8000
- [ ] You've read IMPLEMENTATION_GUIDE.md
- [ ] You've read BACKEND_AUDIT.md

### During Phase 1
- [ ] Audit all occupancy increment/decrement locations
- [ ] Create Room.getActualOccupancy() method
- [ ] Create app:reconcile-occupancy command
- [ ] Remove fallback room logic
- [ ] Test all scenarios
- [ ] Update PROJECT_TRACKER.md with progress

### Before Moving to Phase 2
- [ ] All Phase 1 items marked done [x]
- [ ] No occupancy drift detected
- [ ] All tests pass
- [ ] Code review complete

---

## Related Documents

- Quick Start: [README.md](README.md)
- Getting Help: [DEBUGGING_GUIDE.md](DEBUGGING_GUIDE.md)
- How to Build: [IMPLEMENTATION_GUIDE.md](IMPLEMENTATION_GUIDE.md)
- Backend Issues: [BACKEND_AUDIT.md](BACKEND_AUDIT.md)
- Frontend Issues: [FRONTEND_AUDIT.md](FRONTEND_AUDIT.md)
- Database Concerns: [DATABASE_AUDIT.md](DATABASE_AUDIT.md)

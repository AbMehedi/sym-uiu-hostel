# Hostel Management System — Phase-Wise Implementation Plan

> **Stack:** Symfony 7.4 · PHP 8.2 · MySQL 8 · Doctrine ORM · Twig 3 · Vanilla JS/Stimulus · Docker Compose

> [!NOTE]
> **All design decisions are locked. Ready for teammate assignment and development.**

## Background

The foundation (entities, migrations, base controllers, template scaffolding, Docker) is already in place.  
The audit revealed four critical gaps:
1. **Data integrity** — `Room.currentOccupancy` can drift; fallback room logic corrupts complaint data.
2. **Chat non-functional** — `ChatMessage` entity exists but is never written to or read from.
3. **Hardcoded demo content** — Templates show fake names, placeholder images, and JS-only message stores.
4. **Missing features** — Several stakeholder requirements (reports, repair cost stats, announcements, guest page) are incomplete or absent.

---

## Team Structure & Branch Strategy

| Teammate | Track | Git Branch |
|----------|-------|-----------|
| **A** | Data Integrity + Admin Panel | `feature/teammate-a` |
| **B** | Supervisor Workflows + Announcements | `feature/teammate-b` |
| **C** | Student Features + Chat + PDF Reports + Guest Page | `feature/teammate-c` |

**Merge Order:** A → B → C (A's entity fixes are dependencies for B and C)

> [!IMPORTANT]
> Teammate **A must finish and merge first** because they fix the `Room` entity, add `getActualOccupancy()`, and reconcile occupancy. B and C can work in parallel but should rebase on `feature/teammate-a` before final merge.

---

## Shared Rules (All Teammates)

- ✅ Always use enums for status/type values — never raw strings
- ✅ All database queries go in Repository methods
- ✅ Use flash messages for user feedback after every POST
- ✅ Every new route needs a seed data entry and an integration test
- ✅ Run `docker compose exec php php bin/console cache:clear` after template changes
- ❌ No hardcoded demo data in Twig templates
- ❌ No fallback logic that silently picks the wrong entity
- ❌ No inline SQL — use QueryBuilder or DQL in Repositories

---

## Teammate A — Data Integrity + Admin Panel

**Branch:** `feature/teammate-a`  
**Estimated effort:** 14–16 hours  
**Can start immediately — no dependencies**

### Phase A1: Fix Data Integrity (Critical — Do First)

#### [MODIFY] `backend/src/Entity/Room.php`
- Add `getActualOccupancy(): int` — counts active `RoomAssignment` records via the collection
- Add `isFull(): bool` helper
- Keep `currentOccupancy` field but deprecate direct mutation; migrate all callers to `recalculateOccupancy()`

```php
public function getActualOccupancy(): int {
    return $this->roomAssignments
        ->filter(fn($a) => $a->getStatus() === AssignmentStatus::Active)
        ->count();
}
public function isFull(): bool {
    return $this->getActualOccupancy() >= $this->getCapacity();
}
public function recalculateOccupancy(): void {
    $this->currentOccupancy = $this->getActualOccupancy();
}
```

#### [NEW] `backend/src/Command/ReconcileOccupancyCommand.php`
- Command name: `app:reconcile-occupancy`
- Loops all rooms, calls `recalculateOccupancy()`, flushes, logs output

#### [MODIFY] `backend/src/Controller/AdminController.php`
- Replace all manual `setCurrentOccupancy(+1/-1)` with `room->recalculateOccupancy()` after flush
- Add capacity guard before `roomAssign()`: if `isFull()` → flash error, redirect

#### [MODIFY] `backend/src/Controller/SupervisorController.php`
- Same occupancy fix for `roomChangeApprove()` — call `recalculateOccupancy()` instead of manual math

---

### Phase A2: Admin — Room Management

#### [MODIFY] `backend/src/Entity/Room.php`
- Add `photoPath: ?string` field with nullable Doctrine mapping

#### [NEW] Migration for `photo_path` column

#### [MODIFY] `backend/src/Controller/AdminController.php`
- Add `POST /admin/rooms/{id}/photo` route → `uploadRoomPhoto()`
- Saves to `public/uploads/rooms/`, stores relative path in `Room.photoPath`
- Add `GET /admin/rooms` — list all rooms with capacity, occupancy, status, photo

#### [MODIFY] `backend/templates/admin/rooms.html.twig`
- Room table: number, block, type, capacity, `actualOccupancy` (computed), status badge
- Per-row: **Assign Student** button, **Upload Photo** button
- Show real photo thumbnail or a styled fallback placeholder

#### [NEW] `backend/templates/admin/room-assign.html.twig`
- Form to pick an unassigned, approved student → assign to room
- Validate room is not full before assigning

---

### Phase A3: Admin — Admission Requests

#### [MODIFY] `backend/src/Controller/AdminController.php`
- `GET /admin/admissions` — list all `AdmissionRequest` records with status filters (Pending / Approved / Rejected)
- `POST /admin/admissions/{id}/approve` — sets status to Approved, makes student eligible for room assignment
- `POST /admin/admissions/{id}/reject` — sets status to Rejected with optional rejection reason stored on the record

#### [MODIFY] `backend/templates/admin/admissions.html.twig`
- Tabbed view: Pending | Approved | Rejected
- Each row: student name, request date, preferred room type, actions

---

### Phase A4: Admin — Supervisor Management

#### [MODIFY] `backend/src/Controller/AdminController.php`
- `GET /admin/supervisors` — list supervisors with block, assigned students count
- `POST /admin/supervisors/add` — create Supervisor (links to existing User or creates new)
- `POST /admin/supervisors/{id}/remove` — deactivate supervisor

#### [NEW] `backend/src/Form/SupervisorType.php`
- Fields: user email, block assignment

#### [MODIFY] `backend/templates/admin/supervisors.html.twig`
- Table with supervisor name, block, student count, task count, actions

---

### Phase A5: Admin — Task Assignment to Supervisors

#### [MODIFY] `backend/src/Controller/AdminController.php`
- `GET /admin/tasks` — list all `SupervisorTask` records
- `POST /admin/tasks/create` — assign task to supervisor with description, due date
- `POST /admin/tasks/{id}/delete` — remove task

#### [NEW] `backend/src/Form/SupervisorTaskType.php`
- Fields: supervisor selector, description, due date, priority

#### [MODIFY] `backend/templates/admin/tasks.html.twig`
- Kanban-style list: Pending | In Progress | Completed columns

---

### Phase A6: Admin — Dashboard + Reports

#### [MODIFY] `backend/src/Controller/AdminController.php`
- Dashboard aggregates (pull from repositories, no hardcoding):
  - Total rooms, occupied, vacant, maintenance
  - Total students, pending admissions
  - Open complaints, resolved this month
  - Total repair cost (sum of all `RepairCost.amount`)
- `GET /admin/reports/repair-costs` — cost breakdown by complaint, category, date range

#### [MODIFY] `backend/templates/admin/dashboard.html.twig`
- Stat cards: occupied/vacant, complaints open/resolved, repair total
- Recent activity feed (last 5 complaints, last 5 admissions)

---

### Phase A7: Expand Seed Data

#### [MODIFY] `backend/src/Command/SeedDbCommand.php`
- 3 supervisors (Block A, B, C)
- 18 rooms (6 per block, mixed types: single, double, triple)
- 12 students (4 pending, 4 approved+assigned, 2 rejected, 2 awaiting assignment)
- 8 complaints in various states (Pending, InProgress, Resolved)
- 5 repair costs attached to resolved complaints
- 3 room change requests (1 pending, 1 approved, 1 rejected)
- 4 supervisor tasks (pending/completed mix)
- 3 announcements from different supervisors

---

### Phase A — Acceptance Criteria

- [ ] `Room.currentOccupancy` always equals active `RoomAssignment` count after reconcile
- [ ] `app:reconcile-occupancy` command runs without error and logs changes
- [ ] Admin cannot assign student to full room (sees flash error)
- [ ] Student complaints cannot be filed without real room (fix is in teammate C's scope — coordinate)
- [ ] Room photo uploads and displays on home page
- [ ] All admin list pages show real database data
- [ ] Seed creates 50+ realistic records

---

## Teammate B — Supervisor Workflows + Announcements + Tasks

**Branch:** `feature/teammate-b`  
**Estimated effort:** 12–14 hours  
**Depends on:** Teammate A's entity changes (`Room.getActualOccupancy()`). Can start development in parallel but must rebase before merging.

### Phase B1: Supervisor — Dashboard

#### [MODIFY] `backend/src/Controller/SupervisorController.php`
- Dashboard data for the supervisor's assigned block:
  - Students in their block (from `RoomAssignment` filtered by block)
  - Open complaints in their block
  - Pending room change requests
  - Pending tasks assigned to them

#### [MODIFY] `backend/templates/supervisor/dashboard.html.twig`
- Stat cards: students in block, open complaints, pending tasks, announcements sent
- Quick-action buttons: New Announcement, View Complaints, View Tasks

---

### Phase B2: Supervisor — View Students in Block

#### [MODIFY] `backend/src/Controller/SupervisorController.php`
- `GET /supervisor/students` — lists all students in supervisor's block
- Filter by room, status
- Show student name, room number, admission date

#### [NEW] `backend/src/Repository/StudentRepository.php` methods
- `findByBlock(string $block): array`
- `findByRoom(int $roomId): array`

#### [MODIFY] `backend/templates/supervisor/students.html.twig`
- Table: student name, room, admission date, complaint count
- Click row → student detail view (read-only)

---

### Phase B3: Supervisor — Complaint Management

#### [MODIFY] `backend/src/Controller/SupervisorController.php`
- `GET /supervisor/complaints` — all complaints in supervisor's block, filterable by status/category
- `GET /supervisor/complaints/{id}` — detail view with all `ComplaintUpdate` records
- `POST /supervisor/complaints/{id}/update` — add a `ComplaintUpdate` (status change + note)
- `POST /supervisor/complaints/{id}/repair-cost` — add a `RepairCost` entry

#### [NEW] `backend/src/Form/ComplaintUpdateType.php`
- Fields: new status (enum), note text, optional photo path

#### [NEW] `backend/src/Form/RepairCostType.php`
- Fields: description, amount (decimal), date

#### [MODIFY] `backend/templates/supervisor/complaints.html.twig`
- Filter tabs: All | Plumbing | Electricity | Cleaning | Noise
- Status badges: color-coded (Pending=red, InProgress=yellow, Resolved=green)
- Each row: subject, student, room, category, status, last update date, action button

#### [MODIFY] `backend/templates/supervisor/complaint-detail.html.twig`
- Complaint subject, description, student info, room
- Timeline of `ComplaintUpdate` records (most recent first)
- Repair cost entries with total
- Form to add new update / add repair cost

---

### Phase B4: Supervisor — Room Change Requests

#### [MODIFY] `backend/src/Controller/SupervisorController.php`
- `GET /supervisor/room-changes` — pending requests in supervisor's block
- `POST /supervisor/room-changes/{id}/approve` — update status, move student assignment, recalculate occupancy for both rooms
- `POST /supervisor/room-changes/{id}/reject` — update status with rejection reason

#### [MODIFY] `backend/templates/supervisor/room-changes.html.twig`
- Table: student, current room, requested room, reason, date submitted
- Approve / Reject buttons with confirmation modal

---

### Phase B5: Supervisor — Announcements & Notices

> **Decision locked:** Announcements target the supervisor's own block only. There is no "All blocks" option for supervisors — that scope is reserved for admin if needed in a future phase.

#### [MODIFY] `backend/src/Controller/SupervisorController.php`
- `GET /supervisor/announcements` — list announcements created by this supervisor (filtered by their block)
- `POST /supervisor/announcements/create` — create new `Announcement` scoped to supervisor's block. Auto-set `block` from `Supervisor.block` — no target picker exposed to the user
- `POST /supervisor/announcements/{id}/delete` — soft-delete (set a `deletedAt` timestamp or status flag)

#### [NEW] `backend/src/Form/AnnouncementType.php`
- Fields: title, body (textarea), publish date
- **No target scope field** — block is injected server-side from the logged-in supervisor

#### [MODIFY] `backend/templates/supervisor/announcements.html.twig`
- List of sent announcements with date, preview body, recipient count (students in block)
- "New Announcement" button opens inline form or modal
- Empty state: "No announcements sent yet."

---

### Phase B6: Supervisor — Task Management

#### [MODIFY] `backend/src/Controller/SupervisorController.php`
- `GET /supervisor/tasks` — list tasks assigned to this supervisor
- `POST /supervisor/tasks/{id}/status` — update task status (Pending → InProgress → Completed)

#### [MODIFY] `backend/templates/supervisor/tasks.html.twig`
- Card view grouped by status
- Each card: task description, assigned by admin, due date, status toggle button

---

### Phase B — Acceptance Criteria

- [ ] Supervisor sees only their block's students and complaints
- [ ] Complaint status update creates a `ComplaintUpdate` record in DB
- [ ] Repair cost entry saves and total is recalculated correctly
- [ ] Room change approve/reject updates `RoomAssignment` and recalculates occupancy for both rooms
- [ ] Announcement appears in student's view immediately after creation
- [ ] Task status changes are persisted in DB

---

## Teammate C — Student Features + Chat + PDF Reports + Guest Page

**Branch:** `feature/teammate-c`  
**Estimated effort:** 14–17 hours  
**Depends on:** Teammate A's entity changes. Can develop in parallel; rebase before final merge.

### Phase C1: Student — Fix Critical Bugs

#### [MODIFY] `backend/src/Controller/StudentController.php`
- **Remove fallback room logic** at line 140:
  ```php
  // REMOVE:
  $room = $em->getRepository(Room::class)->findOneBy([]);
  // REPLACE WITH:
  if (!$room) {
      $this->addFlash('error', 'You must be assigned to a room to file a complaint.');
      return $this->redirectToRoute('student_dashboard');
  }
  ```
- Same fix at line 185 for room change requests

---

### Phase C2: Student — Registration + Admission Request

#### [MODIFY] `backend/src/Controller/RegistrationController.php`
- After successful registration, auto-create an `AdmissionRequest` with status Pending
- Redirect to "Pending Approval" landing page

#### [NEW] `backend/templates/registration/pending.html.twig`
- Friendly "Your application is under review" page
- Show application date, expected response time

#### [MODIFY] `backend/src/Controller/StudentController.php`
- Dashboard: if student status is `Pending`, show limited view (no room info)
- Dashboard: if `Approved`, show full dashboard with room + roommate details

---

### Phase C3: Student — Room & Roommate View

#### [MODIFY] `backend/src/Controller/StudentController.php`
- `GET /student/my-room` — load `RoomAssignment` → `Room` and co-residents from same room
- Pass room details and roommate list to template

#### [MODIFY] `backend/templates/student/my-room.html.twig`
- Room info: number, block, floor, type, capacity/occupancy
- Room photo (from `Room.photoPath`) with fallback icon
- Roommate cards: name, contact (if shared), admission date

---

### Phase C4: Student — Complaint Submission & Tracking

#### [MODIFY] `backend/src/Controller/StudentController.php`
- `GET /student/complaints` — list student's own complaints with status
- `GET /student/complaints/new` — form page
- `POST /student/complaints/new` — validate room assignment → create `Complaint`
- `GET /student/complaints/{id}` — detail view with `ComplaintUpdate` timeline

#### [NEW] `backend/src/Form/ComplaintType.php`
- Fields: subject, category (enum), description, optional photo upload

#### [MODIFY] `backend/templates/student/complaints.html.twig`
- Status-color-coded list
- Empty state: "No complaints yet — your room is in good shape! 🏠"

#### [MODIFY] `backend/templates/student/complaint-detail.html.twig`
- Timeline of updates from supervisor
- Show repair cost total (view only)

---

### Phase C5: Student — Room Change Request

#### [MODIFY] `backend/src/Controller/StudentController.php`
- `GET /student/room-change` — form to request room change
- `POST /student/room-change` — validate current assignment → create `RoomChangeRequest`
- `GET /student/room-change/status` — show current request and its status

#### [MODIFY] `backend/templates/student/room-change.html.twig`
- Form: reason, preferred room type, urgency
- Show existing request (if one is pending) instead of form

---

### Phase C6: Student — Chat (Critical Fix)

> **Decision locked:** Chat uses **simple JS polling** (`setInterval` every 3 seconds). No WebSocket or Mercure hub required. This fits the existing Symfony stack with zero additional infrastructure.

#### [MODIFY] `backend/src/Controller/StudentController.php`

**New endpoints:**
```php
// Load chat page with real contacts
GET /student/chat/supervisor      → renders template with supervisor loaded from DB
GET /student/chat/roommate        → renders template with roommate list loaded from DB

// Send a message (AJAX POST — returns JSON)
POST /student/chat/send
// Body: { recipientId: int, message: string }
// Persists ChatMessage entity, returns { id, sentAt, message }

// Poll for new messages (AJAX GET — returns JSON)
GET /student/chat/history/{contactId}?after={lastMessageId}
// Returns array of ChatMessage objects newer than lastMessageId
// Called every 3 seconds by setInterval in the template
```

#### [MODIFY] `backend/src/Repository/ChatRepository.php`
- `findConversation(int $userA, int $userB, int $limit = 50): array` — returns most recent 50 messages
- `findAfter(int $userA, int $userB, int $afterId): array` — returns messages with id > afterId (used by polling)
- `findUnreadCount(int $recipientId): int`

#### [MODIFY] `backend/templates/student/chat-supervisor.html.twig`
- **Remove ALL hardcoded JS** contact arrays and `msgStore` demo objects
- On page load: supervisor is passed from controller (queried from DB via student's block)
- Initial message history loaded into a `data-` attribute or via first fetch call
- **Polling loop:**
  ```javascript
  let lastId = /* highest message id on page load */;
  setInterval(async () => {
      const res = await fetch(`/student/chat/history/${supervisorId}?after=${lastId}`);
      const msgs = await res.json();
      msgs.forEach(m => { appendBubble(m); lastId = m.id; });
  }, 3000);
  ```
- Send button: POST to `/student/chat/send`, append returned message immediately, update `lastId`
- Auto-scroll to bottom on load and after every new message
- Empty state: "No messages yet — start the conversation!"

#### [MODIFY] `backend/templates/student/chat-roommate.html.twig`
- Same polling architecture as supervisor chat
- Contact list = actual roommates from `RoomAssignment` (same room), passed by controller
- Clicking a roommate name sets `activeContactId` and restarts the poll loop for that conversation
- Empty state if no roommates assigned yet: "You haven't been assigned roommates yet."

#### [NEW] `backend/templates/student/_chat_message.html.twig`
- Reusable Twig partial for a single message bubble
- Styles: sent (right-aligned, accent color) vs received (left-aligned, neutral)
- Shows sender name, message text, timestamp

---

### Phase C7: Student — View Announcements

> **Decision locked:** Students only see announcements scoped to their block (matching `Supervisor.block` on the `Announcement` record). No cross-block announcements are shown.

#### [MODIFY] `backend/src/Controller/StudentController.php`
- `GET /student/announcements` — load `Announcement` records where `block` matches student's assigned block
- Order by `publishedAt` DESC, paginate 10 per page
- Pass supervisor name alongside each announcement

#### [MODIFY] `backend/templates/student/announcements.html.twig`
- Card list: title, date, supervisor name, first 150 chars of body as preview
- Click to expand full announcement inline (JS toggle)
- Empty state: "No announcements from your block yet."

---

### Phase C8: PDF Reports

> **Decision locked:** Photo storage uses `public/uploads/rooms/` (local filesystem). No cloud storage.

> [!NOTE]
> Before building new PDF templates, verify which library is active: check `backend/composer.json` for `dompdf/dompdf` or `knplabs/knp-snappy`. The existing `PdfController.php` already has a working render pattern — follow it.

#### [MODIFY] `backend/src/Controller/PdfController.php`
- `GET /admin/pdf/room-allocation` — **Room Allocation PDF** (admin only)
  - Table: room number, block, type, capacity, `actualOccupancy`, list of resident names
  - Repository method: `RoomRepository::findAllWithActiveResidents()`
- `GET /admin/pdf/monthly-complaints` — **Monthly Complaint Log PDF** (admin only)
  - Query param: `?month=5&year=2026`
  - Table: date, student name, room, category, status, repair cost total per complaint
  - **Repair costs included** — admin can see the full cost column
- `GET /admin/pdf/repair-costs` — **Repair Cost Statistics PDF** (admin only)
  - Grouped by complaint category, sum per category, grand total
  - Secondary breakdown: by supervisor (who resolved the complaint)
  - Include date range filter: `?from=2026-01-01&to=2026-05-31`

#### [MODIFY] `backend/templates/pdf/room-allocation.html.twig`
- Clean print-ready table, A4 layout
- Header: hostel name, report date, generated by (admin name)
- Footer: page number

#### [MODIFY] `backend/templates/pdf/monthly-complaints.html.twig`
- Table: complaint date, student, room, category, status, repair cost
- Summary row at bottom: total complaints, total resolved, total cost

#### [NEW] `backend/templates/pdf/repair-costs.html.twig`
- Category breakdown table (Plumbing, Electricity, Cleaning, Noise, Other)
- Per-category: complaint count, total cost
- Grand total row
- Supervisor breakdown sub-table

---

### Phase C9: Guest Landing Page

> **Decision locked:** Room photos stored locally at `public/uploads/rooms/`. Guest page uses real photos where uploaded, or a styled CSS placeholder (gradient + icon) where not — no external placeholder services.

#### [MODIFY] `backend/src/Controller/HomeController.php`
- Query distinct room types from DB for the Room Types section
- Query one sample photo per room type (first room of that type with a `photoPath`)
- Pass hostel rules and contact info from `config/services.yaml` parameters (easy to edit without code changes)

#### [MODIFY] `backend/templates/home/index.html.twig`
- **Hero section:** hostel name, tagline, two CTA buttons: "Apply Now" (→ registration) and "View Rooms"
- **Stats bar:** total rooms, students accommodated, blocks — pulled from DB
- **Facilities section:** icon grid for amenities (WiFi, Laundry, 24h Security, Canteen, etc.)
- **Room Types section:** one card per distinct type (Single, Double, Triple)
  - Show real `Room.photoPath` image if available
  - Fallback: styled `<div>` with CSS gradient + room icon (no placeholder text)
  - Show capacity, price range (if field exists) or "Contact for pricing"
- **Rules section:** hostel rules as a styled numbered list
- **Contact section:** phone, email, address from config params
- **Remove completely:** `<div class="room-img placeholder-img">Room Photo Placeholder</div>`

---

### Phase C — Acceptance Criteria

- [ ] Student cannot submit complaint without a room assignment (flash error shown)
- [ ] Chat messages persist after page refresh (stored in `ChatMessage` table)
- [ ] Chat polling fires every 3 seconds and appends new messages without full page reload
- [ ] Chat shows real roommates from `RoomAssignment` and real supervisor from block — no hardcoded names
- [ ] Sending a message via AJAX saves to `ChatMessage` table and returns JSON
- [ ] Room change request creates `RoomChangeRequest` record in DB
- [ ] Student does **not** see repair cost amounts anywhere in their views
- [ ] Supervisor can add repair cost entries to a complaint they are handling
- [ ] Admin can view repair costs in complaint detail and in the Repair Cost PDF report
- [ ] All 3 PDF reports generate without error and contain real DB data
- [ ] Guest landing page has no placeholder text — real photos or CSS gradient fallback only
- [ ] Announcements shown to student are scoped to their block only

---

## Merge & Integration Plan

```
main
 └── feature/teammate-a  ← merge first (fixes entities)
      └── feature/teammate-b  ← rebase on A, then merge
      └── feature/teammate-c  ← rebase on A, then merge (B and C can be parallel)
```

### Before each merge:
1. `docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction`
2. `docker compose exec php php bin/console app:seed-db`
3. `docker compose exec php php bin/phpunit`
4. `docker compose exec php php bin/console app:reconcile-occupancy`
5. Manually smoke-test: login as admin, supervisor, student

---

## Verification Plan

### Automated
```powershell
docker compose exec php php bin/phpunit
docker compose exec php php bin/console app:reconcile-occupancy
docker compose exec php php bin/console doctrine:mapping:info
```

### Manual Smoke Test Checklist
| Scenario | Role | Expected |
|----------|------|----------|
| Register new student | Student | AdmissionRequest created, pending page shown |
| Approve admission | Admin | Student status becomes Approved |
| Assign student to room | Admin | Room occupancy increments correctly |
| Try to over-fill room | Admin | Flash error, no assignment created |
| File complaint | Student | Complaint saved, appears in supervisor view |
| Update complaint status | Supervisor | ComplaintUpdate record created, student sees new status |
| Send chat message | Student | Message appears after page refresh |
| Approve room change | Supervisor | Old room occupancy -1, new room +1 |
| Generate Room Allocation PDF | Admin | PDF downloads with real data |
| Guest views landing page | Guest | No placeholder text, real room photos or styled fallback |

---

## Design Decisions (Locked)

All questions resolved by the product owner. These are **final** — do not change without explicit approval.

| # | Question | Decision |
|---|----------|----------|
| Q1 | Chat real-time mechanism | **JS polling** — `setInterval` every 3 seconds. No WebSocket or Mercure. |
| Q2 | Announcement target scope | **Supervisor's block only.** Supervisors cannot broadcast to other blocks. |
| Q3 | Photo storage | **Local filesystem** — `public/uploads/rooms/`. No cloud/S3. |
| Q4 | Repair cost visibility | **Supervisor inputs, Admin views.** Students never see cost amounts. |

> [!NOTE]
> **PDF library:** Teammate C must check `backend/composer.json` for `dompdf/dompdf` or `knplabs/knp-snappy` before writing new PDF templates. Follow the render pattern already in the existing `PdfController.php`.

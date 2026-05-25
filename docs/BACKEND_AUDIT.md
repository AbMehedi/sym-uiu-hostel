# Backend Audit

## Table of Contents
1. [Confirmed Issues](#confirmed-issues)
2. [Code Locations](#code-locations)
3. [Improvement Priorities](#improvement-priorities)
4. [Acceptance Criteria](#acceptance-criteria)

---

## Confirmed Issues

### Issue 1: Chat Not Wired to Database Persistence ⚠️ Critical

**Files Affected:**
- `backend/src/Controller/StudentController.php` (lines 226-232)
- `backend/src/Entity/ChatMessage.php` (exists but unused)
- `backend/src/Repository/ChatRepository.php` (exists but unused)

**Current Behavior:**
```php
#[Route('/chat-roommate', name: 'student_chat_roommate')]
public function chatRoommate(): Response {
    return $this->render('student/chat-roommate.html.twig');
}

#[Route('/chat-supervisor', name: 'student_chat_supervisor')]
public function chatSupervisor(): Response {
    return $this->render('student/chat-supervisor.html.twig');
}
// No persistence, no data loading
```

**Why It Matters:**
- Chat entity exists but is never used
- Messages cannot be retrieved or filtered
- No message history is saved
- Feature is non-functional across sessions

**Fix Required:**
```php
// Need to add:
#[Route('/chat/send', name: 'student_chat_send', methods: ['POST'])]
public function sendMessage(Request $request, EntityManagerInterface $em): Response { ... }

#[Route('/chat/{contactId}/history', name: 'student_chat_history')]
public function chatHistory(int $contactId, ChatRepository $repo): Response { ... }
```

---

### Issue 2: Complaint Fallback Room Logic ⚠️ Critical

**File Affected:**
- `backend/src/Controller/StudentController.php` (line 140)

**Current Code:**
```php
// Line 140 in StudentController
$room = $student->getRoom();
if (!$room) {
    $room = $em->getRepository(\App\Entity\Room::class)->findOneBy([]);
}
```

**Problem:**
- If student has no assigned room, it picks the first room from the database
- This means complaints can be incorrectly linked to rooms
- Data integrity is compromised

**Fix Required:**
```php
if (!$room) {
    $this->addFlash('error', 'You must be assigned to a room to file a complaint.');
    return $this->redirectToRoute('student_dashboard');
}
```

---

### Issue 3: Room Occupancy Manual Maintenance 🟡 High

**Files Affected:**
- `backend/src/Controller/AdminController.php` (lines 53, 115, 135, 165, 175, 186, 206)
- `backend/src/Controller/SupervisorController.php` (lines 171, 181, 192)
- `backend/src/Entity/Room.php` (currentOccupancy field)

**Current Implementation:**
```php
// Manually incremented/decremented throughout controllers
$room->setCurrentOccupancy($room->getCurrentOccupancy() + 1);
$room->setCurrentOccupancy(max(0, $room->getCurrentOccupancy() - 1));
```

**Why It Matters:**
- Occupancy can drift if any operation is missed
- No automatic reconciliation
- Database state becomes unreliable

**Fix Required:**
- Add method to Room entity to calculate occupancy from assignments
- Add console command to reconcile occupancy
- Consider computed property instead of stored field

**Example Solution:**
```php
public function recalculateOccupancy(): void {
    $activeCount = $this->roomAssignments
        ->filter(fn($a) => $a->getStatus() === AssignmentStatus::Active)
        ->count();
    $this->currentOccupancy = $activeCount;
}
```

---

### Issue 4: Seed Data Is Too Minimal 🟡 High

**File Affected:**
- `backend/src/Command/SeedDbCommand.php`

**Current Scope:**
- Creates only 3 rooms (101, 102, 201)
- Creates 1 admin, 1 supervisor, 1 student
- Creates 1 complaint example
- Creates 1 announcement example

**Why It Matters:**
- Cannot test multiple user interactions
- Cannot test approval/rejection workflows
- Cannot test pending states
- Screens appear empty during testing

**Fix Required:**
- Create multiple students (5-10)
- Create multiple supervisors (2-3)
- Create multiple rooms across different blocks (10-15)
- Create various complaint states (pending, in-progress, resolved)
- Create pending and approved room changes
- Create various admission request states

---

## Code Locations

### Controllers Needing Chat Implementation
```
backend/src/Controller/StudentController.php
├── chatRoommate()         // Line 227 - Add message send/history
├── chatSupervisor()       // Line 232 - Add message send/history
└── Related routes needed:
    ├── POST /student/chat/send
    ├── GET /student/chat/roommate/{contactId}/history
    └── GET /student/chat/supervisor/history
```

### Controllers With Occupancy Logic
```
backend/src/Controller/AdminController.php
├── roomAssign()           // Line 186 - Increment occupancy
├── roomUnassign()         // Line 206 - Decrement occupancy
└── Other methods with occupancy changes

backend/src/Controller/SupervisorController.php
├── roomChangeApprove()    // Lines 171-192 - Occupancy sync
└── Related methods
```

### Fallback Logic
```
backend/src/Controller/StudentController.php
├── complaints()           // Line 140 - Complaint fallback room
├── roomChange()           // Line 185 - Room change fallback
└── Both need validation instead of fallback
```

---

## Improvement Priorities

### Priority 1: Fix Chat Persistence (Critical)
- [ ] Add message send endpoint
- [ ] Add message history endpoint
- [ ] Load roommates from RoomAssignment
- [ ] Implement pagination for history
- [ ] Add real-time update mechanism (optional)

### Priority 2: Remove Fallback Logic (Critical)
- [ ] Remove complaint fallback room (line 140)
- [ ] Remove room change fallback (line 185)
- [ ] Add validation errors instead
- [ ] Update tests

### Priority 3: Fix Occupancy Sync (High)
- [ ] Review all occupancy increments/decrements
- [ ] Create reconciliation command
- [ ] Add occupancy validation on room full
- [ ] Test assignment/unassignment workflows

### Priority 4: Expand Seed Data (High)
- [ ] Add 5-10 test students
- [ ] Add 2-3 supervisors
- [ ] Add 10-15 rooms
- [ ] Add various complaint states
- [ ] Add pending/approved room changes
- [ ] Add various admission states

---

## Acceptance Criteria

### Chat Implementation
- [x] Messages are stored in database
- [x] Message history is retrieved and displayed
- [x] Real roommates are loaded from RoomAssignment
- [x] Supervisor is correctly identified
- [x] Messages persist after page refresh
- [x] Pagination works for long conversations

### Fallback Logic Removal
- [x] Complaints show validation error if student has no room
- [x] Room changes show validation error if no current room
- [x] No silent fallback assignments occur
- [x] User sees clear error message

### Occupancy Reconciliation
- [x] Occupancy matches active assignments
- [x] Console command can rebuild occupancy
- [x] Room is marked full when at capacity
- [x] Assignment prevents over-capacity rooms

### Seed Data Expansion
- [x] Multiple test students created
- [x] Multiple supervisors created
- [x] Multiple rooms in different blocks
- [x] Various complaint states exist
- [x] Test data covers all major workflows

---

## Testing Checklist

- [ ] Chat message persists after send
- [ ] Chat history shows all messages
- [ ] Roommate list is accurate
- [ ] Supervisor is correctly assigned
- [ ] Student cannot file complaint without room
- [ ] Occupancy increments on assignment
- [ ] Occupancy decrements on unassignment
- [ ] Occupancy reconciliation command works
- [ ] Seed creates diverse demo data
- [ ] All roles have test data to use

---

## Related Documents

- Implementation Guide: [IMPLEMENTATION_GUIDE.md](IMPLEMENTATION_GUIDE.md)
- Frontend Issues: [FRONTEND_AUDIT.md](FRONTEND_AUDIT.md)
- Database Concerns: [DATABASE_AUDIT.md](DATABASE_AUDIT.md)
- Project Tracker: [PROJECT_TRACKER.md](PROJECT_TRACKER.md)
- Debugging Help: [DEBUGGING_GUIDE.md](DEBUGGING_GUIDE.md)

# Database Audit

## Table of Contents
1. [Current Schema](#current-schema)
2. [Strong Points](#strong-points)
3. [Data Integrity Risks](#data-integrity-risks)
4. [Reconciliation Steps](#reconciliation-steps)
5. [Acceptance Criteria](#acceptance-criteria)

---

## Current Schema

### Entities in the System

The database currently supports all core hostel workflows through these 14 entities:

```
Core Users & Roles
├── User              # Authentication + roles
├── Student          # Student profile + admission status
├── Supervisor       # Supervisor profile + block assignment

Room Management
├── Room             # Room details + occupancy
├── RoomAssignment   # Student-to-room mapping
├── RoomStatus       # Enum: Available, Occupied, Maintenance, Full

Admissions
├── AdmissionRequest # Admission applications
├── AdmissionStatus  # Enum: Pending, Approved, Rejected

Complaints & Repairs
├── Complaint        # Student complaints
├── ComplaintUpdate  # Status updates + notes
├── RepairCost       # Cost tracking for repairs
├── ComplaintStatus  # Enum: Pending, InProgress, Resolved
├── ComplaintCategory# Enum: Plumbing, Electricity, etc.

Requests & Changes
├── RoomChangeRequest   # Room transfer requests
├── RequestStatus       # Enum: Pending, Approved, Rejected

Communications
├── Announcement     # Admin notifications
├── ChatMessage      # Inter-user messages (UNUSED)
├── SupervisorTask   # Task assignments
├── TaskStatus       # Enum: Pending, Completed

Reporting
├── Report           # Generated report metadata
├── ReportType       # Enum: Report types
```

---

## Strong Points

### ✅ Good Data Model Design

1. **Separation of Concerns**
   - Complaints and repair costs are separate entities
   - Allows flexible cost tracking independent of complaint status
   - Enables multiple cost entries per complaint

2. **Proper Use of Relationships**
   - Room assignments separate from room records
   - Allows historical tracking of who lived where
   - Status tracking is separate from content

3. **Enum Usage**
   - Status values are properly enumerated (not strings)
   - Prevents invalid state values
   - Enables type-safe code

4. **Audit Trail Capability**
   - ComplaintUpdate allows tracking of changes
   - Assignment dates are recorded
   - Soft delete capability exists (via status enums)

---

## Data Integrity Risks

### Risk 1: Room Occupancy Drift ⚠️ Critical

**Problem:**
- `Room.currentOccupancy` is manually maintained
- Controllers must remember to increment/decrement
- If any workflow is skipped, the count becomes wrong

**Evidence:**
```php
// Manually done in multiple places:
$room->setCurrentOccupancy($room->getCurrentOccupancy() + 1);
```

**Consequences:**
- Room appears full when it's not
- Room appears available when it's full
- Assignment logic fails
- Admin cannot trust occupancy numbers

**Recommended Solution:**
Add a computed property:
```php
public function getActualOccupancy(): int {
    return $this->roomAssignments
        ->filter(fn($a) => $a->getStatus() === AssignmentStatus::Active)
        ->count();
}
```

Or add a reconciliation command:
```powershell
docker compose exec php php bin/console app:reconcile-occupancy
```

---

### Risk 2: Incomplete Seed Data 🟡 High

**Problem:**
- Demo dataset only includes happy-path scenarios
- Only 1 student, 1 supervisor, 3 rooms
- Missing: pending states, rejections, edge cases

**Current Seed Data:**
```php
// Only creates:
- 1 admin user
- 1 supervisor user  
- 1 student user
- 3 rooms total
- 1 complaint (Pending)
- 1 announcement
```

**Testing Limitations:**
- Cannot test multiple student workflows
- Cannot test supervisor task assignment
- Cannot test approval/rejection flows
- Cannot test concurrent changes

**Recommended Solution:**
Expand to ~50-100 records:
- 10+ students in different admission states
- 3-5 supervisors for different blocks
- 15-20 rooms across multiple blocks
- Complaints in various states (pending, in-progress, resolved)
- Room changes (pending, approved, rejected)
- Announcements from different supervisors

---

### Risk 3: Fallback Assignment Logic 🔴 Critical

**Problem:**
- When student has no room, system picks first available room
- Creates misleading data relationships

**Code Location:**
```php
// StudentController.php, line 140
if (!$room) {
    $room = $em->getRepository(\App\Entity\Room::class)->findOneBy([]);
}
```

**Data Integrity Issue:**
- Complaint gets assigned to wrong room
- Complaint data becomes unreliable
- Cannot use complaint room for analysis

**Recommended Solution:**
Validate before creating:
```php
if (!$room) {
    throw new \RuntimeException('Student must have assigned room');
}
```

---

### Risk 4: ChatMessage Entity Unused 🟡 Medium

**Problem:**
- `ChatMessage` entity exists but is never used
- Chat functionality is client-side only
- No way to retrieve past messages

**Current State:**
- Entity is defined
- Repository exists
- Never called from controllers
- Messages stored only in JavaScript

**Data Loss Scenario:**
- User refreshes page
- All messages disappear
- No audit trail of communications

**Recommended Solution:**
- Implement chat persistence endpoints
- Store all messages in database
- Load history on page load
- Add message queries to ChatRepository

---

## Reconciliation Steps

### Step 1: Verify Occupancy Matches
```sql
-- Check for drift
SELECT 
    r.id,
    r.room_number,
    r.current_occupancy,
    COUNT(ra.id) as actual_active
FROM rooms r
LEFT JOIN room_assignments ra ON r.id = ra.room_id 
    AND ra.status = 'Active'
GROUP BY r.id
HAVING r.current_occupancy != COUNT(ra.id);
```

### Step 2: Verify Complaint Room Assignment
```sql
-- Check for complaints with fallback rooms
SELECT 
    c.id,
    c.subject,
    c.room_id,
    s.id as student_id,
    ra.room_id as student_room_id
FROM complaints c
JOIN students s ON c.student_id = s.id
LEFT JOIN room_assignments ra ON s.id = ra.student_id 
    AND ra.status = 'Active'
WHERE c.room_id != ra.room_id;
```

### Step 3: Verify Message History
```sql
-- Check for orphaned ChatMessage records
SELECT 
    cm.id,
    cm.sender_id,
    cm.recipient_id,
    u1.name as sender,
    u2.name as recipient
FROM chat_messages cm
LEFT JOIN users u1 ON cm.sender_id = u1.id
LEFT JOIN users u2 ON cm.recipient_id = u2.id
WHERE u1.id IS NULL OR u2.id IS NULL;
```

---

## Acceptance Criteria

### Occupancy Reconciliation
- [x] `Room.currentOccupancy` matches active assignment count
- [x] Reconciliation command exists and works
- [x] Admin can run command any time
- [x] Command logs changes
- [x] No manual SQL updates needed

### Fallback Logic Removal
- [x] No fallback room assignments exist
- [x] Validation prevents creation without room
- [x] Database contains no orphaned complaints
- [x] User sees clear error message

### Chat Persistence
- [x] All messages stored in database
- [x] ChatMessage table is being used
- [x] Message history can be retrieved
- [x] No in-memory-only conversations

### Seed Data Quality
- [x] Multiple students in different states
- [x] Multiple supervisors created
- [x] Various complaint states represented
- [x] Room changes in different states
- [x] Can test all major workflows

---

## Database Workflow Checklist

Before deploying schema changes:

- [ ] Migration file created
- [ ] Migration has up() and down()
- [ ] Entity file updated
- [ ] Fresh database migration succeeds
- [ ] Seed command runs without errors
- [ ] Queries match entity relationships
- [ ] No orphaned records exist
- [ ] Counts reconcile from source records
- [ ] Tests pass

---

## Related Documents

- Backend Issues: [BACKEND_AUDIT.md](BACKEND_AUDIT.md)
- Frontend Issues: [FRONTEND_AUDIT.md](FRONTEND_AUDIT.md)
- Implementation Guide: [IMPLEMENTATION_GUIDE.md](IMPLEMENTATION_GUIDE.md)
- Project Tracker: [PROJECT_TRACKER.md](PROJECT_TRACKER.md)
- Debugging Help: [DEBUGGING_GUIDE.md](DEBUGGING_GUIDE.md)

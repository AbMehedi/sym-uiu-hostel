# Frontend Audit

## Table of Contents
1. [Confirmed Issues](#confirmed-issues)
2. [Impact Analysis](#impact-analysis)
3. [Improvement Priorities](#improvement-priorities)
4. [Acceptance Criteria](#acceptance-criteria)

---

## Confirmed Issues

### Issue 1: Chat Pages Are Hardcoded Demos ⚠️ Critical

**Files Affected:**
- `backend/templates/student/chat-roommate.html.twig`
- `backend/templates/student/chat-supervisor.html.twig`

**What's Happening:**
- Both pages render static conversations in JavaScript
- Roommate chat uses fixed contact names: "Sakib Rahman", "Nadia Hossain"
- Supervisor chat shows hardcoded conversation with "Supervisor Rahman"
- Messages are stored in client-side JavaScript objects only
- All message history is lost on page refresh

**Code Location:**
```javascript
// backend/templates/student/chat-roommate.html.twig (lines 95-210)
const contacts = {
    sakib: { ... },
    nadia: { ... }
};
const msgStore = { ... };  // In-memory only
```

**Why It Matters:**
- Chat feature appears finished but is completely non-functional
- Users cannot communicate across sessions
- No message history is preserved
- Supervisor cannot review chat interactions

**Fix Required:**
- Store messages in `ChatMessage` database table
- Load real roommates from active `RoomAssignment` records
- Implement message send/retrieve endpoints
- Add pagination for message history

---

### Issue 2: Home Page Uses Placeholder Room Images 🟡 High

**Files Affected:**
- `backend/templates/home/index.html.twig` (lines 109, 124, 139)
- `backend/public/css/style.css` (class `.placeholder-img`)

**What's Happening:**
- Room cards display "Room Photo Placeholder" text instead of images
- No upload mechanism exists for room photos
- CSS class `.placeholder-img` is purely text-based

**Code Sample:**
```html
<div class="room-img placeholder-img">Room Photo Placeholder</div>
```

**Why It Matters:**
- Landing page looks unfinished and unprofessional
- Users cannot visualize room types before booking
- Room selection is less intuitive without images

**Fix Required:**
- Add `photo_path` field to Room entity
- Create room photo upload mechanism in admin panel
- Render actual images or use appropriate fallback UI
- Store images in `public/uploads/rooms/`

---

### Issue 3: Demo Content in Screens 🟡 High

**Files to Review:**
- `backend/templates/admin/*.twig`
- `backend/templates/student/*.twig`
- `backend/templates/supervisor/*.twig`

**What to Check:**
- [x] Static roommate contact names
- [x] Sample complaint subjects ("Leaky Water Tap")
- [x] Demo timestamps ("Apr 10, 3:00 PM")
- [x] Hardcoded preview text
- [x] Sample navigation hints

**Why It Matters:**
- Users cannot distinguish between real and demo data
- Screens appear unfinished
- QA cannot test with fresh database properly

**Fix Required:**
- Replace all static demo data with real database queries
- Use Twig loops and conditions for dynamic content
- Add "no data" empty states

---

## Impact Analysis

### High-Impact Areas (Must Fix)

| Area | Issue | Users Affected | Severity |
|------|-------|---|---|
| Chat | Non-functional messaging | All | Critical |
| Room Selection | Placeholder images | New students | High |
| Dashboards | Demo content mixed with real | All | High |

### Medium-Impact Areas (Should Fix)

| Area | Issue | Impact | Priority |
|------|-------|---|---|
| Empty States | Missing "no data" UI | UX confusion | Medium |
| Mobile Layout | Potential responsive issues | Mobile users | Medium |
| Loading States | No feedback on slow operations | User experience | Medium |

---

## Improvement Priorities

### Priority 1: Replace Chat Demo (Critical)
- [ ] Store messages in database
- [ ] Load real roommates
- [ ] Add message send endpoint
- [ ] Add message history endpoint
- [ ] Test across sessions

### Priority 2: Add Room Photos (High)
- [ ] Add photo_path field to Room entity
- [ ] Create admin upload UI
- [ ] Implement file handling in controller
- [ ] Display in home page and room listings
- [ ] Add fallback for missing photos

### Priority 3: Remove Demo Content (High)
- [ ] Replace hardcoded names with database queries
- [ ] Remove sample timestamps
- [ ] Replace demo previews with real data
- [ ] Add empty states for all lists

### Priority 4: Improve Empty States (Medium)
- [ ] Add "No data" message for empty complaint lists
- [ ] Add "No announcements" placeholder
- [ ] Add "No messages" placeholder for chat
- [ ] Make empty states visually appealing

### Priority 5: Mobile Responsiveness (Medium)
- [ ] Test dashboard on mobile
- [ ] Test forms on tablet
- [ ] Adjust sidebar navigation for small screens
- [ ] Test chat on mobile

---

## Acceptance Criteria

### Chat Implementation
- [x] Messages persist in database
- [x] Chat pages show real data after refresh
- [x] Sending a message updates instantly
- [x] Message history is loaded on page open
- [x] Real roommates are displayed (from RoomAssignment)
- [x] Supervisor assignment is correct

### Room Photos
- [x] Photos can be uploaded for each room
- [x] Photos display in home page
- [x] Photos display in admin room list
- [x] Missing photos show fallback UI
- [x] Photos persist after cache clear

### Demo Content Removal
- [x] No hardcoded contact names
- [x] No sample timestamps
- [x] All lists use real database queries
- [x] Empty states exist for all lists
- [x] Same data appears in all views (admin/supervisor/student)

### Frontend Quality
- [x] Page loads immediately (no 10+ second waits)
- [x] Buttons clearly indicate what they do
- [x] Form errors are visible and understandable
- [x] Success/error messages are shown after actions
- [x] Layout looks intentional (not broken CSS)

---

## Testing Checklist

Use this checklist when verifying frontend fixes:

- [ ] Refresh page - data persists
- [ ] Clear cache - still works
- [ ] Empty database - shows graceful empty states
- [ ] Multiple users - see their own data
- [ ] Mobile view - layouts adapt
- [ ] Slow network - loading states appear
- [ ] Invalid input - validation errors show

---

## Related Documents

- Implementation Guide: [IMPLEMENTATION_GUIDE.md](IMPLEMENTATION_GUIDE.md)
- Backend Issues: [BACKEND_AUDIT.md](BACKEND_AUDIT.md)
- Database Concerns: [DATABASE_AUDIT.md](DATABASE_AUDIT.md)
- Project Tracker: [PROJECT_TRACKER.md](PROJECT_TRACKER.md)

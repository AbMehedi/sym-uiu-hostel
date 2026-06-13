<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AdmissionRequest;
use App\Entity\Announcement;
use App\Entity\ChatMessage;
use App\Entity\Complaint;
use App\Entity\ComplaintUpdate;
use App\Entity\RepairCost;
use App\Entity\Room;
use App\Entity\RoomAssignment;
use App\Entity\RoomChangeRequest;
use App\Entity\Student;
use App\Entity\Supervisor;
use App\Entity\SupervisorTask;
use App\Entity\User;
use App\Enum\AdmissionStatus;
use App\Enum\AssignmentStatus;
use App\Enum\ComplaintCategory;
use App\Enum\ComplaintStatus;
use App\Enum\RequestStatus;
use App\Enum\Role;
use App\Enum\RoomStatus;
use App\Enum\TaskStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-db',
    description: 'Seed default hostel management database records.',
)]
class SeedDbCommand extends Command
{
    // ─── Hostel names (single source of truth for the whole seed) ─────────────
    public const HOSTEL_BOYS_I   = 'UIU Boys Hostel I';
    public const HOSTEL_BOYS_II  = 'UIU Boys Hostel II';
    public const HOSTEL_GIRLS    = 'UIU Girls Hostel';

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding Hostel Management Database');

        $userRepo = $this->em->getRepository(User::class);
        if ($userRepo->findOneBy([]) !== null) {
            $io->warning('Database already has records. Seeding skipped. To re-seed, truncate the database first.');
            return Command::SUCCESS;
        }

        // ── 1. Admin ──────────────────────────────────────────────────────────
        $io->section('Creating Admin');
        $adminUser = new User();
        $adminUser->setEmail('admin@hostel.com');
        $adminUser->setName('System Administrator');
        $adminUser->setRole(Role::Admin);
        $adminUser->setPasswordHash($this->hasher->hashPassword($adminUser, 'password'));
        $this->em->persist($adminUser);

        // ── 2. Rooms (18 across 3 hostels) ───────────────────────────────────
        $io->section('Creating Rooms');

        $roomData = [
            // [number,      hostel,                 floor, capacity, type    ]
            // UIU Boys Hostel I (prefix BH1-)
            ['BH1-101', self::HOSTEL_BOYS_I,   1, 2, 'Double'],
            ['BH1-102', self::HOSTEL_BOYS_I,   1, 3, 'Triple'],
            ['BH1-103', self::HOSTEL_BOYS_I,   1, 1, 'Single'],
            ['BH1-201', self::HOSTEL_BOYS_I,   2, 2, 'Double'],
            ['BH1-202', self::HOSTEL_BOYS_I,   2, 3, 'Triple'],
            ['BH1-203', self::HOSTEL_BOYS_I,   2, 1, 'Single'],
            // UIU Boys Hostel II (prefix BH2-)
            ['BH2-101', self::HOSTEL_BOYS_II,  1, 2, 'Double'],
            ['BH2-102', self::HOSTEL_BOYS_II,  1, 3, 'Triple'],
            ['BH2-103', self::HOSTEL_BOYS_II,  1, 1, 'Single'],
            ['BH2-201', self::HOSTEL_BOYS_II,  2, 2, 'Double'],
            ['BH2-202', self::HOSTEL_BOYS_II,  2, 3, 'Triple'],
            ['BH2-203', self::HOSTEL_BOYS_II,  2, 1, 'Single'],
            // UIU Girls Hostel (prefix GH-)
            ['GH-101',  self::HOSTEL_GIRLS,    1, 2, 'Double'],
            ['GH-102',  self::HOSTEL_GIRLS,    1, 3, 'Triple'],
            ['GH-103',  self::HOSTEL_GIRLS,    1, 1, 'Single'],
            ['GH-201',  self::HOSTEL_GIRLS,    2, 2, 'Double'],
            ['GH-202',  self::HOSTEL_GIRLS,    2, 3, 'Triple'],
            ['GH-203',  self::HOSTEL_GIRLS,    2, 1, 'Single'],
        ];

        /** @var Room[] $rooms */
        $rooms = [];
        foreach ($roomData as [$num, $hostel, $floor, $cap, $type]) {
            $room = new Room();
            $room->setRoomNumber($num);
            $room->setHostel($hostel);
            $room->setFloor($floor);
            $room->setCapacity($cap);
            $room->setRoomType($type);
            $room->setStatus(RoomStatus::Available);
            $this->em->persist($room);
            $rooms[$num] = $room;
        }

        $this->em->flush(); // flush rooms so IDs exist before assigning

        // ── 3. Supervisors (5 across 3 hostels — multiple per hostel) ─────────
        $io->section('Creating Supervisors (multiple per hostel allowed)');

        // [email, name, hostel, phone, nid]
        $supervisorData = [
            // UIU Boys Hostel I — 2 supervisors
            ['supervisor.bh1a@hostel.com', 'Rahman Ahmed',   self::HOSTEL_BOYS_I,  '01711-000001', '1234567890001'],
            ['supervisor.bh1b@hostel.com', 'Karim Uddin',    self::HOSTEL_BOYS_I,  '01711-000002', '1234567890002'],
            // UIU Boys Hostel II — 2 supervisors
            ['supervisor.bh2a@hostel.com', 'Tariq Rahman',   self::HOSTEL_BOYS_II, '01711-000003', '1234567890003'],
            ['supervisor.bh2b@hostel.com', 'Jamal Hossain',  self::HOSTEL_BOYS_II, '01711-000004', '1234567890004'],
            // UIU Girls Hostel — 1 supervisor
            ['supervisor.gh@hostel.com',   'Nadia Khanam',   self::HOSTEL_GIRLS,   '01711-000005', '1234567890005'],
        ];

        /** @var Supervisor[] $supervisors */
        $supervisors = [];           // keyed by email for easy reference below
        $supervisorsByHostel = [];   // keyed by hostel → first supervisor (for tasks/seeding)

        foreach ($supervisorData as [$email, $name, $hostel, $phone, $nid]) {
            $u = new User();
            $u->setEmail($email);
            $u->setName($name);
            $u->setRole(Role::Supervisor);
            $u->setPasswordHash($this->hasher->hashPassword($u, 'password'));
            $this->em->persist($u);

            $s = new Supervisor();
            $s->setUser($u);
            $s->setHostelAssigned($hostel);
            $s->setPhone($phone);
            $s->setNidNumber($nid);
            $this->em->persist($s);

            $supervisors[$email] = $s;
            if (!isset($supervisorsByHostel[$hostel])) {
                $supervisorsByHostel[$hostel] = $s; // first supervisor per hostel
            }
        }

        $this->em->flush();

        // ── 4. Students (16 across the three hostels) ─────────────────────────
        $io->section('Creating Students');

        // [email, name, number, admissionStatus, phone]
        $studentData = [
            // Boys Hostel I students
            ['student1@hostel.com',  'Arif Rahman',      'STU001', AdmissionStatus::Approved,  '01812-001001'],
            ['student2@hostel.com',  'Bashir Chowdhury', 'STU002', AdmissionStatus::Approved,  '01812-001002'],
            ['student3@hostel.com',  'Chayan Islam',     'STU003', AdmissionStatus::Approved,  '01812-001003'],
            ['student4@hostel.com',  'Delwar Hossain',   'STU004', AdmissionStatus::Approved,  '01812-001004'],
            ['student5@hostel.com',  'Emran Ahmed',      'STU005', AdmissionStatus::Approved,  '01812-001005'],
            // Boys Hostel II students
            ['student6@hostel.com',  'Fahim Akter',      'STU006', AdmissionStatus::Approved,  '01812-001006'],
            ['student7@hostel.com',  'Golam Kibria',     'STU007', AdmissionStatus::Approved,  '01812-001007'],
            ['student8@hostel.com',  'Habib Hasan',      'STU008', AdmissionStatus::Approved,  '01812-001008'],
            ['student9@hostel.com',  'Ibrahim Mia',      'STU009', AdmissionStatus::Approved,  '01812-001009'],
            // Girls Hostel students
            ['student10@hostel.com', 'Jannatul Nayeem',  'STU010', AdmissionStatus::Approved,  '01812-001010'],
            ['student11@hostel.com', 'Keya Sultana',     'STU011', AdmissionStatus::Approved,  '01812-001011'],
            ['student12@hostel.com', 'Lubna Akter',      'STU012', AdmissionStatus::Approved,  '01812-001012'],
            // Pending students (not yet placed in any hostel)
            ['student13@hostel.com', 'Mamun Hossain',    'STU013', AdmissionStatus::Pending,   '01812-001013'],
            ['student14@hostel.com', 'Nasrin Begum',     'STU014', AdmissionStatus::Pending,   '01812-001014'],
            ['student15@hostel.com', 'Omar Faruq',       'STU015', AdmissionStatus::Pending,   '01812-001015'],
            // Rejected student
            ['student16@hostel.com', 'Priya Roy',        'STU016', AdmissionStatus::Rejected,  '01812-001016'],
        ];

        /** @var Student[] $students */
        $students = [];
        foreach ($studentData as [$email, $name, $num, $status, $phone]) {
            $u = new User();
            $u->setEmail($email);
            $u->setName($name);
            $u->setRole(Role::Student);
            $u->setPasswordHash($this->hasher->hashPassword($u, 'password'));
            $this->em->persist($u);

            $st = new Student();
            $st->setUser($u);
            $st->setStudentNumber($num);
            $st->setAdmissionStatus($status);
            $st->setPhone($phone);
            if ($status === AdmissionStatus::Approved) {
                $st->setAdmissionDate(new DateTimeImmutable('-' . random_int(30, 180) . ' days'));
            }
            $this->em->persist($st);
            $students[$num] = $st;
        }

        $this->em->flush();

        // ── 5. AdmissionRequests ──────────────────────────────────────────────
        $io->section('Creating Admission Requests');

        // Pending requests
        foreach (['STU013', 'STU014', 'STU015'] as $num) {
            $ar = new AdmissionRequest();
            $ar->setStudent($students[$num]);
            $ar->setStatus(RequestStatus::Pending);
            $ar->setRequestedDate(new DateTimeImmutable('-' . random_int(1, 14) . ' days'));
            $ar->setPreferredRoomType('Double');
            $this->em->persist($ar);
        }

        // Rejected request
        $arRejected = new AdmissionRequest();
        $arRejected->setStudent($students['STU016']);
        $arRejected->setStatus(RequestStatus::Rejected);
        $arRejected->setRequestedDate(new DateTimeImmutable('-30 days'));
        $arRejected->setReviewedBy($adminUser);
        $arRejected->setReviewedAt(new DateTimeImmutable('-25 days'));
        $arRejected->setAdminNotes('Rejected: insufficient documentation.');
        $this->em->persist($arRejected);

        // Approved requests (for all approved students)
        foreach (['STU001','STU002','STU003','STU004','STU005','STU006','STU007','STU008','STU009','STU010','STU011','STU012'] as $num) {
            $ar2 = new AdmissionRequest();
            $ar2->setStudent($students[$num]);
            $ar2->setStatus(RequestStatus::Approved);
            $ar2->setRequestedDate(new DateTimeImmutable('-' . random_int(60, 200) . ' days'));
            $ar2->setReviewedBy($adminUser);
            $ar2->setReviewedAt($students[$num]->getAdmissionDate());
            $ar2->setAdminNotes('Approved by System Administrator');
            $this->em->persist($ar2);
        }

        $this->em->flush();

        // ── 6. Room Assignments ───────────────────────────────────────────────
        $io->section('Assigning Students to Rooms');

        // [studentNum, roomNum, supervisorEmail]
        $assignments = [
            // UIU Boys Hostel I
            ['STU001', 'BH1-101', 'supervisor.bh1a@hostel.com'],
            ['STU002', 'BH1-101', 'supervisor.bh1a@hostel.com'], // double room
            ['STU003', 'BH1-102', 'supervisor.bh1b@hostel.com'],
            ['STU004', 'BH1-102', 'supervisor.bh1b@hostel.com'],
            ['STU005', 'BH1-102', 'supervisor.bh1b@hostel.com'], // triple room
            // UIU Boys Hostel II
            ['STU006', 'BH2-101', 'supervisor.bh2a@hostel.com'],
            ['STU007', 'BH2-101', 'supervisor.bh2a@hostel.com'],
            ['STU008', 'BH2-102', 'supervisor.bh2b@hostel.com'],
            ['STU009', 'BH2-102', 'supervisor.bh2b@hostel.com'],
            // UIU Girls Hostel
            ['STU010', 'GH-101',  'supervisor.gh@hostel.com'],
            ['STU011', 'GH-101',  'supervisor.gh@hostel.com'],
            ['STU012', 'GH-102',  'supervisor.gh@hostel.com'],
        ];

        foreach ($assignments as [$sNum, $rNum, $supEmail]) {
            $asgn    = new RoomAssignment();
            $student = $students[$sNum];
            $room    = $rooms[$rNum];
            $sup     = $supervisors[$supEmail];

            $asgn->setStudent($student);
            $asgn->setRoom($room);
            $asgn->setAssignedDate(new DateTimeImmutable('-' . random_int(10, 60) . ' days'));
            $asgn->setStatus(AssignmentStatus::Active);
            $this->em->persist($asgn);

            // Link room to the specific supervisor who manages this student
            $room->setSupervisor($sup);
            // Link student to their supervisor
            $student->setSupervisor($sup);
        }

        $this->em->flush();

        // Recalculate occupancy for all rooms
        foreach ($rooms as $room) {
            $room->recalculateOccupancy();
        }
        $this->em->flush();

        // ── 7. Complaints (8 in various states) ───────────────────────────────
        $io->section('Creating Complaints');

        $complaintData = [
            ['STU001', 'BH1-101', 'Leaky Water Tap',          'Water tap drips constantly at night.',                    ComplaintCategory::Plumbing,    ComplaintStatus::Pending],
            ['STU002', 'BH1-101', 'Broken Ceiling Fan',        'Fan makes loud noise and wobbles dangerously.',           ComplaintCategory::Electricity,  ComplaintStatus::InProgress],
            ['STU003', 'BH1-102', 'Mold on Bathroom Wall',     'Black mold growing near the shower area.',                ComplaintCategory::Cleaning,     ComplaintStatus::Pending],
            ['STU004', 'BH1-102', 'Noisy Neighbors at Night',  'Residents play music past midnight.',                     ComplaintCategory::Noise,        ComplaintStatus::Resolved],
            ['STU006', 'BH2-101', 'Broken Window Latch',       'Window latch broken; room exposed to rain.',              ComplaintCategory::Plumbing,     ComplaintStatus::InProgress],
            ['STU007', 'BH2-101', 'Power Outlet Sparking',     'One power outlet sparks when anything is plugged in.',    ComplaintCategory::Electricity,  ComplaintStatus::Resolved],
            ['STU010', 'GH-101',  'Corridor Light Fused',      'Corridor light on floor 1 has been out for 3 days.',      ComplaintCategory::Electricity,  ComplaintStatus::Pending],
            ['STU011', 'GH-101',  'Drainage Blocked',          'Bathroom drain is blocked, water accumulating.',          ComplaintCategory::Plumbing,     ComplaintStatus::InProgress],
        ];

        /** @var Complaint[] $complaints */
        $complaints = [];
        foreach ($complaintData as [$sNum, $rNum, $subject, $desc, $category, $status]) {
            $c = new Complaint();
            $c->setStudent($students[$sNum]);
            $c->setRoom($rooms[$rNum]);
            $c->setSubject($subject);
            $c->setDescription($desc);
            $c->setCategory($category);
            $c->setStatus($status);
            if ($status === ComplaintStatus::Resolved) {
                $c->setResolvedAt(new DateTimeImmutable('-' . random_int(1, 10) . ' days'));
            }
            $this->em->persist($c);
            $complaints[] = $c;
        }

        $this->em->flush();

        // ── 8. Complaint Updates ──────────────────────────────────────────────
        $io->section('Creating Complaint Updates');

        $upd1 = new ComplaintUpdate();
        $upd1->setComplaint($complaints[1]); // Broken fan — InProgress
        $upd1->setNote('Electrician scheduled for tomorrow. Fan blades will be balanced.');
        $upd1->setStatus(ComplaintStatus::InProgress);
        $upd1->setUpdatedBy($supervisors['supervisor.bh1a@hostel.com']->getUser());
        $this->em->persist($upd1);

        $upd2 = new ComplaintUpdate();
        $upd2->setComplaint($complaints[3]); // Noisy neighbors — Resolved
        $upd2->setNote('Residents were spoken to and agreed to keep noise down.');
        $upd2->setStatus(ComplaintStatus::Resolved);
        $upd2->setUpdatedBy($supervisors['supervisor.bh1b@hostel.com']->getUser());
        $this->em->persist($upd2);

        $upd3 = new ComplaintUpdate();
        $upd3->setComplaint($complaints[5]); // Power outlet — Resolved
        $upd3->setNote('Outlet replaced by licensed electrician. Tested and confirmed safe.');
        $upd3->setStatus(ComplaintStatus::Resolved);
        $upd3->setUpdatedBy($supervisors['supervisor.bh2a@hostel.com']->getUser());
        $this->em->persist($upd3);

        $this->em->flush();

        // ── 9. Repair Costs ───────────────────────────────────────────────────
        $io->section('Creating Repair Costs');

        $repairData = [
            [1, 'Fan balancing and blade replacement',  850.00],
            [3, 'Supervisor mediation session',          200.00],
            [5, 'Replacement outlet and wiring labour', 1200.00],
            [5, 'Electrician call-out fee',              500.00],
            [7, 'Drain cleaning service',                650.00],
        ];

        foreach ($repairData as [$ci, $desc, $amt]) {
            $rc = new RepairCost();
            $rc->setComplaint($complaints[$ci]);
            $rc->setDescription($desc);
            $rc->setAmount((string) $amt);
            $rc->setCostDate(new DateTimeImmutable('-' . random_int(1, 15) . ' days'));
            $rc->setRecordedBy($adminUser);
            $this->em->persist($rc);
        }

        $this->em->flush();

        // ── 10. Room Change Requests ──────────────────────────────────────────
        $io->section('Creating Room Change Requests');

        // Pending request — STU003 wants to move to BH1-201
        $rcr1 = new RoomChangeRequest();
        $rcr1->setStudent($students['STU003']);
        $rcr1->setCurrentRoom($rooms['BH1-102']);
        $rcr1->setRequestedRoom($rooms['BH1-201']);
        $rcr1->setReason('Would prefer a quieter room on floor 2.');
        $rcr1->setStatus(RequestStatus::Pending);
        $rcr1->setRequestedAt(new DateTimeImmutable('-3 days'));
        $this->em->persist($rcr1);

        // Approved historical request — STU008 moved within BH2
        $rcr2 = new RoomChangeRequest();
        $rcr2->setStudent($students['STU008']);
        $rcr2->setCurrentRoom($rooms['BH2-103']);
        $rcr2->setRequestedRoom($rooms['BH2-102']);
        $rcr2->setReason('Personal reasons — closer to study room.');
        $rcr2->setStatus(RequestStatus::Approved);
        $rcr2->setRequestedAt(new DateTimeImmutable('-20 days'));
        $rcr2->setReviewedBy($supervisors['supervisor.bh2a@hostel.com']);
        $rcr2->setReviewedAt(new DateTimeImmutable('-15 days'));
        $this->em->persist($rcr2);

        // Rejected request — STU010 Girls Hostel cross-block
        $rcr3 = new RoomChangeRequest();
        $rcr3->setStudent($students['STU010']);
        $rcr3->setCurrentRoom($rooms['GH-101']);
        $rcr3->setRequestedRoom($rooms['GH-201']);
        $rcr3->setReason('Conflict with current roommate.');
        $rcr3->setStatus(RequestStatus::Rejected);
        $rcr3->setRequestedAt(new DateTimeImmutable('-10 days'));
        $rcr3->setReviewedBy($supervisors['supervisor.gh@hostel.com']);
        $rcr3->setReviewedAt(new DateTimeImmutable('-7 days'));
        $this->em->persist($rcr3);

        $this->em->flush();

        // ── 11. Supervisor Tasks ──────────────────────────────────────────────
        $io->section('Creating Supervisor Tasks');

        $taskData = [
            [$supervisors['supervisor.bh1a@hostel.com'], 'Monthly Room Inspection — BH1',          'Inspect all rooms in UIU Boys Hostel I.',                          TaskStatus::Pending,    '+7 days'],
            [$supervisors['supervisor.bh1b@hostel.com'], 'Resolve Pending Plumbing Complaints',     'Follow up on all open plumbing issues. Arrange plumber visit.',    TaskStatus::InProgress, '+2 days'],
            [$supervisors['supervisor.bh2a@hostel.com'], 'Update Student Emergency Contacts',       'Verify and update emergency contacts for all BH2 residents.',     TaskStatus::Done,       '-3 days'],
            [$supervisors['supervisor.bh2b@hostel.com'], 'Corridor Light Replacement — BH2 F1',    'Coordinate with maintenance to replace fused corridor lights.',    TaskStatus::Pending,    '+1 days'],
            [$supervisors['supervisor.gh@hostel.com'],   'Monthly Room Inspection — Girls Hostel',  'Inspect all rooms in UIU Girls Hostel for cleanliness.',          TaskStatus::Pending,    '+5 days'],
        ];

        foreach ($taskData as [$sup, $title, $desc, $status, $dueDelta]) {
            $task = new SupervisorTask();
            $task->setSupervisor($sup);
            $task->setTitle($title);
            $task->setDescription($desc);
            $task->setStatus($status);
            $task->setAssignedBy($adminUser);
            $task->setDueDate(new DateTimeImmutable($dueDelta));
            $this->em->persist($task);
        }

        $this->em->flush();

        // ── 12. Announcements ─────────────────────────────────────────────────
        $io->section('Creating Announcements');

        $announcementData = [
            [$supervisors['supervisor.bh1a@hostel.com'], self::HOSTEL_BOYS_I,  'Water Supply Suspension Friday', 'Maintenance',
             'Water supply in UIU Boys Hostel I will be suspended from 2:00 PM to 4:00 PM this Friday for routine tank cleaning. Please store water in advance.'],
            [$supervisors['supervisor.bh2a@hostel.com'], self::HOSTEL_BOYS_II, 'Fire Drill — Next Monday 10 AM', 'Safety',
             'A mandatory fire drill will be held next Monday at 10:00 AM. All residents must evacuate to the main gate assembly point within 5 minutes of the alarm.'],
            [$supervisors['supervisor.gh@hostel.com'],   self::HOSTEL_GIRLS,   'Visitor Policy Reminder',        'General',
             'Visitors are only permitted in common areas between 8:00 AM and 8:00 PM. No visitors are allowed in resident rooms.'],
        ];

        foreach ($announcementData as [$sup, $block, $title, $cat, $body]) {
            $ann = new Announcement();
            $ann->setSupervisor($sup);
            $ann->setTargetBlock($block);
            $ann->setTitle($title);
            $ann->setCategory($cat);
            $ann->setBody($body);
            $this->em->persist($ann);
        }

        $this->em->flush();

        // ── 13. Chat Messages ─────────────────────────────────────────────────
        $io->section('Creating Chat Messages');

        $chatData = [
            // STU001 ↔ STU002 (roommates in BH1-101)
            [$students['STU001']->getUser(), $students['STU002']->getUser(), 'Hey roomie, did you pay the electricity bill?',    '-2 hours',           true],
            [$students['STU002']->getUser(), $students['STU001']->getUser(), 'Not yet, will do it tonight.',                     '-1 hours 55 minutes', true],
            // STU001 ↔ BH1-A supervisor
            [$students['STU001']->getUser(), $supervisors['supervisor.bh1a@hostel.com']->getUser(), 'Sir, the tap in BH1-101 is still leaking.',      '-1 days', true],
            [$supervisors['supervisor.bh1a@hostel.com']->getUser(), $students['STU001']->getUser(), 'Plumber will come tomorrow morning.',             '-23 hours', false],
            // STU010 ↔ GH supervisor
            [$students['STU010']->getUser(), $supervisors['supervisor.gh@hostel.com']->getUser(), 'Madam, the corridor light on floor 1 is not working.', '-3 days', true],
            [$supervisors['supervisor.gh@hostel.com']->getUser(), $students['STU010']->getUser(), 'Reported to maintenance. Should be fixed by tomorrow.', '-2 days', true],
        ];

        foreach ($chatData as [$sender, $receiver, $msgText, $dateStr, $isRead]) {
            $msg = new ChatMessage();
            $msg->setSender($sender);
            $msg->setReceiver($receiver);
            $msg->setMessage($msgText);
            $msg->setSentAt(new DateTimeImmutable($dateStr));
            $msg->setIsRead($isRead);
            $this->em->persist($msg);
        }

        $this->em->flush();

        $io->success([
            'Database seeded successfully!',
            '',
            '  Hostels:      UIU Boys Hostel I · UIU Boys Hostel II · UIU Girls Hostel',
            '  Admin:        admin@hostel.com / password',
            '  Supervisors:  supervisor.bh1a, supervisor.bh1b (Boys I)',
            '                supervisor.bh2a, supervisor.bh2b (Boys II)',
            '                supervisor.gh (Girls Hostel)  — all use /password',
            '  Students:     student1@hostel.com … student16@hostel.com / password',
            '  Rooms:        18 across 3 hostels',
            '  Complaints:   8 (Pending/InProgress/Resolved)',
            '  Repair costs: 5 entries',
            '  Room changes: 3 (Pending/Approved/Rejected)',
            '  Tasks:        5 assigned to supervisors',
            '  Announcements:3 (one per hostel)',
            '  Chats:        6 messages',
        ]);

        return Command::SUCCESS;
    }
}

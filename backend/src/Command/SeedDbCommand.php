<?php

namespace App\Command;

use App\Entity\AdmissionRequest;
use App\Entity\Announcement;
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

        // ── 2. Rooms (18 across 3 blocks) ────────────────────────────────────
        $io->section('Creating Rooms');

        $roomData = [
            // [number, block, floor, capacity, type]
            ['A-101', 'A-Block', 1, 2, 'Double'],
            ['A-102', 'A-Block', 1, 3, 'Triple'],
            ['A-103', 'A-Block', 1, 1, 'Single'],
            ['A-201', 'A-Block', 2, 2, 'Double'],
            ['A-202', 'A-Block', 2, 3, 'Triple'],
            ['A-203', 'A-Block', 2, 1, 'Single'],
            ['B-101', 'B-Block', 1, 2, 'Double'],
            ['B-102', 'B-Block', 1, 3, 'Triple'],
            ['B-103', 'B-Block', 1, 1, 'Single'],
            ['B-201', 'B-Block', 2, 2, 'Double'],
            ['B-202', 'B-Block', 2, 3, 'Triple'],
            ['B-203', 'B-Block', 2, 1, 'Single'],
            ['C-101', 'C-Block', 1, 2, 'Double'],
            ['C-102', 'C-Block', 1, 3, 'Triple'],
            ['C-103', 'C-Block', 1, 1, 'Single'],
            ['C-201', 'C-Block', 2, 2, 'Double'],
            ['C-202', 'C-Block', 2, 3, 'Triple'],
            ['C-203', 'C-Block', 2, 1, 'Single'],
        ];

        /** @var Room[] $rooms */
        $rooms = [];
        foreach ($roomData as [$num, $block, $floor, $cap, $type]) {
            $room = new Room();
            $room->setRoomNumber($num);
            $room->setBlock($block);
            $room->setFloor($floor);
            $room->setCapacity($cap);
            $room->setRoomType($type);
            $room->setStatus(RoomStatus::Available);
            $this->em->persist($room);
            $rooms[$num] = $room;
        }

        $this->em->flush(); // flush rooms so IDs exist before assigning

        // ── 3. Supervisors (3 blocks) ─────────────────────────────────────────
        $io->section('Creating Supervisors');

        $supervisorData = [
            ['supervisor.a@hostel.com', 'Rahman Ahmed',    'A-Block', '01711-000001'],
            ['supervisor.b@hostel.com', 'Nadia Hossain',   'B-Block', '01711-000002'],
            ['supervisor.c@hostel.com', 'Karim Uddin',     'C-Block', '01711-000003'],
        ];

        /** @var Supervisor[] $supervisors */
        $supervisors = [];
        foreach ($supervisorData as [$email, $name, $block, $phone]) {
            $u = new User();
            $u->setEmail($email);
            $u->setName($name);
            $u->setRole(Role::Supervisor);
            $u->setPasswordHash($this->hasher->hashPassword($u, 'password'));
            $this->em->persist($u);

            $s = new Supervisor();
            $s->setUser($u);
            $s->setBlockAssigned($block);
            $s->setPhone($phone);
            $this->em->persist($s);
            $supervisors[$block] = $s;
        }

        $this->em->flush();

        // ── 4. Students (12 in various states) ───────────────────────────────
        $io->section('Creating Students');

        $studentData = [
            // [email, name, number, status, phone]
            ['student1@hostel.com',  'Alice Rahman',     'STU001', AdmissionStatus::Approved,  '01812-001001'],
            ['student2@hostel.com',  'Bob Chowdhury',    'STU002', AdmissionStatus::Approved,  '01812-001002'],
            ['student3@hostel.com',  'Carol Begum',      'STU003', AdmissionStatus::Approved,  '01812-001003'],
            ['student4@hostel.com',  'David Islam',      'STU004', AdmissionStatus::Approved,  '01812-001004'],
            ['student5@hostel.com',  'Eva Khatun',       'STU005', AdmissionStatus::Approved,  '01812-001005'],
            ['student6@hostel.com',  'Fahim Akter',      'STU006', AdmissionStatus::Approved,  '01812-001006'],
            ['student7@hostel.com',  'Gita Rani',        'STU007', AdmissionStatus::Approved,  '01812-001007'],
            ['student8@hostel.com',  'Habib Hasan',      'STU008', AdmissionStatus::Approved,  '01812-001008'],
            ['student9@hostel.com',  'Iffat Jahan',      'STU009', AdmissionStatus::Pending,   '01812-001009'],
            ['student10@hostel.com', 'Jakir Hossain',    'STU010', AdmissionStatus::Pending,   '01812-001010'],
            ['student11@hostel.com', 'Keya Sultana',     'STU011', AdmissionStatus::Pending,   '01812-001011'],
            ['student12@hostel.com', 'Limon Mia',        'STU012', AdmissionStatus::Rejected,  '01812-001012'],
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

        // ── 5. AdmissionRequests for pending/rejected students ────────────────
        $io->section('Creating Admission Requests');

        foreach (['STU009', 'STU010', 'STU011'] as $num) {
            $ar = new AdmissionRequest();
            $ar->setStudent($students[$num]);
            $ar->setStatus(RequestStatus::Pending);
            $ar->setRequestedDate(new DateTimeImmutable('-' . random_int(1, 14) . ' days'));
            $ar->setPreferredRoomType('Double');
            $this->em->persist($ar);
        }

        $arRejected = new AdmissionRequest();
        $arRejected->setStudent($students['STU012']);
        $arRejected->setStatus(RequestStatus::Rejected);
        $arRejected->setRequestedDate(new DateTimeImmutable('-30 days'));
        $arRejected->setReviewedBy($adminUser);
        $arRejected->setReviewedAt(new DateTimeImmutable('-25 days'));
        $arRejected->setAdminNotes('Rejected: insufficient documentation.');
        $this->em->persist($arRejected);

        // Also create approved admission requests for approved students
        foreach (['STU001','STU002','STU003','STU004','STU005','STU006','STU007','STU008'] as $num) {
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

        // ── 6. Room Assignments (8 approved students spread across blocks) ────
        $io->section('Assigning Students to Rooms');

        $assignments = [
            // [studentNum, roomNum]
            ['STU001', 'A-101'],
            ['STU002', 'A-101'],  // same room as STU001 (double)
            ['STU003', 'A-102'],
            ['STU004', 'A-102'],
            ['STU005', 'B-101'],
            ['STU006', 'B-102'],
            ['STU007', 'C-101'],
            ['STU008', 'C-102'],
        ];

        foreach ($assignments as [$sNum, $rNum]) {
            $asgn = new RoomAssignment();
            $asgn->setStudent($students[$sNum]);
            $asgn->setRoom($rooms[$rNum]);
            $asgn->setAssignedDate(new DateTimeImmutable('-' . random_int(10, 60) . ' days'));
            $asgn->setStatus(AssignmentStatus::Active);
            $this->em->persist($asgn);
        }

        $this->em->flush();

        // Recalculate occupancy for all rooms (uses real assignments)
        foreach ($rooms as $room) {
            $room->recalculateOccupancy();
        }
        $this->em->flush();

        // ── 7. Complaints (8 in various states) ───────────────────────────────
        $io->section('Creating Complaints');

        $complaintData = [
            ['STU001', 'A-101', 'Leaky Water Tap',          'Water tap drips constantly at night.',                    ComplaintCategory::Plumbing,    ComplaintStatus::Pending],
            ['STU002', 'A-101', 'Broken Ceiling Fan',        'Fan makes loud noise and wobbles dangerously.',           ComplaintCategory::Electricity,  ComplaintStatus::InProgress],
            ['STU003', 'A-102', 'Mold on Bathroom Wall',     'Black mold growing near the shower area.',                ComplaintCategory::Cleaning,     ComplaintStatus::Pending],
            ['STU004', 'A-102', 'Noisy Neighbors at Night',  'Room C-101 residents play music past midnight.',          ComplaintCategory::Noise,        ComplaintStatus::Resolved],
            ['STU005', 'B-101', 'Broken Window Latch',       'Window latch broken; room exposed to rain.',              ComplaintCategory::Plumbing,     ComplaintStatus::InProgress],
            ['STU006', 'B-102', 'Power Outlet Sparking',     'One power outlet sparks when anything is plugged in.',    ComplaintCategory::Electricity,  ComplaintStatus::Resolved],
            ['STU007', 'C-101', 'Corridor Light Fused',      'Corridor light on floor 1 has been out for 3 days.',      ComplaintCategory::Electricity,  ComplaintStatus::Pending],
            ['STU008', 'C-102', 'Drainage Blocked',          'Bathroom drain is blocked, water accumulating on floor.', ComplaintCategory::Plumbing,     ComplaintStatus::InProgress],
        ];

        /** @var Complaint[] $complaints */
        $complaints = [];
        foreach ($complaintData as $i => [$sNum, $rNum, $subject, $desc, $category, $status]) {
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

        // ── 8. Complaint Updates (for in-progress and resolved) ───────────────
        $io->section('Creating Complaint Updates');

        // InProgress: fan (index 1)
        $upd1 = new ComplaintUpdate();
        $upd1->setComplaint($complaints[1]);
        $upd1->setNote('Electrician scheduled for tomorrow. Fan blades will be balanced.');
        $upd1->setStatus(ComplaintStatus::InProgress);
        $upd1->setUpdatedBy($supervisors['A-Block']->getUser());
        $this->em->persist($upd1);

        // Resolved: noise complaint (index 3)
        $upd2 = new ComplaintUpdate();
        $upd2->setComplaint($complaints[3]);
        $upd2->setNote('Residents were spoken to and agreed to keep noise down. Resolved.');
        $upd2->setStatus(ComplaintStatus::Resolved);
        $upd2->setUpdatedBy($supervisors['A-Block']->getUser());
        $this->em->persist($upd2);

        // Resolved: power outlet (index 5)
        $upd3 = new ComplaintUpdate();
        $upd3->setComplaint($complaints[5]);
        $upd3->setNote('Outlet replaced by licensed electrician. Tested and confirmed safe.');
        $upd3->setStatus(ComplaintStatus::Resolved);
        $upd3->setUpdatedBy($supervisors['B-Block']->getUser());
        $this->em->persist($upd3);

        $this->em->flush();

        // ── 9. Repair Costs ───────────────────────────────────────────────────
        $io->section('Creating Repair Costs');

        $repairData = [
            // [complaintIndex, description, amount]
            [1, 'Fan balancing and blade replacement',  850.00],
            [3, 'Supervisor time — mediation session',  200.00],
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

        // Pending request
        $rcr1 = new RoomChangeRequest();
        $rcr1->setStudent($students['STU003']);
        $rcr1->setCurrentRoom($rooms['A-102']);
        $rcr1->setRequestedRoom($rooms['B-101']);
        $rcr1->setReason('Would prefer to be closer to the library in B-Block.');
        $rcr1->setStatus(RequestStatus::Pending);
        $rcr1->setRequestedAt(new DateTimeImmutable('-3 days'));
        $this->em->persist($rcr1);

        // Approved request (historical)
        $rcr2 = new RoomChangeRequest();
        $rcr2->setStudent($students['STU005']);
        $rcr2->setCurrentRoom($rooms['A-102']);
        $rcr2->setRequestedRoom($rooms['B-101']);
        $rcr2->setReason('Personal reasons — family closer to B-Block area.');
        $rcr2->setStatus(RequestStatus::Approved);
        $rcr2->setRequestedAt(new DateTimeImmutable('-20 days'));
        $rcr2->setReviewedBy($supervisors['A-Block']);
        $rcr2->setReviewedAt(new DateTimeImmutable('-15 days'));
        $this->em->persist($rcr2);

        // Rejected request
        $rcr3 = new RoomChangeRequest();
        $rcr3->setStudent($students['STU006']);
        $rcr3->setCurrentRoom($rooms['B-102']);
        $rcr3->setRequestedRoom($rooms['C-101']);
        $rcr3->setReason('Conflict with roommates.');
        $rcr3->setStatus(RequestStatus::Rejected);
        $rcr3->setRequestedAt(new DateTimeImmutable('-10 days'));
        $rcr3->setReviewedBy($supervisors['B-Block']);
        $rcr3->setReviewedAt(new DateTimeImmutable('-7 days'));
        $this->em->persist($rcr3);

        $this->em->flush();

        // ── 11. Supervisor Tasks ──────────────────────────────────────────────
        $io->section('Creating Supervisor Tasks');

        $taskData = [
            [$supervisors['A-Block'], 'Monthly Room Inspection — A-Block',       'Inspect all 6 rooms in A-Block for cleanliness and maintenance needs.',        TaskStatus::Pending,     '+7 days'],
            [$supervisors['A-Block'], 'Resolve Pending Plumbing Complaints',      'Follow up on all open plumbing complaints. Arrange plumber visit.',             TaskStatus::InProgress,  '+2 days'],
            [$supervisors['B-Block'], 'Update Student Emergency Contact Records', 'Verify and update emergency contacts for all B-Block residents.',               TaskStatus::Done,        '-3 days'],
            [$supervisors['C-Block'], 'Corridor Light Replacement — C-Block F1',  'Coordinate with maintenance to replace fused corridor light on floor 1.',      TaskStatus::Pending,     '+1 days'],
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
            [$supervisors['A-Block'], 'A-Block', 'Water Supply Suspension Friday', 'Maintenance',
             'Water supply in A-Block will be suspended from 2:00 PM to 4:00 PM this Friday for routine tank cleaning. Please store water in advance.'],
            [$supervisors['B-Block'], 'B-Block', 'Fire Drill — Next Monday 10 AM', 'Safety',
             'A mandatory fire drill will be held next Monday at 10:00 AM. All residents must evacuate to the main gate assembly point within 5 minutes of the alarm.'],
            [$supervisors['C-Block'], 'C-Block', 'Visitor Policy Reminder', 'General',
             'Visitors are only permitted in common areas between 8:00 AM and 8:00 PM. No visitors are allowed in resident rooms. Please remind your guests of this policy.'],
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

        $io->success([
            'Database seeded successfully!',
            '  Admin:        admin@hostel.com / password',
            '  Supervisors:  supervisor.a@hostel.com, supervisor.b@hostel.com, supervisor.c@hostel.com / password',
            '  Students:     student1@hostel.com … student12@hostel.com / password',
            '  Rooms:        18 across A/B/C blocks',
            '  Complaints:   8 (Pending/InProgress/Resolved)',
            '  Repair costs: 5 entries',
            '  Room changes: 3 (Pending/Approved/Rejected)',
            '  Tasks:        4 assigned to supervisors',
            '  Announcements:3 (one per block)',
        ]);

        return Command::SUCCESS;
    }
}

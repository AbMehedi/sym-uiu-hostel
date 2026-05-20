<?php

namespace App\Command;

use App\Entity\Announcement;
use App\Entity\Complaint;
use App\Entity\Room;
use App\Entity\RoomAssignment;
use App\Entity\Student;
use App\Entity\Supervisor;
use App\Entity\User;
use App\Enum\AssignmentStatus;
use App\Enum\ComplaintCategory;
use App\Enum\ComplaintStatus;
use App\Enum\Role;
use App\Enum\AdmissionStatus;
use App\Enum\RoomStatus;
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

        // Check if database already has users to avoid duplicate seeding
        $userRepo = $this->em->getRepository(User::class);
        if ($userRepo->findOneBy([]) !== null) {
            $io->warning('Database already has records. Seeding skipped.');
            return Command::SUCCESS;
        }

        // 1. Create Rooms
        $io->section('Creating Rooms');
        
        $room101 = new Room();
        $room101->setRoomNumber('101');
        $room101->setCapacity(2);
        $room101->setCurrentOccupancy(1);
        $room101->setFloor(1);
        $room101->setHostelBlock('A-Block');
        $room101->setStatus(RoomStatus::Available);
        $this->em->persist($room101);

        $room102 = new Room();
        $room102->setRoomNumber('102');
        $room102->setCapacity(3);
        $room102->setCurrentOccupancy(0);
        $room102->setFloor(1);
        $room102->setHostelBlock('A-Block');
        $room102->setStatus(RoomStatus::Available);
        $this->em->persist($room102);

        $room201 = new Room();
        $room201->setRoomNumber('201');
        $room201->setCapacity(2);
        $room201->setCurrentOccupancy(0);
        $room201->setFloor(2);
        $room201->setHostelBlock('B-Block');
        $room201->setStatus(RoomStatus::Available);
        $this->em->persist($room201);

        // 2. Create Users & Profiles
        $io->section('Creating Users & Profiles');

        // Admin User
        $adminUser = new User();
        $adminUser->setEmail('admin@hostel.com');
        $adminUser->setName('System Administrator');
        $adminUser->setRole(Role::Admin);
        $adminUser->setPasswordHash($this->hasher->hashPassword($adminUser, 'password'));
        $this->em->persist($adminUser);

        // Supervisor User
        $superUser = new User();
        $superUser->setEmail('supervisor@hostel.com');
        $superUser->setName('John Supervisor');
        $superUser->setRole(Role::Supervisor);
        $superUser->setPasswordHash($this->hasher->hashPassword($superUser, 'password'));
        $this->em->persist($superUser);

        $supervisor = new Supervisor();
        $supervisor->setUser($superUser);
        $supervisor->setBlockAssigned('A-Block');
        $this->em->persist($supervisor);

        // Student User
        $studentUser = new User();
        $studentUser->setEmail('student@hostel.com');
        $studentUser->setName('Alice Student');
        $studentUser->setRole(Role::Student);
        $studentUser->setPasswordHash($this->hasher->hashPassword($studentUser, 'password'));
        $this->em->persist($studentUser);

        $student = new Student();
        $student->setUser($studentUser);
        $student->setStudentNumber('STU001');
        $student->setAdmissionStatus(AdmissionStatus::Approved);
        $student->setAdmissionDate(new \DateTimeImmutable());
        $this->em->persist($student);

        // 3. Assign Student to Room
        $io->section('Assigning Student to Room');
        $assignment = new RoomAssignment();
        $assignment->setStudent($student);
        $assignment->setRoom($room101);
        $assignment->setAssignedDate(new \DateTimeImmutable());
        $assignment->setStatus(AssignmentStatus::Active);
        $this->em->persist($assignment);

        // 4. Create initial Complaints
        $io->section('Creating initial Complaints');
        $complaint = new Complaint();
        $complaint->setStudent($student);
        $complaint->setRoom($room101);
        $complaint->setSubject('Leaky Water Tap');
        $complaint->setDescription('The water tap in our washroom has a slow drip that is keeping us awake at night.');
        $complaint->setStatus(ComplaintStatus::Pending);
        $complaint->setCategory(ComplaintCategory::Plumbing);
        $this->em->persist($complaint);

        // 5. Create initial Announcements
        $io->section('Creating initial Announcements');
        $announcement = new Announcement();
        $announcement->setTitle('Water Maintenance in A-Block');
        $announcement->setBody('Please note that water supply will be suspended in A-Block from 2:00 PM to 4:00 PM on Friday for routine tank cleaning.');
        $announcement->setCategory('Maintenance');
        $announcement->setSupervisor($supervisor);
        $announcement->setTargetBlock('A-Block');
        $this->em->persist($announcement);

        $this->em->flush();

        $io->success('Database seeded successfully!');

        return Command::SUCCESS;
    }
}

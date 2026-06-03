<?php

namespace App\Tests\Controller;

use App\Entity\AdmissionRequest;
use App\Entity\Announcement;
use App\Entity\Room;
use App\Entity\Student;
use App\Entity\User;
use App\Enum\AdmissionStatus;
use App\Enum\AssignmentStatus;
use App\Enum\Role;
use App\Enum\RequestStatus;
use App\Entity\RoomAssignment;
use App\Entity\RepairCost;
use App\Entity\Complaint;
use App\Entity\Supervisor;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StudentWorkflowRemainingTest extends WebTestCase
{
    private $client;
    private $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = $this->client->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    public function testRegistrationFlowCreatesAdmissionRequest(): void
    {
        $email = 'remainingtest_' . uniqid() . '@hostel.com';
        $this->client->request('POST', '/register', [
            'firstName' => 'Remaining',
            'lastName' => 'Student',
            'studentId' => '221_' . uniqid(),
            'email' => $email,
            'phone' => '+8801700000000',
            'password' => 'password123',
            'confirmPassword' => 'password123',
        ]);

        $this->assertResponseRedirects('/register/pending');
        
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Application Under Review', $this->client->getResponse()->getContent());

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($user);
        
        $student = $user->getStudent();
        $this->assertNotNull($student);
        $this->assertEquals(AdmissionStatus::Pending, $student->getAdmissionStatus());

        $admissionRequest = $this->entityManager->getRepository(AdmissionRequest::class)->findOneBy(['student' => $student]);
        $this->assertNotNull($admissionRequest);
        $this->assertEquals(RequestStatus::Pending, $admissionRequest->getStatus());
    }

    public function testMyRoomDisplaysRoommatesAndSupervisor(): void
    {
        $studentUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'student1@hostel.com']);
        $this->assertNotNull($studentUser);

        $this->client->loginUser($studentUser);
        $this->client->request('GET', '/student/my-room');

        $this->assertResponseIsSuccessful();
        
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('My Room', $content);
        $this->assertStringContainsString('Room Info', $content);
        $this->assertStringContainsString('My Roommates', $content);
    }

    public function testAnnouncementsFilteringByBlock(): void
    {
        $studentUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'student1@hostel.com']);
        $this->assertNotNull($studentUser);

        $student = $studentUser->getStudent();
        $room = $student->getRoom();
        $block = $room ? $room->getBlock() : 'A';

        $supervisor = $this->entityManager->getRepository(Supervisor::class)->findOneBy([]);
        $this->assertNotNull($supervisor, 'No supervisor found in database to link announcements to');

        // Create announcements: one for student block, one for other block
        $ann1 = new Announcement();
        $ann1->setTitle('Test Block notice');
        $ann1->setBody('This is for block ' . $block);
        $ann1->setTargetBlock($block);
        $ann1->setSupervisor($supervisor);

        $ann2 = new Announcement();
        $ann2->setTitle('Test Other block notice');
        $ann2->setBody('This is for block other');
        $ann2->setTargetBlock($block === 'A' ? 'B' : 'A');
        $ann2->setSupervisor($supervisor);

        $this->entityManager->persist($ann1);
        $this->entityManager->persist($ann2);
        $this->entityManager->flush();

        $this->client->loginUser($studentUser);
        $this->client->request('GET', '/student/announcements');

        $this->assertResponseIsSuccessful();
        
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Test Block notice', $content);
        $this->assertStringNotContainsString('Test Other block notice', $content);
    }

    public function testRepairCostsPdfDownload(): void
    {
        $adminUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@hostel.com']);
        $this->assertNotNull($adminUser);

        $complaint = $this->entityManager->getRepository(Complaint::class)->findOneBy([]);
        if (!$complaint) {
            $this->markTestSkipped('No complaints found in DB to attach repair costs to.');
        }

        $repairCost = new RepairCost();
        $repairCost->setComplaint($complaint);
        $repairCost->setAmount('1500.00');
        $repairCost->setDescription('Test plumbing fix');
        $repairCost->setCostDate(new DateTimeImmutable());
        $repairCost->setRecordedBy($adminUser);

        $this->entityManager->persist($repairCost);
        $this->entityManager->flush();

        $this->client->loginUser($adminUser);
        $this->client->request('GET', '/admin/pdf/repair-costs');

        $this->assertResponseIsSuccessful();
        $this->assertEquals('application/pdf', $this->client->getResponse()->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="repair-costs-', $this->client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testGuestLandingPage(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Welcome to Your New Home', $content);
        $this->assertStringContainsString('Pick a room', $content);
        $this->assertStringContainsString('Rules', $content);
        $this->assertStringContainsString('Talk to Us', $content);
    }

    public function testGuardedPagesRedirectNonApprovedStudent(): void
    {
        $pendingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'student9@hostel.com']);
        $this->assertNotNull($pendingUser);

        $this->client->loginUser($pendingUser);
        
        // Attempt to access my-room
        $this->client->request('GET', '/student/my-room');
        $this->assertResponseRedirects('/student/dashboard');

        // Follow redirect and check flash message
        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Access denied. Your admission status is not approved.', $this->client->getResponse()->getContent());
    }

    public function testGuardedJsonEndpointsReturn403ForNonApprovedStudent(): void
    {
        $pendingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'student9@hostel.com']);
        $this->assertNotNull($pendingUser);

        $this->client->loginUser($pendingUser);
        
        // Attempt to send a message
        $this->client->request(
            'POST',
            '/student/chat/1/send',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['message' => 'Hello'])
        );
        $this->assertResponseStatusCodeSame(403);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Access denied. Your admission status is not approved.', $response['error']);

        // Attempt to poll
        $this->client->request('GET', '/student/chat/1/poll?after=0');
        $this->assertResponseStatusCodeSame(403);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Access denied. Your admission status is not approved.', $response['error']);
    }

    public function testSupervisorLogin(): void
    {
        $supervisorUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'supervisor.a@hostel.com']);
        $this->assertNotNull($supervisorUser);

        $this->client->loginUser($supervisorUser);
        $this->client->request('GET', '/supervisor/');
        $this->assertResponseIsSuccessful();
    }

    public function testSupervisorDashboardDisplaysPendingRoomChanges(): void
    {
        $supervisorUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'supervisor.a@hostel.com']);
        $this->assertNotNull($supervisorUser);

        $this->client->loginUser($supervisorUser);
        $crawler = $this->client->request('GET', '/supervisor/');
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Pending Room Change Requests', $content);
        $this->assertStringContainsString('Recent Complaints', $content);
        $this->assertStringContainsString('My Recent Announcements', $content);
        
        $pendingRoomChanges = $this->entityManager->getRepository(\App\Entity\RoomChangeRequest::class)->findBy(['status' => RequestStatus::Pending]);
        $expectedCount = count($pendingRoomChanges);

        $this->assertSelectorTextContains('#rc-badge', (string)$expectedCount);
    }
}




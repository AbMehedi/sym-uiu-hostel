<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StudentControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $studentUser;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        
        // Disable following redirects to check the exact response
        // $this->client->followRedirects();

        $this->entityManager = $this->client->getContainer()
            ->get('doctrine')
            ->getManager();

        // Find a student user (e.g., student1@hostel.com created by seed-db)
        $this->studentUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'student1@hostel.com']);
        
        if (!$this->studentUser) {
            $this->markTestSkipped('Database needs to be seeded first with student1@hostel.com');
        } else {
            // Clean up any existing room change requests for student1 to make this test idempotent
            $student = $this->studentUser->getStudent();
            if ($student) {
                $rcrRepo = $this->entityManager->getRepository(\App\Entity\RoomChangeRequest::class);
                $existingRequests = $rcrRepo->findBy(['student' => $student]);
                foreach ($existingRequests as $req) {
                    $this->entityManager->remove($req);
                }
                $this->entityManager->flush();
            }
        }
    }

    public function testDashboardIsAccessibleByStudent(): void
    {
        $this->client->loginUser($this->studentUser);
        
        $this->client->request('GET', '/student/dashboard');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Welcome');
    }

    public function testProfileIsAccessibleByStudent(): void
    {
        $this->client->loginUser($this->studentUser);
        
        $this->client->request('GET', '/student/profile');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'My Profile');
    }

    public function testChatPageIsAccessible(): void
    {
        $this->client->loginUser($this->studentUser);
        
        $this->client->request('GET', '/student/chat');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.messenger-contacts-header h2', 'Messages');
    }

    public function testSendingChatMessage(): void
    {
        $this->client->loginUser($this->studentUser);

        // Find roommate to message
        $roommateUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'student2@hostel.com']);
        $this->assertNotNull($roommateUser, 'Roommate student2 not found');

        $this->client->request(
            'POST', 
            '/student/chat/' . $roommateUser->getId() . '/send',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['message' => 'Integration test message!'])
        );
        
        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('id', $response);
        $this->assertEquals('Integration test message!', $response['message']);
    }

    public function testSubmitRoomChangeRequest(): void
    {
        $this->client->loginUser($this->studentUser);

        $this->client->request(
            'POST',
            '/student/room-change',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'reason' => 'Noise Issues',
                'details' => 'My roommate snores too loudly.'
            ])
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);
    }
}

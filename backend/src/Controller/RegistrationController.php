<?php

namespace App\Controller;

use App\Entity\Student;
use App\Entity\User;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if (!$request->isMethod('POST')) {
            return new JsonResponse([
                'message' => 'Send a POST request to register a student account.',
                'required_fields' => ['name', 'email', 'studentNumber', 'password'],
                'optional_fields' => ['phone', 'emergencyContact'],
            ], Response::HTTP_METHOD_NOT_ALLOWED);
        }

        $data = $request->request->all();
        if ($data === []) {
            $decoded = json_decode((string) $request->getContent(), true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $studentNumber = trim((string) ($data['studentNumber'] ?? ''));
        $password = (string) ($data['password'] ?? $data['plainPassword'] ?? '');

        if ($name === '' || $email === '' || $studentNumber === '' || $password === '') {
            return new JsonResponse([
                'message' => 'Missing required fields.',
                'required_fields' => ['name', 'email', 'studentNumber', 'password'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = (new User())
            ->setName($name)
            ->setEmail($email)
            ->setRole(Role::Student);

        $user->setPasswordHash($passwordHasher->hashPassword($user, $password));

        $student = (new Student())
            ->setUser($user)
            ->setStudentNumber($studentNumber)
            ->setPhone(($data['phone'] ?? null) ?: null)
            ->setEmergencyContact(($data['emergencyContact'] ?? null) ?: null);

        $entityManager->persist($user);
        $entityManager->persist($student);
        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Registration complete.',
            'user_id' => $user->getId(),
        ], Response::HTTP_CREATED);
    }
}

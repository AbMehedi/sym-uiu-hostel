<?php

namespace App\Controller;

use App\Entity\AdmissionRequest;
use App\Entity\Student;
use App\Entity\User;
use App\Enum\RequestStatus;
use App\Enum\Role;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        if ($request->isMethod('POST')) {
            $firstName = trim((string) $request->request->get('firstName'));
            $lastName = trim((string) $request->request->get('lastName'));
            $studentId = trim((string) $request->request->get('studentId'));
            $email = trim((string) $request->request->get('email'));
            $phone = trim((string) $request->request->get('phone'));
            $password = (string) $request->request->get('password');
            $confirmPassword = (string) $request->request->get('confirmPassword');

            if ($firstName === '' || $lastName === '' || $studentId === '' || $email === '' || $password === '') {
                $this->addFlash('error', 'Please fill in all required fields.');
                return $this->redirectToRoute('app_register');
            }

            if ($password !== $confirmPassword) {
                $this->addFlash('error', 'Passwords do not match.');
                return $this->redirectToRoute('app_register');
            }

            // Check if email or student ID already exists
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser) {
                $this->addFlash('error', 'An account with this email already exists.');
                return $this->redirectToRoute('app_register');
            }

            $existingStudent = $entityManager->getRepository(Student::class)->findOneBy(['studentNumber' => $studentId]);
            if ($existingStudent) {
                $this->addFlash('error', 'A student with this Student ID is already registered.');
                return $this->redirectToRoute('app_register');
            }

            $user = new User();
            $user->setName($firstName . ' ' . $lastName);
            $user->setEmail($email);
            $user->setRole(Role::Student);
            $user->setPasswordHash($passwordHasher->hashPassword($user, $password));

            $student = new Student();
            $student->setUser($user);
            $student->setStudentNumber($studentId);
            $student->setPhone($phone ?: null);

            $admissionRequest = new AdmissionRequest();
            $admissionRequest->setStudent($student);
            $admissionRequest->setRequestedDate(new DateTimeImmutable());
            $admissionRequest->setStatus(RequestStatus::Pending);
            $admissionRequest->setPreferredRoomType('Double Sharing');

            $entityManager->persist($user);
            $entityManager->persist($student);
            $entityManager->persist($admissionRequest);
            $entityManager->flush();

            $this->addFlash('success', 'Registration submitted successfully! Your application is pending review.');
            return $this->redirectToRoute('app_register_pending');
        }

        return $this->render('registration/register.html.twig');
    }

    #[Route('/register/pending', name: 'app_register_pending')]
    public function pending(): Response
    {
        return $this->render('registration/pending.html.twig');
    }
}


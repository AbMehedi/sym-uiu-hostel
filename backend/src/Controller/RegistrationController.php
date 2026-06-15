<?php

namespace App\Controller;

use App\Entity\AdmissionRequest;
use App\Entity\Student;
use App\Entity\User;
use App\Enum\RequestStatus;
use App\Enum\Role;
use App\Repository\AdmissionRequestRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
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
        EntityManagerInterface $entityManager,
        AdmissionRequestRepository $admissionRepo,
    ): Response {
        if ($request->isMethod('POST')) {
            $firstName = trim((string) $request->request->get('firstName'));
            $lastName = trim((string) $request->request->get('lastName'));
            $studentId = trim((string) $request->request->get('studentId'));
            $email = trim((string) $request->request->get('email'));
            $phone = trim((string) $request->request->get('phone'));
            $password = (string) $request->request->get('password');
            $confirmPassword = (string) $request->request->get('confirmPassword');

            $idCardPicture = $request->files->get('idCardPicture');
            $nidOrBirthCert = $request->files->get('nidOrBirthCert');

            if ($firstName === '' || $lastName === '' || $studentId === '' || $email === '' || $password === '' || !$idCardPicture || !$nidOrBirthCert) {
                $this->addFlash('error', 'Please fill in all required fields and upload the required documents.');
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
                // B-19: also check if they already have a pending admission request
                $pendingRequest = $admissionRepo->findOneBy([
                    'student' => $existingStudent,
                    'status'  => RequestStatus::Pending,
                ]);
                if ($pendingRequest) {
                    $this->addFlash('error', 'This student ID already has a pending admission request. Please wait for admin review.');
                    return $this->redirectToRoute('app_register');
                }
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

            // Handle file uploads
            $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/students';
            if (!is_dir($uploadsDir)) {
                @mkdir($uploadsDir, 0775, true);
            }

            try {
                $idExt = $idCardPicture->guessExtension() ?: 'bin';
                $idFilename = 'id-' . bin2hex(random_bytes(6)) . '.' . $idExt;
                $idCardPicture->move($uploadsDir, $idFilename);
                $student->setIdCardPicturePath('/uploads/students/' . $idFilename);

                $nidExt = $nidOrBirthCert->guessExtension() ?: 'bin';
                $nidFilename = 'nid-' . bin2hex(random_bytes(6)) . '.' . $nidExt;
                $nidOrBirthCert->move($uploadsDir, $nidFilename);
                $student->setNidOrBirthCertPath('/uploads/students/' . $nidFilename);
            } catch (FileException) {
                $this->addFlash('error', 'Failed to upload documents. Please try again.');
                return $this->redirectToRoute('app_register');
            }

            $preferredRoomType = trim((string) $request->request->get('preferredRoomType'));
            if (!$preferredRoomType) {
                $preferredRoomType = 'Double Sharing';
            }

            $admissionRequest = new AdmissionRequest();
            $admissionRequest->setStudent($student);
            $admissionRequest->setRequestedDate(new DateTimeImmutable());
            $admissionRequest->setStatus(RequestStatus::Pending);
            $admissionRequest->setPreferredRoomType($preferredRoomType);

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


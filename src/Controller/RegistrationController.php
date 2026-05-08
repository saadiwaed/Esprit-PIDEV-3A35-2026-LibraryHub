<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Role;
use App\Entity\ReadingProfile;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $user->setStatus('PENDING');

            // Rôle MEMBER
            $roleRepository = $entityManager->getRepository(Role::class);
            $memberRole = $roleRepository->findOneBy(['name' => 'ROLE_MEMBER']);

            if (!$memberRole) {
                $memberRole = new Role();
                $memberRole->setName('ROLE_MEMBER');
                $memberRole->setDescription('Membre standard de LibraryHub');
                $entityManager->persist($memberRole);
            }

            $user->addRole($memberRole);

            // Reading Profile
            $readingProfile = new ReadingProfile();
            $readingProfile->setUser($user);
            $readingProfile->setTotalBooksRead(0);

            $entityManager->persist($readingProfile);
            $entityManager->persist($user);
            $entityManager->flush();

            // NO auto-login — user must complete MFA setup first
            $this->addFlash('success', 'Compte créé ! Configurez d\'abord votre double authentification.');

            return $this->redirectToRoute('app_mfa_setup', ['id' => $user->getId()]);
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
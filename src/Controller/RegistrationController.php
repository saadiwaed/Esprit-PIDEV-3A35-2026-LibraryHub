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
    ): Response
    {
        // If user is already logged in, redirect based on role
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash the password
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

            // Set default status
            $user->setStatus('PENDING');

            // ✅ 1. Récupérer ou créer le rôle MEMBRE
            $roleRepository = $entityManager->getRepository(Role::class);
            $memberRole = $roleRepository->findOneBy(['name' => 'ROLE_MEMBER']);
            
            if (!$memberRole) {
                $memberRole = new Role();
                $memberRole->setName('ROLE_MEMBER');
                $memberRole->setDescription('Membre standard de LibraryHub');
                $entityManager->persist($memberRole);
            }
            
            // ✅ 2. Assigner le rôle MEMBRE à l'utilisateur
            $user->addRole($memberRole);
            
            // ✅ 3. Créer un profil de lecture par défaut
            $readingProfile = new ReadingProfile();
            $readingProfile->setUser($user);
            $readingProfile->setTotalBooksRead(0);

            // Ajoute d'autres valeurs par défaut selon ton entité ReadingProfile
            
            $entityManager->persist($readingProfile);
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Inscription réussie ! Bienvenue sur LibraryHub.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
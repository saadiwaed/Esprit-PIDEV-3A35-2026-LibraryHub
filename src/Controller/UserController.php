<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/user')]
class UserController extends AbstractController
{
    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository, RoleRepository $roleRepository): Response
    {
        $search = $request->query->get('search', '');
        $statusFilter = $request->query->get('status', '');
        $roleFilter = $request->query->get('role', '');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 4; // Number of users per page
        
        // Build base query
        $queryBuilder = $userRepository->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC');
        
        $hasWhere = false;
        
        // Apply search filter
        if ($search) {
            $queryBuilder
                ->where('(LOWER(u.firstName) LIKE LOWER(:query)')
                ->orWhere('LOWER(u.lastName) LIKE LOWER(:query)')
                ->orWhere('LOWER(u.email) LIKE LOWER(:query)')
                ->orWhere("LOWER(CONCAT(u.firstName, ' ', u.lastName)) LIKE LOWER(:query))")
                ->setParameter('query', '%' . $search . '%');
            $hasWhere = true;
        }
        
        // Apply status filter
        if ($statusFilter && in_array($statusFilter, ['PENDING', 'ACTIVE', 'INACTIVE'])) {
            if ($hasWhere) {
                $queryBuilder->andWhere('u.status = :status');
            } else {
                $queryBuilder->where('u.status = :status');
                $hasWhere = true;
            }
            $queryBuilder->setParameter('status', $statusFilter);
        }
        
        // Apply role filter
        if ($roleFilter) {
            $queryBuilder->join('u.roles', 'r');
            if ($hasWhere) {
                $queryBuilder->andWhere('r.id = :roleId');
            } else {
                $queryBuilder->where('r.id = :roleId');
                $hasWhere = true;
            }
            $queryBuilder->setParameter('roleId', $roleFilter);
        }
        
        // Get total count for pagination
        $countQueryBuilder = clone $queryBuilder;
        $totalUsers = (int) $countQueryBuilder
            ->select('COUNT(DISTINCT u.id)')
            ->getQuery()
            ->getSingleScalarResult();
        
        $totalPages = $totalUsers > 0 ? (int) ceil($totalUsers / $limit) : 1;
        
        // Apply pagination to get users
        $users = $queryBuilder
            ->select('DISTINCT u')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        
        // Get all roles for filter dropdown
        $allRoles = $roleRepository->findAll();
        
        return $this->render('user/index.html.twig', [
            'users' => $users,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalUsers' => $totalUsers,
            'limit' => $limit,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'roleFilter' => $roleFilter,
            'allRoles' => $allRoles,
        ]);
    }

    #[Route('/search', name: 'app_user_search', methods: ['GET'])]
    public function search(Request $request, UserRepository $userRepository): Response
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return $this->json([]);
        }
        
        $users = $userRepository->searchByNameOrEmail($query);
        
        $results = array_map(function($user) {
            return [
                'id' => $user->getId(),
                'fullName' => $user->getFullName(),
                'email' => $user->getEmail(),
                'avatar' => $user->getAvatar(),
                'initials' => substr($user->getFirstName(), 0, 1) . substr($user->getLastName(), 0, 1),
            ];
        }, $users);
        
        return $this->json($results);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager, 
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger
    ): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle password
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            // Handle avatar upload
            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$avatarFile->guessExtension();

                try {
                    $avatarFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/avatars',
                        $newFilename
                    );
                    $user->setAvatar($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('warning', 'Avatar upload failed, but user was created.');
                }
            }

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'User created successfully!');

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        User $user, 
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger
    ): Response
    {
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle password (only if provided)
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            // Handle avatar upload
            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                // Delete old avatar if exists
                if ($user->getAvatar()) {
                    $oldAvatarPath = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars/' . $user->getAvatar();
                    if (file_exists($oldAvatarPath)) {
                        unlink($oldAvatarPath);
                    }
                }

                $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$avatarFile->guessExtension();

                try {
                    $avatarFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/avatars',
                        $newFilename
                    );
                    $user->setAvatar($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('warning', 'Avatar upload failed, but user was updated.');
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'User updated successfully!');

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            try {
                // Delete avatar if exists
                if ($user->getAvatar()) {
                    $avatarPath = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars/' . $user->getAvatar();
                    if (file_exists($avatarPath)) {
                        unlink($avatarPath);
                    }
                }

                $entityManager->remove($user);
                $entityManager->flush();

                $this->addFlash('success', 'User deleted successfully!');
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Cannot delete this user: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('danger', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}

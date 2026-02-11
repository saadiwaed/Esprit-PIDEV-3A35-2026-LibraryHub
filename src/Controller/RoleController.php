<?php

namespace App\Controller;

use App\Entity\Role;
use App\Form\RoleType;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/role')]
class RoleController extends AbstractController
{
    #[Route('/', name: 'app_role_index', methods: ['GET'])]
    public function index(Request $request, RoleRepository $roleRepository): Response
    {
        $search = $request->query->get('q', '');
        $hasUsers = $request->query->get('hasUsers', '');
        
        // Use filters
        $roles = $roleRepository->findWithFilters(
            $search ?: null,
            $hasUsers ?: null
        );
        
        return $this->render('role/index.html.twig', [
            'roles' => $roles,
            'search' => $search,
            'currentHasUsers' => $hasUsers,
        ]);
    }

    #[Route('/search', name: 'app_role_search', methods: ['GET'])]
    public function search(Request $request, RoleRepository $roleRepository): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 1) {
            return $this->json([]);
        }
        
        $roles = $roleRepository->searchByName($query, 10);
        
        $results = [];
        foreach ($roles as $role) {
            $results[] = [
                'id' => $role->getId(),
                'text' => $role->getName(),
                'name' => $role->getName(),
                'description' => $role->getDescription(),
            ];
        }
        
        return $this->json($results);
    }

    #[Route('/new', name: 'app_role_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $role = new Role();
        $form = $this->createForm(RoleType::class, $role);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($role);
            $entityManager->flush();

            $this->addFlash('success', 'Role created successfully!');
            return $this->redirectToRoute('app_role_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('role/new.html.twig', [
            'role' => $role,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_role_show', methods: ['GET'])]
    public function show(Role $role): Response
    {
        return $this->render('role/show.html.twig', [
            'role' => $role,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_role_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Role $role, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(RoleType::class, $role);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Role updated successfully!');
            return $this->redirectToRoute('app_role_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('role/edit.html.twig', [
            'role' => $role,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_role_delete', methods: ['POST'])]
    public function delete(Request $request, Role $role, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $role->getId(), $request->getPayload()->getString('_token'))) {
            // Check if role is assigned to users
            if ($role->getUsers()->count() > 0) {
                $this->addFlash('error', 'Cannot delete role. It is assigned to ' . $role->getUsers()->count() . ' user(s).');
                return $this->redirectToRoute('app_role_index', [], Response::HTTP_SEE_OTHER);
            }

            $entityManager->remove($role);
            $entityManager->flush();

            $this->addFlash('success', 'Role deleted successfully!');
        }

        return $this->redirectToRoute('app_role_index', [], Response::HTTP_SEE_OTHER);
    }
}

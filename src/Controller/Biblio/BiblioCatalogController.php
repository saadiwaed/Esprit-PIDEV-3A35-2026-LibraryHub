<?php

namespace App\Controller\Biblio;
use Endroid\QrCode\Builder\Builder;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\Category;
use App\Form\AuthorType;
use App\Form\BookType;
use App\Form\CategoryType;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use App\Repository\CategoryRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;

#[Route('/biblio')]
final class BiblioCatalogController extends AbstractController
{
    #[Route(name: 'biblio_catalog_index', methods: ['GET'])]
    public function index(
        CategoryRepository $categoryRepository,
        AuthorRepository $authorRepository,
        BookRepository $bookRepository
    ): Response {
        return $this->render('catalog/backoffice/index.html.twig', [
            'categories_count' => $categoryRepository->count([]),
            'authors_count' => $authorRepository->count([]),
            'books_count' => $bookRepository->count([]),
            'area' => 'biblio',
        ]);
    }

    #[Route('/categories', name: 'biblio_category_index', methods: ['GET'])]
    public function categoryIndex(CategoryRepository $repository): Response
    {
        return $this->render('catalog/backoffice/category/index.html.twig', [
            'categories' => $repository->findAllOrderedByName(),
            'area' => 'biblio',
        ]);
    }

    #[Route('/categories/new', name: 'biblio_category_new', methods: ['GET', 'POST'])]
    public function categoryNew(Request $request, EntityManagerInterface $em, FileUploader $fileUploader): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $iconFile = $form->get('iconFile')->getData();
            if ($iconFile instanceof UploadedFile) {
                $category->setIcon($fileUploader->upload($iconFile, 'categories'));
            }
            $em->persist($category);
            $em->flush();
            $this->addFlash('success', 'Catégorie créée.');
            return $this->redirectToRoute('biblio_category_index', [], Response::HTTP_SEE_OTHER);
        }
        return $this->render('catalog/backoffice/category/new.html.twig', [
            'category' => $category,
            'form' => $form,
            'area' => 'biblio',
        ]);
    }

    #[Route('/categories/{id}', name: 'biblio_category_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function categoryShow(Category $category): Response
    {
        return $this->render('catalog/backoffice/category/show.html.twig', [
            'category' => $category,
            'area' => 'biblio',
        ]);
    }

    #[Route('/categories/{id}/edit', name: 'biblio_category_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function categoryEdit(Request $request, Category $category, EntityManagerInterface $em, FileUploader $fileUploader): Response
    {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $iconFile = $form->get('iconFile')->getData();
            if ($iconFile instanceof UploadedFile) {
                $category->setIcon($fileUploader->upload($iconFile, 'categories'));
            }
            $em->flush();
            $this->addFlash('success', 'Catégorie mise à jour.');
            return $this->redirectToRoute('biblio_category_index', [], Response::HTTP_SEE_OTHER);
        }
        return $this->render('catalog/backoffice/category/edit.html.twig', [
            'category' => $category,
            'form' => $form,
            'area' => 'biblio',
        ]);
    }

    #[Route('/categories/{id}', name: 'biblio_category_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function categoryDelete(Request $request, Category $category, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $category->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($category);
            $em->flush();
            $this->addFlash('success', 'Catégorie supprimée.');
        }
        return $this->redirectToRoute('biblio_category_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/authors', name: 'biblio_author_index', methods: ['GET'])]
    public function authorIndex(AuthorRepository $repository): Response
    {
        return $this->render('catalog/backoffice/author/index.html.twig', [
            'authors' => $repository->findBy([], ['lastname' => 'ASC', 'firstname' => 'ASC']),
            'area' => 'biblio',
        ]);
    }

    #[Route('/authors/new', name: 'biblio_author_new', methods: ['GET', 'POST'])]
    public function authorNew(Request $request, EntityManagerInterface $em, FileUploader $fileUploader): Response
    {
        $author = new Author();
        $form = $this->createForm(AuthorType::class, $author);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $photoFile = $form->get('photoFile')->getData();
            if ($photoFile instanceof UploadedFile) {
                $author->setPhoto($fileUploader->upload($photoFile, 'authors'));
            }
            $em->persist($author);
            $em->flush();
            $this->addFlash('success', 'Auteur créé.');
            return $this->redirectToRoute('biblio_author_index', [], Response::HTTP_SEE_OTHER);
        }
        return $this->render('catalog/backoffice/author/new.html.twig', [
            'author' => $author,
            'form' => $form,
            'area' => 'biblio',
        ]);
    }

    #[Route('/authors/{id}', name: 'biblio_author_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function authorShow(Author $author): Response
    {
        return $this->render('catalog/backoffice/author/show.html.twig', [
            'author' => $author,
            'area' => 'biblio',
        ]);
    }

    #[Route('/authors/{id}/edit', name: 'biblio_author_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function authorEdit(Request $request, Author $author, EntityManagerInterface $em, FileUploader $fileUploader): Response
    {
        $form = $this->createForm(AuthorType::class, $author);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $photoFile = $form->get('photoFile')->getData();
            if ($photoFile instanceof UploadedFile) {
                $author->setPhoto($fileUploader->upload($photoFile, 'authors'));
            }
            $em->flush();
            $this->addFlash('success', 'Auteur mis à jour.');
            return $this->redirectToRoute('biblio_author_index', [], Response::HTTP_SEE_OTHER);
        }
        return $this->render('catalog/backoffice/author/edit.html.twig', [
            'author' => $author,
            'form' => $form,
            'area' => 'biblio',
        ]);
    }

    #[Route('/authors/{id}', name: 'biblio_author_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function authorDelete(Request $request, Author $author, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $author->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($author);
            $em->flush();
            $this->addFlash('success', 'Auteur supprimé.');
        }
        return $this->redirectToRoute('biblio_author_index', [], Response::HTTP_SEE_OTHER);
    }

 
    #[Route('/books', name: 'biblio_book_index', methods: ['GET'])]
    public function bookIndex(
        Request $request,
        BookRepository $repo,
        CategoryRepository $categoryRepository,
        AuthorRepository $authorRepository,
        PaginatorInterface $paginator
    ): Response {
    
        $q = $request->query->get('q');
        $category = $request->query->get('category');
        $author = $request->query->get('author');
        $order = $request->query->get('order');
    
        $query = $repo->createFilteredQuery($q,$category,$author,$order);
    
        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page',1),
            3
        );
    
        // AJAX request -> return only grid
        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return $this->render('catalog/backoffice/book/_grid.html.twig', [
                'pagination'=>$pagination,
                'area'=>'biblio'
            ]);
        }
    
        return $this->render('catalog/backoffice/book/index.html.twig', [
            'pagination'=>$pagination,
            'categories'=>$categoryRepository->findAll(),
            'authors'=>$authorRepository->findAll(),
            'area'=>'biblio'
        ]);
    }
    
    #[Route('/books/{id}/qr', name:'biblio_book_qr')]
    public function bookQr(Book $book): Response
    {
        $qr = Builder::create()
        ->data($this->generateUrl('biblio_book_show',['id'=>$book->getId()],0))
        ->size(300)
            ->build();
    
        return new Response($qr->getString(),200,['Content-Type'=>'image/png']);
    }
    #[Route('/books/new', name: 'biblio_book_new', methods: ['GET', 'POST'])]
    public function bookNew(Request $request, EntityManagerInterface $em, FileUploader $fileUploader): Response
    {
        $book = new Book();
        $form = $this->createForm(BookType::class, $book);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $coverFile = $form->get('coverImageFile')->getData();
            if ($coverFile instanceof UploadedFile) {
                $book->setCoverImage($fileUploader->upload($coverFile, 'books'));
            }
            $em->persist($book);
            $em->flush();
            $this->addFlash('success', 'Livre créé.');
            return $this->redirectToRoute('biblio_book_index', [], Response::HTTP_SEE_OTHER);
        }
        return $this->render('catalog/backoffice/book/new.html.twig', [
            'book' => $book,
            'form' => $form,
            'area' => 'biblio',
        ]);
    }
    #[Route('/books/{id}', name: 'biblio_book_show', requirements: ['id' => '\d+'], methods: ['GET'])]
public function bookShow(Book $book): Response
{
    // construction du texte du QR
    $status = match ($book->getStatus()) {
        'available' => 'Disponible',
        'borrowed' => 'Emprunté',
        'reserved' => 'Réservé',
        default => 'Maintenance'
    };

    $qrText =
        "===== BIBLIOTHEQUE =====\n" .
        "ID Livre : " . $book->getId() . "\n" .
        "Titre : " . $book->getTitle() . "\n" .
        "Auteur : " . $book->getAuthor()->getFullName() . "\n" .
        "Categorie : " . $book->getCategory()->getName() . "\n" .
        "Editeur : " . ($book->getPublisher() ?? '—') . "\n" .
        "Annee : " . ($book->getPublicationYear() ?? '—') . "\n" .
        "Pages : " . ($book->getPageCount() ?? '—') . "\n" .
        "Langue : " . ($book->getLanguage() ?? '—') . "\n" .
        "Statut : " . $status . "\n" .
        "Ajoute le : " . $book->getCreatedAt()->format('d/m/Y');

    // ✅ CORRECTION - Utilisation de QrCode au lieu de Builder
    $qrCode = QrCode::create($qrText)
    ->setSize(350)
    ->setMargin(10)
    ->setEncoding(new Encoding('UTF-8'))  // ← Utilise new Encoding()
    ->setErrorCorrectionLevel(ErrorCorrectionLevel::High);

    $writer = new PngWriter();
    $result = $writer->write($qrCode);
    $qr = $result->getDataUri();

    return $this->render('catalog/backoffice/book/show.html.twig', [
        'book' => $book,
        'area' => 'biblio',
        'qr' => $qr
    ]);
}

    #[Route('/books/{id}/edit', name: 'biblio_book_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function bookEdit(Request $request, Book $book, EntityManagerInterface $em, FileUploader $fileUploader): Response
    {
        $form = $this->createForm(BookType::class, $book);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $coverFile = $form->get('coverImageFile')->getData();
            if ($coverFile instanceof UploadedFile) {
                $book->setCoverImage($fileUploader->upload($coverFile, 'books'));
            }
            $em->flush();
            $this->addFlash('success', 'Livre mis à jour.');
            return $this->redirectToRoute('biblio_book_index', [], Response::HTTP_SEE_OTHER);
        }
        return $this->render('catalog/backoffice/book/edit.html.twig', [
            'book' => $book,
            'form' => $form,
            'area' => 'biblio',
        ]);
    }

    #[Route('/books/{id}', name: 'biblio_book_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function bookDelete(Request $request, Book $book, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $book->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($book);
            $em->flush();
            $this->addFlash('success', 'Livre supprimé.');
        }
        return $this->redirectToRoute('biblio_book_index', [], Response::HTTP_SEE_OTHER);
    }
}

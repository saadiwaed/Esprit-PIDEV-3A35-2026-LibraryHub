<?php

namespace App\Command;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:books',
    description: 'Crée des livres de démonstration pour tester les demandes d\'emprunt',
)]
final class SeedBookTestDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('count', null, InputOption::VALUE_OPTIONAL, 'Nombre de livres à créer', 10)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Créer même si des livres existent déjà');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = max(1, (int) $input->getOption('count'));
        $force = (bool) $input->getOption('force');

        $existingBooks = (int) $this->entityManager->createQuery('SELECT COUNT(b.id) FROM App\Entity\Book b')
            ->getSingleScalarResult();

        if ($existingBooks > 0 && !$force) {
            $io->warning(sprintf(
                'Il y a déjà %d livre(s) en base. Relancez avec --force pour en créer quand même.',
                $existingBooks
            ));
            $io->writeln('Exemple: php bin/console app:seed:books --count=10 --force');

            return Command::SUCCESS;
        }

        $category = $this->getOrCreateCategory();
        $author = $this->getOrCreateAuthor();

        $titles = [
            'Le Secret des Étagères',
            'Voyage au Cœur des Pages',
            'La Bibliothèque Oubliée',
            'Chroniques d\'un Lecteur',
            'Le Dernier Chapitre',
            'Sous la Pluie des Mots',
            'L\'Encre et la Lumière',
            'Les Murmures du Papier',
            'La Nuit des Livres',
            'Au Fil des Histoires',
            'Les Aventuriers du Savoir',
            'Le Code des Bibliothèques',
            'Parfums de Lecture',
            'Le Jardin des Romans',
            'La Carte des Récits',
        ];

        $created = [];
        for ($i = 0; $i < $count; $i++) {
            $title = $titles[$i % count($titles)] . ($count > count($titles) ? ' #' . ($i + 1) : '');

            $book = (new Book())
                ->setTitle($title)
                ->setDescription('Livre de démonstration pour tester les demandes d\'emprunt.')
                ->setPublisher('LIBRARYHUB Éditions')
                ->setPublicationYear((int) (new \DateTimeImmutable())->format('Y'))
                ->setPageCount(250 + ($i * 10))
                ->setLanguage('FR')
                ->setCoverImage(null)
                ->setStatus('available')
                ->setCreatedAt(new \DateTime())
                ->setCategory($category)
                ->setAuthor($author);

            $this->entityManager->persist($book);
            $created[] = $book;
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d livre(s) créé(s).', count($created)));
        $io->writeln('IDs à utiliser dans "ID du livre" :');
        foreach ($created as $book) {
            $io->writeln(sprintf(' - #%d : %s', $book->getId() ?? 0, (string) $book->getTitle()));
        }

        return Command::SUCCESS;
    }

    private function getOrCreateCategory(): Category
    {
        $existing = $this->entityManager->getRepository(Category::class)->findOneBy([]);
        if ($existing instanceof Category) {
            return $existing;
        }

        $category = (new Category())
            ->setName('Romans')
            ->setDescriptionCat('Catégorie de démonstration.')
            ->setIcon('bi-book');

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    private function getOrCreateAuthor(): Author
    {
        $existing = $this->entityManager->getRepository(Author::class)->findOneBy([]);
        if ($existing instanceof Author) {
            return $existing;
        }

        $author = (new Author())
            ->setFirstname('Alex')
            ->setLastname('Martin')
            ->setBiography('Auteur de démonstration pour LIBRARYHUB.')
            ->setNationality('FR');

        $this->entityManager->persist($author);
        $this->entityManager->flush();

        return $author;
    }
}


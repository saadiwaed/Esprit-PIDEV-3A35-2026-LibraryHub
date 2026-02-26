<?php

namespace App\Command;

use App\Entity\Book;
use App\Entity\BookCopy;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed:loan-test-data',
    description: 'Create test data for Loan CRUD',
)]
class SeedLoanTestDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            // Create test users (members)
            $io->writeln('Creating test members...');
            
            $user1 = new User();
            $user1->setEmail('member1@example.com');
            $user1->setFirstName('John');
            $user1->setLastName('Doe');
            $user1->setPassword($this->passwordHasher->hashPassword($user1, 'password123'));
            $user1->setRoles(['ROLE_USER']);
            $user1->setStatus('active');
            $user1->setMembershipType('basic');
            $user1->setCreatedAt(new \DateTime());
            $user1->setUpdatedAt(new \DateTime());
            $user1->setIsVerified(true);
            
            $this->entityManager->persist($user1);

            $user2 = new User();
            $user2->setEmail('member2@example.com');
            $user2->setFirstName('Jane');
            $user2->setLastName('Smith');
            $user2->setPassword($this->passwordHasher->hashPassword($user2, 'password123'));
            $user2->setRoles(['ROLE_USER']);
            $user2->setStatus('active');
            $user2->setMembershipType('premium');
            $user2->setCreatedAt(new \DateTime());
            $user2->setUpdatedAt(new \DateTime());
            $user2->setIsVerified(true);
            
            $this->entityManager->persist($user2);

            $this->entityManager->flush();
            $io->success('Created 2 test members');

            // Create test book copies
            $io->writeln('Creating test book copies...');
            
            for ($i = 1; $i <= 5; $i++) {
                $bookCopy = new BookCopy();
                $this->entityManager->persist($bookCopy);
            }

            $this->entityManager->flush();
            $io->success('Created 5 test book copies');

            $io->success('Test data created successfully!');
            $io->writeln('');
            $io->writeln('You can now create loans with:');
            $io->writeln('  Email: member1@example.com or member2@example.com');
            $io->writeln('  Password: password123');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error creating test data: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

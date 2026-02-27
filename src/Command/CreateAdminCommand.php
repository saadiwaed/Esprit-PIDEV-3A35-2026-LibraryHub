<?php

namespace App\Command;

use App\Entity\Role;
use App\Entity\User;
use App\Entity\ReadingProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un compte administrateur pour se connecter au back-office',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Email de l\'admin', 'admin@libraryhub.local')
            ->addArgument('password', InputArgument::OPTIONAL, 'Mot de passe', 'admin123');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        $userRepo = $this->entityManager->getRepository(User::class);
        if ($userRepo->findOneBy(['email' => $email])) {
            $io->error("Un utilisateur avec l'email \"{$email}\" existe déjà.");
            return Command::FAILURE;
        }

        $roleRepo = $this->entityManager->getRepository(Role::class);
        $adminRole = $roleRepo->findOneBy(['name' => 'ROLE_ADMIN']);
        if (!$adminRole) {
            $io->error('Le rôle ROLE_ADMIN n\'existe pas. Exécutez d\'abord database/seed_admin_roles.sql');
            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Admin');
        $user->setLastName('LibraryHub');
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setStatus('ACTIVE');
        $user->setCreatedAt(new \DateTime());
        $user->addRole($adminRole);

        $readingProfile = new ReadingProfile();
        $readingProfile->setUser($user);
        $readingProfile->setTotalBooksRead(0);

        $this->entityManager->persist($readingProfile);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('Compte admin créé.');
        $io->table(
            ['Email', 'Mot de passe'],
            [[$email, $password]]
        );
        $io->writeln('Connectez-vous sur /login avec ces identifiants.');
        return Command::SUCCESS;
    }
}

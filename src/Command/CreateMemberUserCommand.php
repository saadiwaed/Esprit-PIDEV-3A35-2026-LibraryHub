<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create-member',
    description: 'Cree (ou met Ã  jour) un utilisateur (membre ou admin) pour pouvoir se connecter.',
)]
final class CreateMemberUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email du membre', 'member@libraryhub.test')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Mot de passe du membre', 'password123')
            ->addOption('first-name', null, InputOption::VALUE_REQUIRED, 'Prenom', 'Membre')
            ->addOption('last-name', null, InputOption::VALUE_REQUIRED, 'Nom', 'LibraryHub')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Creer un compte admin (ROLE_LIBRARIAN)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = trim((string) $input->getOption('email'));
        $password = (string) $input->getOption('password');
        $firstName = trim((string) $input->getOption('first-name'));
        $lastName = trim((string) $input->getOption('last-name'));
        $isAdmin = (bool) $input->getOption('admin');

        if ($email === '' || $password === '' || $firstName === '' || $lastName === '') {
            $io->error('Email / mot de passe / prenom / nom sont obligatoires.');

            return Command::FAILURE;
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        $isNew = false;

        if (!$user instanceof User) {
            $user = new User();
            $user->setEmail($email);
            $isNew = true;
        }

        $user
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setRoles($isAdmin ? ['ROLE_LIBRARIAN'] : ['ROLE_USER'])
            ->setStatus('active')
            ->setMembershipType('basic')
            ->setIsVerified(true);

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        if ($isNew) {
            $this->entityManager->persist($user);
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            '%s : %s (roles: %s / email: %s / mot de passe: %s)',
            $isNew ? 'Membre cree' : 'Membre mis Ã  jour',
            $user->getFullName(),
            implode(',', $user->getRoles()),
            $email,
            $password
        ));

        return Command::SUCCESS;
    }
}


<?php

namespace App\Command;

use App\Entity\Role;
use App\Entity\User;
use App\Repository\RoleRepository;
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
    description: 'Create a member user (and ROLE_MEMBER if missing).',
)]
final class CreateMemberUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly RoleRepository $roleRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Member email.', 'member2@libraryhub.local')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Member password (generated if omitted).')
            ->addOption('first-name', null, InputOption::VALUE_REQUIRED, 'First name.', 'Member')
            ->addOption('last-name', null, InputOption::VALUE_REQUIRED, 'Last name.', 'One')
            ->addOption('phone', null, InputOption::VALUE_OPTIONAL, 'Phone number (format: +216XXXXXXXX or 8 digits).')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'User status (PENDING|ACTIVE|INACTIVE).', 'ACTIVE')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'If the email exists, update password and ensure ROLE_MEMBER.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = (string) $input->getOption('email');
        $plainPassword = $input->getOption('password');
        $firstName = (string) $input->getOption('first-name');
        $lastName = (string) $input->getOption('last-name');
        $phoneNumber = $this->normalizeTnPhone($input->getOption('phone'));
        $status = strtoupper((string) $input->getOption('status'));
        $force = (bool) $input->getOption('force');

        if (!in_array($status, ['PENDING', 'ACTIVE', 'INACTIVE'], true)) {
            $io->error('Invalid status. Use PENDING, ACTIVE, or INACTIVE.');
            return Command::INVALID;
        }

        if ($input->getOption('phone') !== null && $phoneNumber === null) {
            $io->error('Invalid phone. Expected +216XXXXXXXX or 8 digits.');
            return Command::INVALID;
        }

        if ($plainPassword === null || $plainPassword === '') {
            $plainPassword = $this->generatePassword();
        } else {
            $plainPassword = (string) $plainPassword;
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if ($user !== null && !$force) {
            $io->error(sprintf('User with email "%s" already exists. Re-run with --force to update it.', $email));
            return Command::FAILURE;
        }

        $roleMember = $this->roleRepository->findOneBy(['name' => 'ROLE_MEMBER']);
        if ($roleMember === null) {
            $roleMember = (new Role())->setName('ROLE_MEMBER')->setDescription('Member');
            $this->entityManager->persist($roleMember);
        }

        if ($user === null) {
            $user = new User();
            $user->setEmail($email);
            $user->setFirstName($firstName);
            $user->setLastName($lastName);
            $user->setStatus($status);
            $this->entityManager->persist($user);
        }

        if ($phoneNumber !== null) {
            $user->setPhoneNumber($phoneNumber);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->addRole($roleMember);

        $this->entityManager->flush();

        $io->success('Member user ready.');
        $io->writeln(sprintf('Email: %s', $email));
        $io->writeln(sprintf('Password: %s', $plainPassword));
        if ($phoneNumber !== null) {
            $io->writeln(sprintf('Phone: %s', $phoneNumber));
        }

        return Command::SUCCESS;
    }

    private function generatePassword(): string
    {
        return 'M!' . bin2hex(random_bytes(8));
    }

    private function normalizeTnPhone(mixed $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $value = preg_replace('/\s+/', '', trim((string) $input));
        $value = str_replace(['-', '(', ')', '.'], '', $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\+216\d{8}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^216(\d{8})$/', $value, $m) === 1) {
            return '+216' . $m[1];
        }

        if (preg_match('/^(\d{8})$/', $value, $m) === 1) {
            return '+216' . $m[1];
        }

        return null;
    }
}

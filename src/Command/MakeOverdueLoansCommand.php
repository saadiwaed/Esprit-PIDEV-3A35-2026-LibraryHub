<?php

namespace App\Command;

use App\Entity\Loan;
use App\Enum\LoanStatus;
use App\Repository\LoanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:make-overdue-loans',
    description: 'Marque des emprunts existants comme en retard (OVERDUE) pour faciliter les tests.'
)]
final class MakeOverdueLoansCommand extends Command
{
    public function __construct(
        private readonly LoanRepository $loanRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'Nombre d’emprunts à passer en retard.', 3)
            ->addOption('min-days', null, InputOption::VALUE_REQUIRED, 'Retard minimum (jours).', 3)
            ->addOption('max-days', null, InputOption::VALUE_REQUIRED, 'Retard maximum (jours).', 14)
            ->addOption('ids', null, InputOption::VALUE_REQUIRED, 'IDs d’emprunts à modifier (ex: 12,15,18).', '')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Autorise l’exécution même en prod.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $env = $this->kernel->getEnvironment();
        $force = (bool) $input->getOption('force');
        if ($env === 'prod' && !$force) {
            $output->writeln('<error>Refusé en prod. Relance avec --force si tu sais ce que tu fais.</error>');
            return Command::FAILURE;
        }

        $count = max(1, (int) $input->getOption('count'));
        $minDays = max(1, (int) $input->getOption('min-days'));
        $maxDays = max($minDays, (int) $input->getOption('max-days'));

        $idsRaw = trim((string) $input->getOption('ids'));
        $loans = [];
        if ($idsRaw !== '') {
            $ids = array_values(array_filter(array_map(static fn (string $v): int => (int) trim($v), explode(',', $idsRaw))));
            if ($ids === []) {
                $output->writeln('<error>Option --ids invalide.</error>');
                return Command::INVALID;
            }

            $loans = $this->loanRepository->createQueryBuilder('l')
                ->andWhere('l.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getResult();
        } else {
            $loans = $this->loanRepository->createQueryBuilder('l')
                ->andWhere('l.returnDate IS NULL')
                ->andWhere('l.status = :status')
                ->setParameter('status', LoanStatus::ACTIVE)
                ->orderBy('l.id', 'DESC')
                ->setMaxResults($count)
                ->getQuery()
                ->getResult();
        }

        if ($loans === []) {
            $output->writeln('<comment>Aucun emprunt éligible trouvé (ACTIVE + non retourné). Crée d’abord des emprunts actifs, puis relance.</comment>');
            return Command::SUCCESS;
        }

        $today = new \DateTimeImmutable('today');

        $changed = 0;
        foreach ($loans as $loan) {
            if (!$loan instanceof Loan) {
                continue;
            }

            $daysLate = random_int($minDays, $maxDays);
            $newDueDate = $today->sub(new \DateInterval(sprintf('P%dD', $daysLate)));

            $previousDue = $loan->getDueDate();
            $loan->setDueDate(\DateTime::createFromImmutable($newDueDate));
            $loan->setStatus(LoanStatus::OVERDUE);
            $loan->setReturnDate(null);

            $this->entityManager->persist($loan);
            $changed++;

            $output->writeln(sprintf(
                'Loan #%d -> OVERDUE (dueDate %s -> %s, retard=%d jours)',
                $loan->getId() ?? 0,
                $previousDue instanceof \DateTimeInterface ? $previousDue->format('Y-m-d') : 'null',
                $newDueDate->format('Y-m-d'),
                $daysLate
            ));
        }

        $this->entityManager->flush();

        $output->writeln(sprintf('<info>%d emprunt(s) mis en retard.</info>', $changed));
        $output->writeln('<comment>Tu peux maintenant tester la création de pénalité (retard) et la suggestion IA.</comment>');

        return Command::SUCCESS;
    }
}


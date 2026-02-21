<?php

namespace App\Command;

use App\Entity\Loan;
use App\Entity\Penalty;
use App\Enum\PaymentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:penalty-test-data',
    description: 'Create test data for Penalty CRUD',
)]
class SeedPenaltyTestDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $io->writeln('Creating test penalties...');

            // Get first loan if any exist
            $loan = $this->entityManager
                ->getRepository(Loan::class)
                ->findOneBy([]);

            if (!$loan) {
                $io->error('No loans found! Please create a loan first.');
                return Command::FAILURE;
            }

            // Create test penalties
            $penalties = [
                [
                    'amount' => 5.00,
                    'reason' => 'Late return - 5 days late',
                    'issueDate' => new \DateTime('-10 days'),
                    'status' => PaymentStatus::UNPAID,
                    'waived' => false,
                    'notes' => 'First reminder sent',
                ],
                [
                    'amount' => 10.00,
                    'reason' => 'Damaged book cover',
                    'issueDate' => new \DateTime('-20 days'),
                    'status' => PaymentStatus::PAID,
                    'waived' => false,
                    'notes' => 'Damage was significant',
                ],
                [
                    'amount' => 7.50,
                    'reason' => 'Missing pages',
                    'issueDate' => new \DateTime('-5 days'),
                    'status' => PaymentStatus::PARTIAL,
                    'waived' => false,
                    'notes' => null,
                ],
                [
                    'amount' => 3.00,
                    'reason' => 'Late return - 2 days late',
                    'issueDate' => new \DateTime('-2 days'),
                    'status' => PaymentStatus::UNPAID,
                    'waived' => true,
                    'notes' => 'Waived due to member loyalty',
                ],
            ];

            foreach ($penalties as $penalty) {
                $p = new Penalty();
                $p->setAmount($penalty['amount']);
                $p->setReason($penalty['reason']);
                $p->setIssueDate($penalty['issueDate']);
                $p->setStatus($penalty['status']);
                $p->setWaived($penalty['waived']);
                $p->setNotes($penalty['notes']);
                $p->setLoan($loan);

                $this->entityManager->persist($p);
            }

            $this->entityManager->flush();
            $io->success('Created 4 test penalties for Loan #' . $loan->getId());

            $io->info('You can now:');
            $io->info('  - View penalties: http://localhost:8000/admin/penalty/');
            $io->info('  - Create penalty: http://localhost:8000/admin/penalty/new');
            $io->info('  - View penalty: http://localhost:8000/admin/penalty/1');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error creating test data: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

<?php

namespace App\Command;

use App\Entity\Penalty;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug:penalties',
    description: 'List all penalties in database',
)]
class DebugPenaltiesCommand extends Command
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
            /** @var Penalty[] $penalties */
            $penalties = $this->entityManager->getRepository(Penalty::class)->findAll();

            $io->writeln('Total penalties: ' . count($penalties));

            if (empty($penalties)) {
                $io->warning('No penalties found in database!');
                $io->info('Run: php bin/console app:seed:penalty-test-data');
            } else {
                $io->info('Penalties in database:');
                foreach ($penalties as $penalty) {
                    $io->writeln("  ID: {$penalty->getId()}, Amount: \${$penalty->getAmount()}, Reason: {$penalty->getReason()}, Loan: {$penalty->getLoan()->getId()}");
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

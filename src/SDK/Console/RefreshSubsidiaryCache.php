<?php

namespace BayWaReLusy\UsersAPI\SDK\Console;

use BayWaReLusy\UsersAPI\SDK\UsersApiClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'users-api-sdk:refresh-subsidiary-cache')]
class RefreshSubsidiaryCache extends Command
{
    public function __construct(
        protected UsersApiClient $usersApiClient
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription(
            'Refresh Subsidiary Cache. Subsidiaries are fetched from the Users API and written into the Cache.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $output->writeln(
                sprintf("[%s] Starting Refresh of Subsidiary Cache...", (new \DateTime())->format('c'))
            );

            $this->usersApiClient
                ->setConsole($output)
                ->getSubsidiaries(true);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(
                (new \DateTime())->format('[c] ') . "Process finished with an error : " . $e->getMessage(),
            );

            $output->writeln($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}

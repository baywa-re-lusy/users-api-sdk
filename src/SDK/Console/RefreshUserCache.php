<?php

namespace BayWaReLusy\UsersAPI\SDK\Console;

use BayWaReLusy\UsersAPI\SDK\UsersApiClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'users-api-sdk:refresh-user-cache')]
class RefreshUserCache extends Command
{
    public function __construct(
        protected UsersApiClient $usersApiClient
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Refresh User Cache. Users are fetched from the Users API and written into the Cache.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $output->writeln(
                sprintf("[%s] Starting Refresh of Users Cache...", (new \DateTime())->format('c'))
            );

            $this->usersApiClient
                ->setConsole($output)
                ->getUsers(true);

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

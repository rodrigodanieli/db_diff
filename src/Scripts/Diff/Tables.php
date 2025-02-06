<?php

namespace App\Scripts\Diff;

use Migration\Table\Application\Diff;
use Migration\Table\Application\DiffDto;
use Sohris\Core\Loader;
use Sohris\Core\Server;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Tables extends Command
{
    protected function configure(): void
    {
        $this
            ->setName("diff:tables")
            ->setDescription('Execute a diff in tables from bases')
            ->addArgument('project', InputArgument::REQUIRED, 'Project Id')
            ->addArgument('base', InputArgument::REQUIRED, 'Base usada como referencia no diff')
            ->addArgument('compare', InputArgument::REQUIRED, 'Base a ser aplicada no diff')
            ->addOption('worker', 'w', InputArgument::OPTIONAL, 'Workers');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutputInterface) {
            throw new \LogicException('This command accepts only an instance of "ConsoleOutputInterface".');
        }
        Server::setOutput($output);
        Loader::loadClasses();
        $base = $input->getArgument("base");
        $compare = $input->getArgument("compare");
        $project = $input->getArgument("project");


        $diff_dto = new DiffDto($project, $base, $compare);
        
        if($input->hasOption("worker"))
        {
            $diff_dto->workers = $input->getOption("worker");
        }
        $diff = new Diff;

        $diff->execute($diff_dto);

        return Command::SUCCESS;
    }
}

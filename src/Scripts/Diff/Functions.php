<?php

namespace App\Scripts\Diff;

use Migration\Functions\Application\Diff;
use Migration\Functions\Application\DiffDto;
use Sohris\Core\Loader;
use Sohris\Core\Server;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Functions extends Command
{
    protected function configure(): void
    {
        $this
            ->setName("diff:functions")
            ->setDescription('Execute a diff in Functions from bases')
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

        if ($input->hasOption("worker")) {
            $w = $input->getOption("worker");
            if (!is_null($w))
                $diff_dto->workers = $w;
        }
        $diff = new Diff;

        $diff->execute($diff_dto);

        return Command::SUCCESS;
    }
}

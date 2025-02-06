<?php

namespace App\Scripts\Dump;

use Exception;
use Migration\Database\Application\GetBasesMigrationFromProject;
use Migration\Database\Application\GetBasesMigrationFromProjectDto;
use Migration\Database\Application\GetDatabaseFromProject;
use Migration\Database\Application\GetDatabaseFromProjectDto;
use Migration\Database\Infrastructure\Json as InfrastructureRepository;
use Migration\Event\Application\Diff;
use Migration\Event\Application\DiffDto;
use Migration\Event\Application\GetEventCreation;
use Migration\Event\Application\GetEventCreationDto;
use Migration\Event\Application\GetEvents;
use Migration\Event\Application\GetEventsDto;
use Migration\Event\Infrastructure\Repository;
use Sohris\Core\Loader;
use Sohris\Core\Logger;
use Sohris\Core\Server;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Events extends Command
{
    protected function configure(): void
    {
        $this
            ->setName("dump:events")
            ->setDescription('Execute a diff in Events from bases')
            ->addArgument('project', InputArgument::REQUIRED, 'Project Id')
            ->addArgument('base', InputArgument::REQUIRED, 'Base usada como referencia no diff');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutputInterface) {
            throw new \LogicException('This command accepts only an instance of "ConsoleOutputInterface".');
        }
        Server::setOutput($output);
        Loader::loadClasses();
        $logger = new Logger("DumpEvents");
        $base = $input->getArgument("base");
        $project = $input->getArgument("project");

        $logger->info("Dump Data $project $base");
        //Events
        $event_repository = new Repository;
        $get_events_from_base = new GetEvents($event_repository);
        $get_creation_event = new GetEventCreation($event_repository);
        $database_repository = new InfrastructureRepository;
        $get_database_from_project = new GetDatabaseFromProject($database_repository);
        $get_bases_from_project = new GetBasesMigrationFromProject($database_repository);


        if ($input->hasOption("worker")) {
            $w = $input->getOption("worker");
        }
        
        $database_reference_dto = new GetDatabaseFromProjectDto($project, $base);
        $database_reference = $get_database_from_project->execute($database_reference_dto);
        $bases = $get_bases_from_project->execute(new GetBasesMigrationFromProjectDto($project));
        $script = [];
        $total = 0;
        $events = [];

        foreach ($bases as $base) {
            $script[$base] = [];
            try {
                $event_dto = new GetEventsDto($database_reference, $base);
                $events[$base] = $get_events_from_base->execute($event_dto);
                $total += count($events[$base]);
            } catch (Exception $e) {
            }
        }

        foreach ($events as $base => $ev) {
            foreach ($ev as $procedure) {
                try {
                    $compare_event_dto = new GetEventCreationDto($base, $procedure, $database_reference);
                    $script[$base][$procedure] = $get_creation_event->execute($compare_event_dto);
                } catch (Exception $e) {
                }
            }
        }

        return Command::SUCCESS;
    }
}

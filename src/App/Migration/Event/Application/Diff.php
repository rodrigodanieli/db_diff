<?php

namespace Migration\Event\Application;

use Exception;
use Migration\Database\Application\GetBasesMigrationFromProject;
use Migration\Database\Application\GetBasesMigrationFromProjectDto;
use Migration\Database\Application\GetDatabaseFromProject;
use Migration\Database\Application\GetDatabaseFromProjectDto;
use Migration\Database\Application\GetTablesMigrationFromProject;
use Migration\Database\Application\GetTablesMigrationFromProjectDto;
use Migration\Database\Infrastructure\Json as DatabaseRepository;
use Migration\Event\Infrastructure\Repository;
use Migration\Table\Infrastructure\Repository as InfrastructureRepository;
use parallel\Runtime;
use Sohris\Core\Logger;
use Sohris\Core\Server;
use Sohris\Core\Utils as CoreUtils;
use Symfony\Component\Console\Output\ConsoleOutput;

class Diff
{
    private Logger $logger;
    private string $scripts_dir;
    private int $workers = 1;



    private GetDatabaseFromProject $get_database_from_project;
    private GetBasesMigrationFromProject $get_bases_from_project;

    private GetEventsFromBase $get_events_from_base;
    private Compare $compare_event;


    private array $events = [];
    private int $project;
    private string $base_1;
    private string $base_2;

    private array $diff_script = [];
    private array $rollback_script = [];


    public function __construct()
    {
        $this->logger = new Logger("DiffEvent");
        $base = CoreUtils::getConfigFiles('system')['files_dir'];
        $this->scripts_dir = "$base/diff/" . date("Y-m-d");

        $this->get_database_from_project = new GetDatabaseFromProject(new DatabaseRepository);
        $this->get_bases_from_project = new GetBasesMigrationFromProject(new DatabaseRepository);

        $this->get_events_from_base = new GetEventsFromBase(new Repository);
    }

    public function execute(DiffDto $dto)
    {
        $diff_name = $dto->base_1 . "-" . $dto->base_2;

        $this->scripts_dir .= "/$diff_name";
        $this->logger->info("Doing Project " . $dto->project . " Base " . $dto->base_1 . " To " . $dto->base_2);
        $this->logger->info("Workers " . $dto->workers);

        if (!is_null($dto->workers))
            $this->workers = $dto->workers;

        $this->project = $dto->project;
        $this->base_1 = $dto->base_1;
        $this->base_2 = $dto->base_2;


        $start = CoreUtils::microtimeFloat();
        $this->getBasesEvents();
        $this->diff_script = $this->process();
        $this->logger->debug("Process Diff in " . (round((CoreUtils::microtimeFloat() - $start), 2)) . "sec");

        $start = CoreUtils::microtimeFloat();
        $this->getBasesEvents(true);
        $this->rollback_script = $this->process(true);
        $this->logger->debug("Process Rollback in " . (round((CoreUtils::microtimeFloat() - $start), 2)) . "sec");


        $this->logger->info("Generate Diff Script");
        $this->generate($this->diff_script);

        $this->logger->info("Generate Rollback Script");
        $this->generate($this->rollback_script, "rollback");
    }

    private function getBasesEvents($inverse = false)
    {

        $this->events = [];
        $base_c = $this->base_1;
        $compare = $this->base_2;

        if ($inverse) {
            $base_c = $this->base_2;
            $compare = $this->base_1;
        }

        $this->logger->info("Getting Bases Events");
        $bases = $this->get_bases_from_project->execute(new GetBasesMigrationFromProjectDto($this->project));

        $database_reference_dto = new GetDatabaseFromProjectDto($this->project, $base_c);
        $database_reference = $this->get_database_from_project->execute($database_reference_dto);

        $database_to_apply_dto = new GetDatabaseFromProjectDto($this->project, $compare);
        $database_to_apply = $this->get_database_from_project->execute($database_to_apply_dto);

        foreach ($bases as $base) {
            try {
                $this->logger->debug("Base $base");
                $event_dto = new GetEventsFromBaseDto($database_reference, $database_to_apply, $base);
                $events = $this->get_events_from_base->execute($event_dto);
                foreach ($events as $proc) {
                    $this->events[] = [
                        "base" => $base,
                        "event" => $proc
                    ];
                }
            } catch (Exception $e) {
                $this->logger->exception($e);
                continue;
            }
        }
    }

    private function process($inverse = false): array
    {
        $base = $this->base_1;
        $compare = $this->base_2;

        if ($inverse) {
            $base = $this->base_2;
            $compare = $this->base_1;
        }

        if ($this->workers > 1) {
            $script = [];
            $chunk = array_chunk($this->events, (int)ceil(count($this->events) / $this->workers));
            $runtimes = [];
            $futures = [];
            for ($i = 0; $i < $this->workers; $i++) {
                if (!isset($chunk[$i])) continue;
                $runtimes[$i] = new Runtime(Server::getRootDir() . "/bootstrap.php");
                $futures[$i] = $runtimes[$i]->run(static function ($project, $base_1, $base_2, $data, $worker, $root_dir, $verbose) {
                    $server = Server::getServer();
                    $server->setRootDir($root_dir);
                    $server->loadServer();
                    $server->setOutput(new ConsoleOutput($verbose));
                    return self::diff($project, $base_1, $base_2, $data, $worker);
                }, [$this->project, $base, $compare, $chunk[$i], $i, Server::getRootDir(), Server::getOutput()->getVerbosity()]);
            }

            do {
                foreach ($futures as $key => $future)
                    if ($future->done() || $future->cancelled()) {
                        $this->logger->debug("Depara");
                        $value = $future->value();
                        if (!empty($value)) {
                            $dev = json_decode($value, true);
                            foreach ($dev as $base1 => $values) {
                                if (!isset($script[$base1]))
                                    $script[$base1] = "";
                                $script[$base1] .= $values;
                            }
                        }
                        unset($runtimes[$key]);
                        unset($futures[$key]);
                    }
            } while (!empty($runtimes));

            return $script;
        } else {
            return json_decode(self::diff($this->project, $base, $compare, $this->events), true);
        }
    }

    private static function diff($project, $base_1, $base_2, $data, $worker = "")
    {
        $logger = new Logger("DiffEvent$worker");

        $script = [];
        $get_database_from_project = new GetDatabaseFromProject(new DatabaseRepository);

        $database_reference_dto = new GetDatabaseFromProjectDto($project, $base_1);
        $database_reference = $get_database_from_project->execute($database_reference_dto);

        $database_to_apply_dto = new GetDatabaseFromProjectDto($project, $base_2);
        $database_to_apply = $get_database_from_project->execute($database_to_apply_dto);
        $compare_event = new Compare(new Repository);
        $count = 0;
        $total = count($data);
        foreach ($data as $info) {
            try {
                $count++;
                $base = $info['base'];
                $event = $info['event'];
                $logger->debug("Process $base $event ($count/$total)");
                $compare_event_dto = new CompareDto($base, $event, $database_reference, $database_to_apply);
                $value = $compare_event->execute($compare_event_dto);
                if (!isset($script[$base]) && !empty($value))
                    $script[$base] = "";
                if (!empty($value))
                    $script[$base] .= $value;
            } catch (Exception $e) {
                $logger->exception($e);
            }
        }
        return json_encode($script);
    }

    private function generate($data, $folder = "structure")
    {
        foreach ($data as $base => $scr) {
            if (empty($scr)) continue;
            $path = $this->scripts_dir . "/$folder/$base";
            CoreUtils::checkFolder($path, "create");
            $fscript = "/* *******STRUCTURE SCRIPT FROM $base *******\nGenerete: " . date("Y-m-d H:i:s") . "\nPowered By: (Rodrigo Danieli) rodrigo.danieli@dbsnoop.com\n Database Host Reference: " . $this->base_1 . " \n Database Host to Apply: " . $this->base_2 . "*/\n\n\n";
            $fscript .= "\nCREATE DATABASE IF NOT EXISTS $base;\n";
            $fscript .= $scr;
            file_put_contents("$path/events.sql", $fscript);
        }
    }
}

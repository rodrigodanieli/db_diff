<?php

namespace App\Scripts;

use Exception;
use React\EventLoop\Loop;
use Sohris\Core\Utils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Migration\Database\Application\GetBasesMigrationFromProject;
use Migration\Database\Application\GetBasesMigrationFromProjectDto;
use Migration\Database\Application\GetDatabaseFromProject;
use Migration\Database\Application\GetDatabaseFromProjectDto;
use Migration\Database\Application\GetTablesMigrationFromProject;
use Migration\Database\Application\GetTablesMigrationFromProjectDto;
use Migration\Database\Infrastructure\Json as DatabaseRepository;
use Migration\Event\Application\GetEventCreation;
use Migration\Event\Application\GetEventCreationDto;
use Migration\Event\Application\GetEvents;
use Migration\Event\Application\GetEventsDto;
use Migration\Event\Infrastructure\Repository as EventRepository;
use Migration\Functions\Application\GetFunctionCreation;
use Migration\Functions\Application\GetFunctionCreationDto;
use Migration\Functions\Application\GetFunctions;
use Migration\Functions\Application\GetFunctionsDto;
use Migration\Functions\Infrastructure\Repository as FunctionRepository;
use Migration\Procedure\Application\GetProcedureCreation;
use Migration\Procedure\Application\GetProcedureCreationDto;
use Migration\Procedure\Application\GetProcedures;
use Migration\Procedure\Application\GetProceduresDto;
use Migration\Procedure\Infrastructure\Repository as ProcedureRepository;
use Migration\Table\Application\GetCreationTable;
use Migration\Table\Application\GetCreationTableDto;
use Migration\Table\Infrastructure\Repository as TableRepository;
use Migration\Trigger\Application\GetTriggerCreation;
use Migration\Trigger\Application\GetTriggerCreationDto;
use Migration\Trigger\Application\GetTriggers;
use Migration\Trigger\Application\GetTriggersDto;
use Migration\Trigger\Infrastructure\Repository as TriggerRepository;
use Migration\View\Application\GetViewCreation;
use Migration\View\Application\GetViewCreationDto;
use Migration\View\Application\GetViews;
use Migration\View\Application\GetViewsDto;
use Migration\View\Infrastructure\Repository as ViewRepository;
use parallel\Runtime;
use Sohris\Core\Server;
use Sohris\Core\Tools\Worker\ChannelController;
use Symfony\Component\Console\Helper\ProgressBar;

class DumpMysql extends Command
{
    const OPTIONS = ["table", "view", "function", "procedure", "event", "trigger"];
    const CACHE_FOLDER = "./cache2";
    const FILE_FOLDER = "./files/database_script";
    private static GetDatabaseFromProject $get_database_from_project;
    private static GetBasesMigrationFromProject $get_bases_from_project;

    private static GetTablesMigrationFromProject $get_tables_from_project;
    private static GetCreationTable $get_creation_table;

    private static GetProcedureCreation $get_creation_procedure;
    private static GetProcedures $get_procedures_from_base;

    private static GetFunctionCreation $get_creation_function;
    private static GetFunctions $get_functions_from_base;

    private static GetViewCreation $get_creation_view;
    private static GetViews $get_views_from_base;

    private static GetEventCreation $get_creation_event;
    private static GetEvents $get_events_from_base;

    private static GetTriggerCreation $get_creation_trigger;
    private static GetTriggers $get_triggers_from_base;

    protected function configure(): void
    {
        $this
            ->setName("automations:dump")
            ->setDescription('Dump database Mysql from project')
            ->addArgument('project', InputArgument::REQUIRED, 'Project Id')
            ->addArgument('base', InputArgument::REQUIRED, 'Base usada como referencia')
            ->addOption('all', 'a', NULL, 'Default - Compare All Components (Tables - Procedures - Functions - Views - Functions - Events - Triggers)')
            ->addOption('table', 't', NULL, 'Compare Tables')
            ->addOption('procedure', 'p', NULL, 'Compare Procedures')
            ->addOption('view', 'w', NULL, 'Compare Views')
            ->addOption('function', 'f', NULL, 'Compare Functions')
            ->addOption('event', 'e', NULL, 'Compare Events')
            ->addOption('trigger', 'g', NULL, 'Compare Triggers');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutputInterface) {
            throw new \LogicException('This command accepts only an instance of "ConsoleOutputInterface".');
        }
        self::multiThread($input, $output);

        // if ($opts['parallel']) {
        //     self::multiThread($input, $output);
        // } else {
        //     self::singleThread($input, $output);
        // }
        return Command::SUCCESS;
    }

    public static function firstRun()
    {
        $database_repository = new DatabaseRepository;
        self::$get_database_from_project = new GetDatabaseFromProject($database_repository);
        self::$get_bases_from_project = new GetBasesMigrationFromProject($database_repository);

        //Tables
        $tables_repository = new TableRepository;
        self::$get_tables_from_project = new GetTablesMigrationFromProject($database_repository);
        self::$get_creation_table = new GetCreationTable($tables_repository);

        //Procedures
        $procedure_repository = new ProcedureRepository;
        self::$get_procedures_from_base = new GetProcedures($procedure_repository);
        self::$get_creation_procedure = new GetProcedureCreation($procedure_repository);

        //Functions
        $function_repository = new FunctionRepository;
        self::$get_functions_from_base = new GetFunctions($function_repository);
        self::$get_creation_function = new GetFunctionCreation($function_repository);

        //Views
        $view_repository = new ViewRepository;
        self::$get_views_from_base = new GetViews($view_repository);
        self::$get_creation_view = new GetViewCreation($view_repository);

        //Events
        $event_repository = new EventRepository;
        self::$get_events_from_base = new GetEvents($event_repository);
        self::$get_creation_event = new GetEventCreation($event_repository);

        //Trigger
        $trigger_repository = new TriggerRepository;
        self::$get_triggers_from_base = new GetTriggers($trigger_repository);
        self::$get_creation_trigger = new GetTriggerCreation($trigger_repository);
    }

    private static function multiThread($input, $output)
    {
        $opts = $input->getOptions();
        $do = [];
        if ($opts['all']) {
            $do = self::OPTIONS;
        } else {
            foreach (self::OPTIONS as $opt)
                if ($opts[$opt])
                    $do[] = $opt;
        }
        $section = $output->section();
        $error_section = null;
        $args = $input->getArguments();
        $section->writeln("Dumping Project $args[project] base $args[base] ");
        $workers = [];
        $sections = [];
        $sections2 = [];
        $progress = [];
        $scripts = [];
        foreach ($do as $key => $to_do) {
            //$section->writeln("Configuring $to_do");
            $sections2[$key] = $output->section();
            $sections[$key] = $output->section();
            $progress[$key] = new ProgressBar($sections[$key]);
            $count = 15 - strlen($to_do);
            $sections2[$key]->overwrite(["----------------------------", strtoupper($to_do) . " Waiting"]);
            $workers[$key] = new Runtime(Server::getRootDir() . "/bootstrap.php");
            ChannelController::on(sha1($key), "set_total", function ($total) use (&$sections, &$progress, $key, $to_do) {
                $progress[$key]->setMaxSteps($total);
                $count = 15 - strlen($to_do);
                $progress[$key]->setFormat($to_do . '%current:' . $count . 's%/%max% [%bar%] %percent:1s%% %elapsed:3s%/%estimated:-3s% %message%');
            });
            ChannelController::on(sha1($key), "update", function ($name) use (&$sections, &$progress, $key) {

                $progress[$key]->setMessage("( $name )");
                $progress[$key]->advance();
            });
            ChannelController::on(sha1($key), "update_state", function ($name) use (&$sections2, $to_do, $key) {
                $sections2[$key]->overwrite(["----------------------------", strtoupper($to_do) . " $name"]);
            });
            ChannelController::on(sha1($key), "finish", function ($values) use (&$scripts, &$sections2, &$progress, $key, $to_do) {
                $sections2[$key]->overwrite(["----------------------------", strtoupper($to_do) . " Finish"]);
                $count = 15 - strlen($to_do);
                $progress[$key]->setFormat(strtoupper($to_do) . '%current:' . $count . 's%');
                $progress[$key]->setProgress(100);
                foreach ($values as $schema => $script) {
                    if (!array_key_exists($schema, $scripts))
                        $scripts[$schema] = [];
                    $scripts[$schema][$to_do] = $script;
                }
            });
            ChannelController::on(sha1($key), "error", function ($values) use (&$error_section, $to_do) {
                $values = json_decode($values, true);
                $desc = $values['desc'];
                $e = unserialize($values['e']);
                if (!is_null($error_section))
                    $error_section->writeln("[" . date("Y-m-d H:i:s") . "][ERROR][" . strtoupper($to_do) . "] $desc - " . $e->getMessage() . " " . $e->getFile() . " (" . $e->getLine() . ")");
            });
            $progress[$key]->start();
            switch ($to_do) {
                case "table":
                    $workers[$key]->run(static function ($args, $key) {
                        self::tables($args['project'], $args['base'], $key);
                    }, [$args, $key]);
                    break;
                case "procedure":
                    $workers[$key]->run(static function ($args, $key) {
                        self::procedures($args['project'], $args['base'], $key);
                    }, [$args, $key]);
                    break;
                case "function":
                    $workers[$key]->run(static function ($args, $key) {
                        self::functions($args['project'], $args['base'], $key);
                    }, [$args, $key]);
                    break;
                case "view":
                    $workers[$key]->run(static function ($args, $key) {
                        self::views($args['project'], $args['base'], $key);
                    }, [$args, $key]);
                    break;
                case "event":
                    $workers[$key]->run(static function ($args, $key) {
                        self::events($args['project'], $args['base'], $key);
                    }, [$args, $key]);
                    break;
                case "trigger":
                    $workers[$key]->run(static function ($args, $key) {
                        self::triggers($args['project'], $args['base'], $key);
                    }, [$args, $key]);
                    break;
            }
        }
        $error_section = $output->section();
        $error_section->writeln("----------------------------------------------------------------");
        $error_section->writeln("LOGS:");
        Loop::addPeriodicTimer(5, function () use (&$scripts, &$progress, $args) {
            foreach ($progress as $p)
                if ($p->getProgress() < 100) return;

            self::mountScripts($scripts, $args['base']);
            Loop::stop();
        });

        Loop::run();
    }

    private static function tables($project_id, $base_1, $event, $channel = true)
    {
        $cache = self::has_cache("table");
        if (!empty($cache)) {
            ChannelController::send(sha1($event), "finish", $cache);
            return;
        }
        self::firstRun();
        ChannelController::send(sha1($event), "update_state", "Getting Bases");
        $tables_dto = new GetTablesMigrationFromProjectDto($project_id);
        $database_reference_dto = new GetDatabaseFromProjectDto($project_id, $base_1);
        $database_reference = self::$get_database_from_project->execute($database_reference_dto);
        $script = [];
        $tables = self::$get_tables_from_project->execute($tables_dto);
        $total = count($tables);
        ChannelController::send(sha1($event), "set_total", $total);
        foreach ($tables as $table_info) {
            $table = $table_info['table'];
            $base = $table_info['base'];
            ChannelController::send(sha1($event), "update_state", "Processing $base");
            ChannelController::send(sha1($event), "update", $base . "." . $table);
            try {
                if (!array_key_exists($base, $script))
                    $script[$base] = [];
                $dto = new GetCreationTableDto($base, $table, $database_reference);
                $script[$base][$table] = self::$get_creation_table->execute($dto);
            } catch (Exception $e) {
                ChannelController::send(sha1($event), "error", json_encode(["desc" => $base . "." . $table, "e" => serialize($e)]));
            }
        }
        self::cache($script, "table");
        ChannelController::send(sha1($event), "finish", $script);
    }

    private static function procedures($project_id, $base_1, $event, $channel = true)
    {
        $cache = self::has_cache("procedure");
        if (!empty($cache)) {
            ChannelController::send(sha1($event), "finish", $cache);
            return;
        }
        self::firstRun();
        ChannelController::send(sha1($event), "update_state", "Getting Bases");
        $database_reference_dto = new GetDatabaseFromProjectDto($project_id, $base_1);
        $database_reference = self::$get_database_from_project->execute($database_reference_dto);
        $bases = self::$get_bases_from_project->execute(new GetBasesMigrationFromProjectDto($project_id));

        $script = [];
        $procedures = [];
        $total = 0;

        foreach ($bases as $base) {
            ChannelController::send(sha1($event), "update_state", "Getting Components From $base");
            try {
                $procedure_dto = new GetProceduresDto($database_reference, $base);
                $procedures[$base] = self::$get_procedures_from_base->execute($procedure_dto);
                $total += count($procedures[$base]);
            } catch (Exception $e) {
                ChannelController::send(sha1($event), "error", json_encode(["desc" => "", "e" => serialize($e)]));
            }
        }

        ChannelController::send(sha1($event), "set_total", $total);
        foreach ($procedures as $base => $procs) {
            $script[$base] = [];
            ChannelController::send(sha1($event), "update_state", "Processing $base");
            foreach ($procs as $procedure) {
                ChannelController::send(sha1($event), "update", $base . "." . $procedure);
                try {
                    $dto = new GetProcedureCreationDto($base, $procedure, $database_reference);
                    $script[$base][$procedure] = self::$get_creation_procedure->execute($dto);
                } catch (Exception $e) {
                    ChannelController::send(sha1($event), "error", json_encode(["desc" => $base . "." . $procedure, "e" => serialize($e)]));
                }
            }
        }
        self::cache($script, "procedure");
        ChannelController::send(sha1($event), "finish", $script);
    }

    private static function functions($project_id, $base_1, $event, $channel = true)
    {
        $cache = self::has_cache("function");
        if (!empty($cache)) {
            ChannelController::send(sha1($event), "finish", $cache);
            return;
        }
        self::firstRun();
        ChannelController::send(sha1($event), "update_state", "Getting Bases");
        $database_reference_dto = new GetDatabaseFromProjectDto($project_id, $base_1);
        $database_reference = self::$get_database_from_project->execute($database_reference_dto);
        $bases = self::$get_bases_from_project->execute(new GetBasesMigrationFromProjectDto($project_id));

        $script = [];
        $functions = [];
        $total = 0;

        foreach ($bases as $base) {
            ChannelController::send(sha1($event), "update_state", "Getting Components From $base");
            try {
                $function_dto = new GetFunctionsDto($database_reference, $base);
                $functions[$base] = self::$get_functions_from_base->execute($function_dto);
                $total += count($functions[$base]);
            } catch (Exception $e) {
                ChannelController::send(sha1($event), "error", json_encode(["desc" => "", "e" => serialize($e)]));
            }
        }

        ChannelController::send(sha1($event), "set_total", $total);
        foreach ($functions as $base => $func) {
            $script[$base] = [];
            ChannelController::send(sha1($event), "update_state", "Processing $base");
            $script[$base] = [];
            foreach ($func as $procedure) {
                ChannelController::send(sha1($event), "update", $base . "." . $procedure);
                try {
                    $dto = new GetFunctionCreationDto($base, $procedure, $database_reference);
                    $script[$base][$procedure] = self::$get_creation_function->execute($dto);
                } catch (Exception $e) {
                    ChannelController::send(sha1($event), "error", json_encode(["desc" => "$base.$procedure", "e" => serialize($e)]));
                }
            }
        }
        self::cache($script, "function");
        ChannelController::send(sha1($event), "finish", $script);
    }

    private static function views($project_id, $base_1, $event, $channel = true)
    {
        $cache = self::has_cache("view");
        if (!empty($cache)) {
            ChannelController::send(sha1($event), "finish", $cache);
            return;
        }
        self::firstRun();
        ChannelController::send(sha1($event), "update_state", "Getting Bases");
        $database_reference_dto = new GetDatabaseFromProjectDto($project_id, $base_1);
        $database_reference = self::$get_database_from_project->execute($database_reference_dto);
        $bases = self::$get_bases_from_project->execute(new GetBasesMigrationFromProjectDto($project_id));

        $script = [];
        $views = [];
        $total = 0;

        foreach ($bases as $base) {
            ChannelController::send(sha1($event), "update_state", "Getting Components From $base");
            try {
                $view_dto = new GetViewsDto($database_reference, $base);
                $views[$base] = self::$get_views_from_base->execute($view_dto);
                $total += count($views[$base]);
            } catch (Exception $e) {
                ChannelController::send(sha1($event), "error", json_encode(["desc" => "", "e" => serialize($e)]));
            }
        }

        ChannelController::send(sha1($event), "set_total", $total);
        foreach ($views as $base => $vw) {
            $script[$base] = [];
            ChannelController::send(sha1($event), "update_state", "Processing $base");
            foreach ($vw as $procedure) {
                ChannelController::send(sha1($event), "update", $base . "." . $procedure);
                try {
                    $dto = new GetViewCreationDto($base, $procedure, $database_reference);
                    $script[$base][$procedure] = self::$get_creation_view->execute($dto);
                } catch (Exception $e) {
                    ChannelController::send(sha1($event), "error", json_encode(["desc" => "$base.$procedure", "e" => serialize($e)]));
                }
            }
        }
        self::cache($script, "view");
        ChannelController::send(sha1($event), "finish", $script);
    }

    private static function events($project_id, $base_1, $event, $channel = true)
    {
        $cache = self::has_cache("event");
        if (!empty($cache)) {
            ChannelController::send(sha1($event), "finish", $cache);
            return;
        }
        self::firstRun();
        ChannelController::send(sha1($event), "update_state", "Getting Bases");
        $database_reference_dto = new GetDatabaseFromProjectDto($project_id, $base_1);
        $database_reference = self::$get_database_from_project->execute($database_reference_dto);
        $bases = self::$get_bases_from_project->execute(new GetBasesMigrationFromProjectDto($project_id));
        $script = [];
        $total = 0;
        $events = [];

        foreach ($bases as $base) {
            $script[$base] = [];
            ChannelController::send(sha1($event), "update_state", "Getting Components From $base");
            try {
                $event_dto = new GetEventsDto($database_reference, $base);
                $events[$base] = self::$get_events_from_base->execute($event_dto);
                $total += count($events[$base]);
            } catch (Exception $e) {
                ChannelController::send(sha1($event), "error", json_encode(["desc" => "", "e" => serialize($e)]));
            }
        }

        ChannelController::send(sha1($event), "set_total", $total);
        foreach ($events as $base => $ev) {
            ChannelController::send(sha1($event), "update_state", "Processing $base");
            foreach ($ev as $procedure) {
                ChannelController::send(sha1($event), "update", $base . "." . $procedure);
                try {
                    $compare_event_dto = new GetEventCreationDto($base, $procedure, $database_reference);
                    $script[$base][$procedure] = self::$get_creation_event->execute($compare_event_dto);
                } catch (Exception $e) {
                    ChannelController::send(sha1($event), "error", json_encode(["desc" => "$base.$procedure", "e" => serialize($e)]));
                }
            }
        }
        self::cache($script, "event");
        ChannelController::send(sha1($event), "finish", $script);
    }

    private static function triggers($project_id, $base_1, $event, $channel = true)
    {
        $cache = self::has_cache("trigger");
        if (!empty($cache)) {
            ChannelController::send(sha1($event), "finish", $cache);
            return;
        }
        self::firstRun();
        ChannelController::send(sha1($event), "update_state", "Getting Bases");
        $database_reference_dto = new GetDatabaseFromProjectDto($project_id, $base_1);
        $database_reference = self::$get_database_from_project->execute($database_reference_dto);
        $bases = self::$get_bases_from_project->execute(new GetBasesMigrationFromProjectDto($project_id));

        $script = [];
        $triggers = [];
        $total = 0;
        foreach ($bases as $base) {
            ChannelController::send(sha1($event), "update_state", "Getting Components From $base");
            try {
                $trigger_dto = new GetTriggersDto($database_reference, $base);
                $triggers[$base] = self::$get_triggers_from_base->execute($trigger_dto);
                $total += count($triggers);
            } catch (Exception $e) {
                ChannelController::send(sha1($event), "error", json_encode(["desc" => "", "e" => serialize($e)]));
            }
        }
        ChannelController::send(sha1($event), "set_total", $total);
        foreach ($triggers as $base => $tr) {
            $script[$base] = [];
            ChannelController::send(sha1($event), "update_state", "Processing $base");
            $triggers_script = "";
            foreach ($tr as $procedure) {
                ChannelController::send(sha1($event), "update", $base . "." . $procedure);
                try {
                    $compare_trigger_dto = new GetTriggerCreationDto($base, $procedure, $database_reference);
                    $script[$base][$procedure] =  self::$get_creation_trigger->execute($compare_trigger_dto);
                } catch (Exception $e) {
                    ChannelController::send(sha1($event), "error", json_encode(["desc" => "$base.$procedure", "e" => serialize($e)]));
                }
            }
        }
        self::cache($script, "trigger");
        ChannelController::send(sha1($event), "finish", $script);
    }

    private static function mountScripts($all_scripts, $base)
    {
        foreach ($all_scripts as $schema => $scripts) {
            foreach (self::OPTIONS as $key => $opt) {
                if (isset($scripts[$opt])) {
                    foreach ($scripts[$opt] as $name => $sct) {
                        $path = self::FILE_FOLDER . "/dump_$base" . "_" . date("Y_m_d_H_i") . "/$schema/$key.$opt";
                        Utils::checkFolder($path, "create");
                        $script = "/* *******DUMB SCRIPT FROM $schema*******\nGenerete: " . date("Y-m-d H:i") . "\nPowered By: (Rodrigo Danieli) rodrigo.danieli@dbsnoop.com\n Database Host Reference: " . $base . " \n */\n\n\n";
                        $script .= "\nCREATE DATABASE IF NOT EXISTS $schema;\n";
                        $script .= $sct;
                        file_put_contents("$path/$name.sql", $script);
                    }
                }
            }
        }
    }

    private static function cache($data, $type)
    {

        foreach ($data as $base => $script) {
            foreach ($script as $component => $code) {
                $path = self::CACHE_FOLDER . "/$base/$type";
                Utils::checkFolder($path, "create");
                file_put_contents($path . "/$component", $code);
            }
        }
    }

    private static function has_cache($type)
    {
        $data = [];
        Utils::checkFolder(self::CACHE_FOLDER, "create");
        foreach (scandir(self::CACHE_FOLDER) as $dir) {
            if (in_array($dir, [".", ".."])) continue;
            if (file_exists(self::CACHE_FOLDER . "/$dir/$type")) {
                $data[$dir] = [];
                foreach (scandir(self::CACHE_FOLDER . "/$dir/$type") as $fi) {
                    if (in_array($fi, [".", ".."])) continue;
                    $data[$dir][$fi] = file_get_contents(self::CACHE_FOLDER . "/$dir/$type/$fi");
                }
            }
        }

        return $data;
    }
}

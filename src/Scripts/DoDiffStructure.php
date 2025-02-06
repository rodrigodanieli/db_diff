<?php

namespace App\Scripts;

use Exception;
use React\EventLoop\Loop;
use Sohris\Core\Utils;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;

use Migration\Database\Application\GetBasesMigrationFromProject;
use Migration\Database\Application\GetBasesMigrationFromProjectDto;
use Migration\Database\Application\GetDatabaseFromProject;
use Migration\Database\Application\GetDatabaseFromProjectDto;
use Migration\Database\Application\GetTablesMigrationFromProject;
use Migration\Database\Application\GetTablesMigrationFromProjectDto;
use Migration\Database\Domain\Database;
use Migration\Database\Infrastructure\Json as DatabaseRepository;
use Migration\Event\Application\Compare as EventCompare;
use Migration\Event\Application\CompareDto as EventCompareDto;
use Migration\Event\Application\GetEventsFromBase;
use Migration\Event\Application\GetEventsFromBaseDto;
use Migration\Event\Infrastructure\Repository as EventRepository;
use Migration\Functions\Application\Compare as FunctionCompare;
use Migration\Functions\Application\CompareDto as FunctionCompareDto;
use Migration\Functions\Application\GetFunctionsFromBase;
use Migration\Functions\Application\GetFunctionsFromBaseDto;
use Migration\Functions\Infrastructure\Repository as FunctionRepository;
use Migration\Procedure\Application\Compare as ProcedureCompare;
use Migration\Procedure\Application\CompareDto as ProcedureCompareDto;
use Migration\Procedure\Application\GetProceduresFromBase;
use Migration\Procedure\Application\GetProceduresFromBaseDto;
use Migration\Procedure\Infrastructure\Repository as ProcedureRepository;
use Migration\Table\Application\Compare as TableCompare;
use Migration\Table\Application\CompareDto as TableCompareDto;
use Migration\Table\Infrastructure\Repository as TableRepository;
use Migration\Trigger\Application\Compare as TriggerCompare;
use Migration\Trigger\Application\CompareDto as TriggerCompareDto;
use Migration\Trigger\Application\GetTriggersFromBase;
use Migration\Trigger\Application\GetTriggersFromBaseDto;
use Migration\Trigger\Infrastructure\Repository as TriggerRepository;
use Migration\View\Application\Compare as ViewCompare;
use Migration\View\Application\CompareDto as ViewCompareDto;
use Migration\View\Application\GetViewsFromBase;
use Migration\View\Application\GetViewsFromBaseDto;
use Migration\View\Infrastructure\Repository as ViewRepository;
use parallel\Runtime;
use Sohris\Core\Logger;
use Sohris\Core\Server;
use Sohris\Core\Tools\Worker\ChannelController;
use Sohris\Core\Tools\Worker\Worker;
use Symfony\Component\Console\Helper\ProgressBar;

class DoDiffStructure extends Command
{
    const OPTIONS = ["table", "view", "procedure", "function", "event", "trigger"];

    const CACHE_FOLDER = "./cache";
    const FILE_FOLDER = "./files/database_script";

    private static Logger $logger;

    private static GetDatabaseFromProject $get_database_from_project;
    private static GetBasesMigrationFromProject $get_bases_from_project;

    private static GetTablesMigrationFromProject $get_tables_from_project;
    private static TableCompare $compare_tables;

    private static GetProceduresFromBase $get_procedures_from_base;
    private static ProcedureCompare $compare_procedure;

    private static GetFunctionsFromBase $get_functions_from_base;
    private static FunctionCompare $compare_function;

    private static GetViewsFromBase $get_views_from_base;
    private static ViewCompare $compare_views;

    private static GetEventsFromBase $get_events_from_base;
    private static EventCompare $compare_events;

    private static GetTriggersFromBase $get_triggers_from_base;
    private static TriggerCompare $compare_triggers;

    protected function configure(): void
    {
        $this
            ->setName("automations:do_diff_structure")
            ->setDescription('Execute a event registred')
            ->addArgument('project', InputArgument::REQUIRED, 'Project Id')
            ->addArgument('base', InputArgument::REQUIRED, 'Base usada como referencia no diff')
            ->addArgument('compare', InputArgument::REQUIRED, 'Base a ser aplicada no diff')
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
        
        Server::setOutput($output);
        self::firstRun();
        if (!$output instanceof ConsoleOutputInterface) {
            throw new \LogicException('This command accepts only an instance of "ConsoleOutputInterface".');
        }
        $args = $input->getArguments();
        $opts = $input->getOptions();
        $do = [];
        if ($opts['all']) {
            $do = self::OPTIONS;
        } else {
            foreach (self::OPTIONS as $opt)
                if ($opts[$opt])
                    $do[] = $opt;
        }
        echo 123;
        self::$logger->info("Doing $args[base] <-> $args[compare] (" . implode(",", $do) . ")");
        echo 123;
        $workers = [];
        $base = $args['base'];
        $compare = $args['compare'];

        foreach ($do as $key => $to_do) {
            //$section->writeln("Configuring $to_do");
            $workers[$key] = new Runtime(Server::getRootDir() . "/bootstrap.php");
            ChannelController::on(sha1($key), "finish", function ($values) use ($to_do, $base, $compare) {
                self::$logger->info("Mount Script $to_do");
                self::mountScriptsSction($values, $base, $compare, $to_do);
            });
            switch ($to_do) {
                case "table":
                    $workers[$key]->run(static function ($args, $key) {
                        self::tables($args['project'], $args['base'], $args['compare'], $key);
                    }, [$args, $key]);
                    break;
                case "procedure":
                    $workers[$key]->run(static function ($args, $key) {
                        self::procedures($args['project'], $args['base'], $args['compare'], $key);
                    }, [$args, $key]);
                    break;
                case "function":
                    $workers[$key]->run(static function ($args, $key) {
                        self::functions($args['project'], $args['base'], $args['compare'], $key);
                    }, [$args, $key]);
                    break;
                case "view":
                    $workers[$key]->run(static function ($args, $key) {
                        self::views($args['project'], $args['base'], $args['compare'], $key);
                    }, [$args, $key]);
                    break;
                case "event":
                    $workers[$key]->run(static function ($args, $key) {
                        self::events($args['project'], $args['base'], $args['compare'], $key);
                    }, [$args, $key]);
                    break;
                case "trigger":
                    $workers[$key]->run(static function ($args, $key) {
                        self::triggers($args['project'], $args['base'], $args['compare'], $key);
                    }, [$args, $key]);
                    break;
            }
        }

        Loop::run();
        return Command::SUCCESS;
    }

    public static function firstRun($output = null)
    {
        self::$logger = new Logger("DiffStructure");

        $database_repository = new DatabaseRepository;
        self::$get_database_from_project = new GetDatabaseFromProject($database_repository);
        self::$get_bases_from_project = new GetBasesMigrationFromProject($database_repository);

        //Tables
        $tables_repository = new TableRepository;
        self::$get_tables_from_project = new GetTablesMigrationFromProject($database_repository);
        self::$compare_tables = new TableCompare($tables_repository);

        //Procedures
        $procedure_repository = new ProcedureRepository;
        self::$get_procedures_from_base = new GetProceduresFromBase($procedure_repository);
        self::$compare_procedure = new ProcedureCompare($procedure_repository);

        //Functions
        $function_repository = new FunctionRepository;
        self::$get_functions_from_base = new GetFunctionsFromBase($function_repository);
        self::$compare_function = new FunctionCompare($function_repository);

        //Views
        $view_repository = new ViewRepository;
        self::$get_views_from_base = new GetViewsFromBase($view_repository);
        self::$compare_views = new ViewCompare($view_repository);

        //Events
        $event_repository = new EventRepository;
        self::$get_events_from_base = new GetEventsFromBase($event_repository);
        self::$compare_events = new EventCompare($event_repository);

        //Trigger
        $trigger_repository = new TriggerRepository;
        self::$get_triggers_from_base = new GetTriggersFromBase($trigger_repository);
        self::$compare_triggers = new TriggerCompare($trigger_repository);
    }

    private static function tables($project_id, $base_1, $base_2, $event)
    {
        $cache = self::has_cache("table");
        if (!empty($cache)) {
            ChannelController::send(sha1($event), "finish", $cache);
            return;
        }
        self::firstRun();

        self::$logger->debug("Table - Getting Bases");
        $database_reference_dto = new GetDatabaseFromProjectDto($project_id, $base_1);
        $database_reference = self::$get_database_from_project->execute($database_reference_dto);
        $database_to_apply_dto = new GetDatabaseFromProjectDto($project_id, $base_2);
        $database_to_apply = self::$get_database_from_project->execute($database_to_apply_dto);

        $tables_dto = new GetTablesMigrationFromProjectDto($project_id);
        $script = [];
        $tables = self::$get_tables_from_project->execute($tables_dto);
        $total = count($tables);
        self::$logger->debug("Table - Total Table from base $total");
        foreach ($tables as $table_info) {
            $start = Utils::microtimeFloat();
            $table = $table_info['table'];
            $base = $table_info['base'];
            self::$logger->debug("Table - Processing Table $base.$table");
            try {
                if (!array_key_exists($base, $script))
                    $script[$base] = "";
                $dto = new TableCompareDto($base, $table, $database_reference, $database_to_apply);
                $script[$base] .=  self::$compare_tables->execute($dto);
            } catch (Exception $e) {
                self::$logger->error("ErrorTable - $base.$table");
                self::$logger->exception($e);
            }
            self::$logger->debug("Time " . (round((Utils::microtimeFloat() - $start), 2)) . "sec");
        }
        self::cache($script, "table");
        ChannelController::send(sha1($event), "finish", $script);
    }

    private static function procedures($project_id, $base_1, $base_2, $event)
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
        $database_to_apply_dto = new GetDatabaseFromProjectDto($project_id, $base_2);
        $database_to_apply = self::$get_database_from_project->execute($database_to_apply_dto);
        $bases = self::$get_bases_from_project->execute(new GetBasesMigrationFromProjectDto($project_id));

        $script = [];
        $procedures = [];
        $total = 0;

        foreach ($bases as $base) {
            ChannelController::send(sha1($event), "update_state", "Getting Components From $base");

            $procedure_dto = new GetProceduresFromBaseDto($database_reference, $database_to_apply, $base);
            $procedures[$base] = self::$get_procedures_from_base->execute($procedure_dto);
            $total += count($procedures[$base]);
        }

        ChannelController::send(sha1($event), "set_total", $total);
        foreach ($procedures as $base => $procs) {
            $procedure_script = "";

            ChannelController::send(sha1($event), "update_state", "Processing $base");
            foreach ($procs as $procedure) {
                ChannelController::send(sha1($event), "update", $base . "." . $procedure);
                try {
                    $compare_procedure_dto = new ProcedureCompareDto($base, $procedure, $database_reference, $database_to_apply);
                    $procedure_script .= self::$compare_procedure->execute($compare_procedure_dto);
                } catch (Exception $e) {
                    ChannelController::send(sha1($event), "error", json_encode(["desc" => $base . "." . $procedure, "e" => serialize($e)]));
                }
                ChannelController::send(sha1($event), "update", $base . "." . $procedure);
            }

            if (!empty($procedure_script))
                $script[$base] = $procedure_script;
        }
        self::cache($script, "procedure");
        ChannelController::send(sha1($event), "finish", $script);
    }

    private static function functions($project_id, $base_1, $base_2, $event)
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
        $database_to_apply_dto = new GetDatabaseFromProjectDto($project_id, $base_2);
        $database_to_apply = self::$get_database_from_project->execute($database_to_apply_dto);
        $bases = self::$get_bases_from_project->execute(new GetBasesMigrationFromProjectDto($project_id));

        $script = [];
        $functions = [];
        $total = 0;

        foreach ($bases as $base) {
            ChannelController::send(sha1($event), "update_state", "Getting Components From $base");
            $function_dto = new GetFunctionsFromBaseDto($database_reference, $database_to_apply, $base);
            $functions[$base] = self::$get_functions_from_base->execute($function_dto);
            $total += count($functions[$base]);
        }

        ChannelController::send(sha1($event), "set_total", $total);
        foreach ($functions as $base => $func) {
            $functions_script = "";
            ChannelController::send(sha1($event), "update_state", "Processing $base");

            foreach ($func as $procedure) {
                $start = Utils::microtimeFloat();
                ChannelController::send(sha1($event), "update", $base . "." . $procedure);
                try {
                    $compare_function_dto = new FunctionCompareDto($base, $procedure, $database_reference, $database_to_apply);
                    $functions_script .= self::$compare_function->execute($compare_function_dto);
                } catch (Exception $e) {
                    ChannelController::send(sha1($event), "error", json_encode(["desc" => "$base.$procedure", "e" => serialize($e)]));
                }
            }
            if (!empty($functions_script))
                $script[$base] = $functions_script;
        }
        self::cache($script, "function");
        ChannelController::send(sha1($event), "finish", $script);
    }

    private static function views($project_id, $base_1, $base_2, $event)
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
        $database_to_apply_dto = new GetDatabaseFromProjectDto($project_id, $base_2);
        $database_to_apply = self::$get_database_from_project->execute($database_to_apply_dto);
        $bases = self::$get_bases_from_project->execute(new GetBasesMigrationFromProjectDto($project_id));


        $script = [];
        $views = [];
        $total = 0;

        foreach ($bases as $base) {
            ChannelController::send(sha1($event), "update_state", "Getting Components From $base");
            $view_dto = new GetViewsFromBaseDto($database_reference, $database_to_apply, $base);
            $views[$base] = self::$get_views_from_base->execute($view_dto);
            $total += count($views[$base]);
        }

        ChannelController::send(sha1($event), "set_total", $total);
        foreach ($views as $base => $vw) {
            $views_script = "";
            ChannelController::send(sha1($event), "update_state", "Processing $base");
            foreach ($vw as $procedure) {
                $start = Utils::microtimeFloat();
                ChannelController::send(sha1($event), "update", $base . "." . $procedure);
                try {
                    $compare_view_dto = new ViewCompareDto($base, $procedure, $database_reference, $database_to_apply);
                    $views_script .= self::$compare_views->execute($compare_view_dto);
                } catch (Exception $e) {
                    ChannelController::send(sha1($event), "error", json_encode(["desc" => "$base.$procedure", "e" => serialize($e)]));
                }
            }
            if (!empty($views_script))
                $script[$base] = $views_script;
        }
        self::cache($script, "view");
        ChannelController::send(sha1($event), "finish", $script);
    }

    private static function events($project_id, $base_1, $base_2, $event)
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
        $database_to_apply_dto = new GetDatabaseFromProjectDto($project_id, $base_2);
        $database_to_apply = self::$get_database_from_project->execute($database_to_apply_dto);
        $bases = self::$get_bases_from_project->execute(new GetBasesMigrationFromProjectDto($project_id));
        $script = [];
        $total = 0;
        $events = [];

        foreach ($bases as $base) {
            ChannelController::send(sha1($event), "update_state", "Getting Components From $base");
            $event_dto = new GetEventsFromBaseDto($database_reference, $database_to_apply, $base);
            $events[$base] = self::$get_events_from_base->execute($event_dto);
            $total += count($events[$base]);
        }

        ChannelController::send(sha1($event), "set_total", $total);
        foreach ($events as $base => $ev) {
            $events_script = "";
            ChannelController::send(sha1($event), "update_state", "Processing $base");
            foreach ($ev as $procedure) {
                ChannelController::send(sha1($event), "update", $base . "." . $procedure);
                try {
                    $compare_event_dto = new EventCompareDto($base, $procedure, $database_reference, $database_to_apply);
                    $events_script .= self::$compare_events->execute($compare_event_dto);
                } catch (Exception $e) {
                    ChannelController::send(sha1($event), "error", json_encode(["desc" => "$base.$procedure", "e" => serialize($e)]));
                }
            }
            if (!empty($events_script))
                $script[$base] = $events_script;
        }
        self::cache($script, "event");
        ChannelController::send(sha1($event), "finish", $script);
    }

    private static function triggers($project_id, $base_1, $base_2, $event)
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
        $database_to_apply_dto = new GetDatabaseFromProjectDto($project_id, $base_2);
        $database_to_apply = self::$get_database_from_project->execute($database_to_apply_dto);
        $bases = self::$get_bases_from_project->execute(new GetBasesMigrationFromProjectDto($project_id));

        $script = [];
        $triggers = [];
        $total = 0;

        foreach ($bases as $base) {
            ChannelController::send(sha1($event), "update_state", "Getting Components From $base");
            $trigger_dto = new GetTriggersFromBaseDto($database_reference, $database_to_apply, $base);
            $triggers[$base] = self::$get_triggers_from_base->execute($trigger_dto);
            $total += count($triggers);
        }
        ChannelController::send(sha1($event), "set_total", $total);
        foreach ($triggers as $base => $tr) {
            $trigger_dto = new GetTriggersFromBaseDto($database_reference, $database_to_apply, $base);
            $triggers = self::$get_triggers_from_base->execute($trigger_dto);
            $triggers_script = "";
            ChannelController::send(sha1($event), "update_state", "Processing $base");
            foreach ($tr as $procedure) {
                ChannelController::send(sha1($event), "update", $base . "." . $procedure);
                try {
                    $compare_trigger_dto = new TriggerCompareDto($base, $procedure, $database_reference, $database_to_apply);
                    $triggers_script .= self::$compare_triggers->execute($compare_trigger_dto);
                } catch (Exception $e) {
                    ChannelController::send(sha1($event), "error", json_encode(["desc" => "$base.$procedure", "e" => serialize($e)]));
                }
            }
            if (!empty($triggers_script))
                $script[$base] = $triggers_script;
        }
        self::cache($script, "trigger");
        ChannelController::send(sha1($event), "finish", $script);
    }

    private static function mountScriptsSction($scripts, $base, $compare, $name)
    {
        $uname = strtoupper($name);
        foreach ($scripts as $schema => $script) {
            if (empty($script)) continue;
            $path = self::FILE_FOLDER . "/deploy-$base-$compare-" . date("Y_m_d") . "/structure/$schema";
            Utils::checkFolder($path, "create");
            $fscript = "/* *******STRUCTURE SCRIPT FROM $schema $uname *******\nGenerete: " . date("Y-m-d H:i:s") . "\nPowered By: (Rodrigo Danieli) rodrigo.danieli@dbsnoop.com\n Database Host Reference: " . $base . " \n Database Host to Apply: " . $compare . "*/\n\n\n";
            $fscript .= "\nCREATE DATABASE IF NOT EXISTS $schema;\n";
            $fscript .= $script;
            file_put_contents("$path/$name.sql", $fscript);
        }
    }

    private static function cache($data, $type)
    {
        foreach ($data as $base => $script) {
            $path = "./cache/$base";
            Utils::checkFolder($path, "create");
            file_put_contents($path . "/$type", $script);
        }
    }

    private static function has_cache($type)
    {
        $data = [];
        if (is_dir("./cache"))
            foreach (scandir("./cache") as $dir) {
                if (in_array($dir, [".", ".."])) continue;
                if (file_exists("./cache/$dir/$type")) {
                    $data[$dir] = file_get_contents("./cache/$dir/$type");
                }
            }

        return $data;
    }
}

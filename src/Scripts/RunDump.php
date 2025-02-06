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
use Throwable;

class RunDump extends Command
{
    const OPTIONS = ["table", "view", "function", "procedure", "event", "trigger"];
    const FILE_FOLDER = "./files/database_script";
    private static GetDatabaseFromProject $get_database_from_project;
    private static GetBasesMigrationFromProject $get_bases_from_project;
    private static $error_exit = true;

    protected function configure(): void
    {
        $this
            ->setName("automations:exec_dump")
            ->setDescription('Dump database Mysql from project')
            ->addArgument('project', InputArgument::REQUIRED, 'Project Id')
            ->addArgument('destination', InputArgument::REQUIRED, 'Destino do Dump usada como referencia')
            ->addArgument('dump_dir', InputArgument::REQUIRED, 'Arquivo do dump realizado pelo DumpMysql')
            ->addOption('ignore-error', 'i', NULL, 'Compare Tables')
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
        self::firstRun();
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
        $args = $input->getArguments();

        if (isset($opts['ignore-error']) && $opts['ignore-error'])
            self::$error_exit = false;

        $project_id = $args['project'];
        $base = $args['destination'];
        $folder = $args['dump_dir'];


        $dir = self::FILE_FOLDER . "/$folder";
        if (!is_dir($dir)) {
            self::log($section, "error", "Folder $folder not found in " . realpath(self::FILE_FOLDER));
            exit;
        }
        try {
            $database_reference_dto = new GetDatabaseFromProjectDto($project_id, $base);
            $database_reference = self::$get_database_from_project->execute($database_reference_dto);
            $connection = $database_reference->getConnection();
        } catch (Exception $e) {
            self::log($section, "error", $e->getMessage());
            exit;
        }

        $section->writeln(["Dump Database $base", "Host: " . $database_reference->host()]);
        $files = self::getFiles($dir);
        $section->writeln(["Objects - " . count($files)]);

        $progress_section = $output->section();
        $progress = new ProgressBar($progress_section, count($files));

        $progress->setFormat('%percent:1s%% [%bar%] %current%/%max%  %elapsed:3s%/%estimated:-3s% %message%');
        $error_section = $output->section();

        foreach ($files as $file) {
            $f = explode("/", $file);
            $size = sizeof($f);
            $name = $f[$size - 3] . "." . $f[$size - 1];
            $progress->setMessage($name);
            try {
                $content = file_get_contents($file);
                $execs = explode("DELIMITER &&", $content);

                foreach($execs as $exec)
                {
                    $script = explode("&&\nDELIMITER ;", $exec)[0];
                    if($connection->exec($script) === false) 
                    {
                        self::log($error_section, "error", $connection->errorInfo()[2]);
                    }
                }
            } catch (Exception $e) {
                self::log($error_section, "error", $e->getMessage());
            } catch (Throwable $e) {
                self::log($error_section, "error", $e->getMessage());
            }
            $progress->advance();
        }
        return Command::SUCCESS;
    }

    private static function getFiles($path)
    {
        $dirs = [];

        if (!is_dir($path)) return [$path];

        foreach (scandir($path) as $file) {
            if (in_array($file, [".", ".."])) continue;
            $dirs = array_merge($dirs, self::getFiles("$path/$file"));
        }
        return $dirs;
    }

    public static function firstRun()
    {
        $database_repository = new DatabaseRepository;
        self::$get_database_from_project = new GetDatabaseFromProject($database_repository);
        self::$get_bases_from_project = new GetBasesMigrationFromProject($database_repository);
    }

    public static function log($section, $level, $msg)
    {
        $section->writeln([
            "[" . date("Y-m-d H:i:s") . "][" . strtoupper($level) . "] $msg"
        ]);

        if (self::$error_exit && $level == "error")
            exit;
    }
}

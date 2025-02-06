<?php

namespace Migration\Table\Application;

use Exception;
use Migration\Database\Application\GetDatabaseFromProject;
use Migration\Database\Application\GetDatabaseFromProjectDto;
use Migration\Database\Application\GetTablesMigrationFromProject;
use Migration\Database\Application\GetTablesMigrationFromProjectDto;
use Migration\Database\Infrastructure\Json as DatabaseRepository;
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

    public function __construct()
    {
        $this->logger = new Logger("DiffTable");
        $base = CoreUtils::getConfigFiles('system')['files_dir'];
        $this->scripts_dir = "$base/diff/" . date("Y-m-d");
    }

    public function execute(DiffDto $dto)
    {
        $diff_name = $dto->base_1 . "-" . $dto->base_2;
        $diff_script = [];
        $rollback_script = [];

        $this->scripts_dir .= "/$diff_name";
        $this->logger->info("Doing Project " . $dto->project . " Base " . $dto->base_1 . " To " . $dto->base_2);
        $this->logger->info("Workers " . $dto->workers);

        $this->workers = $dto->workers;

        $project_id = $dto->project;
        $base_1 = $dto->base_1;
        $base_2 = $dto->base_2;

        $get_tables_from_project = new GetTablesMigrationFromProject(new DatabaseRepository);
        $tables_dto = new GetTablesMigrationFromProjectDto($project_id);
        $tables = $get_tables_from_project->execute($tables_dto);
        $tmp_t = [];
        foreach($tables as $table)
            if($table['base'] == 'alemonitor' && $table['table'] == 'handshake_control')
                $tmp_t[] = $table;
        // $tables = $tmp_t;
        //Diff Bases
        $start = CoreUtils::microtimeFloat();
        if ($this->workers > 1) {
            $chunk = array_chunk($tables, (int)ceil(count($tables) / $this->workers));
            $runtimes = [];
            $futures = [];
            $this->logger->debug("Generate Workers");
            for ($i = 0; $i < $this->workers; $i++) {
                if (!isset($chunk[$i])) continue;
                $runtimes[$i] = new Runtime(Server::getRootDir() . "/bootstrap.php");
                $futures[$i] = $runtimes[$i]->run(static function ($project_id, $base_1, $base_2, $tables, $worker, $root_dir, $verbose) {
                    $server = Server::getServer();
                    $server->setRootDir($root_dir);
                    $server->loadServer();
                    $server->setOutput(new ConsoleOutput($verbose));
                    return self::diff($tables, $project_id, $base_1, $base_2, $worker);
                }, [$project_id, $base_1, $base_2, $chunk[$i], $i, Server::getRootDir(), Server::getOutput()->getVerbosity()]);
            }

            $this->logger->debug("Validate Workers");
            do {
                foreach ($futures as $key => $future) {
                    if ($future->done() || $future->cancelled()) {
                        $value = $future->value();
                        if (!empty($value)) {
                            $dev = json_decode($value, true);
                            foreach ($dev as $base => $values) {
                                if (!isset($diff_script[$base]))
                                    $diff_script[$base] = "";
                                $diff_script[$base] .= $values;
                            }
                        }
                        unset($runtimes[$key]);
                        unset($futures[$key]);
                    }
                }
            } while (!empty($runtimes));
            unset($runtimes);
            unset($futures);
        } else {
            $diff_script = $this->diff($tables, $project_id, $base_1, $base_2);
        }
        $this->logger->debug("Process Diff in " . (round((CoreUtils::microtimeFloat() - $start), 2)) . "sec");


        //Rollback Bases
        $start = CoreUtils::microtimeFloat();
        if ($this->workers > 1) {
            $chunk = array_chunk($tables, (int)ceil(count($tables) / $this->workers));
            $runtimes = [];
            $futures = [];
            for ($i = 0; $i < $this->workers; $i++) {
                if (!isset($chunk[$i])) continue;
                $runtimes[$i] = new Runtime(Server::getRootDir() . "/bootstrap.php");
                $futures[$i] = $runtimes[$i]->run(static function ($project_id, $base_1, $base_2, $tables, $worker, $root_dir, $verbose) {
                    $server = Server::getServer();
                    $server->setRootDir($root_dir);
                    $server->loadServer();
                    $server->setOutput(new ConsoleOutput($verbose));
                    return self::diff($tables, $project_id, $base_1, $base_2, $worker);
                }, [$project_id, $base_2, $base_1, $chunk[$i], $i, Server::getRootDir(), Server::getOutput()->getVerbosity()]);
            }

            do {
                foreach ($futures as $key => $future)
                    if ($future->done() || $future->cancelled()) {

                        $value = $future->value();
                        if (!empty($value)) {
                            $dev = json_decode($value, true);
                            foreach ($dev as $base => $values) {
                                if (!isset($rollback_script[$base]))
                                    $rollback_script[$base] = "";
                                $rollback_script[$base] .= $values;
                            }
                        }
                        unset($runtimes[$key]);
                        unset($futures[$key]);
                    }
            } while (!empty($runtimes));
        } else {
            $rollback_script = $this->diff($tables, $project_id, $base_1, $base_2);
        }
        $this->logger->debug("Process Rollback in " . (round((CoreUtils::microtimeFloat() - $start), 2)) . "sec");



        $this->logger->info("Generate Diff Script");
        foreach ($diff_script as $base => $scr) {
            if (empty($scr)) continue;
            $path = $this->scripts_dir . "/structure/$base";
            CoreUtils::checkFolder($path, "create");
            $fscript = "/* *******STRUCTURE SCRIPT FROM $base *******\nGenerete: " . date("Y-m-d H:i:s") . "\nPowered By: (Rodrigo Danieli) rodrigo.danieli@dbsnoop.com\n Database Host Reference: " . $base_1 . " \n Database Host to Apply: " . $base_2 . "*/\n\n\n";
            $fscript .= "\nCREATE DATABASE IF NOT EXISTS $base;\n";
            $fscript .= $scr;
            file_put_contents("$path/table.sql", $fscript);
        }

        $this->logger->info("Generate Rollback Script");
        foreach ($rollback_script as $base => $scr) {
            if (empty($scr)) continue;
            $path = $this->scripts_dir . "/rollback/$base";
            CoreUtils::checkFolder($path, "create");
            $fscript = "/* *******STRUCTURE SCRIPT FROM $base *******\nGenerete: " . date("Y-m-d H:i:s") . "\nPowered By: (Rodrigo Danieli) rodrigo.danieli@dbsnoop.com\n Database Host Reference: " . $base_1 . " \n Database Host to Apply: " . $base_2 . "*/\n\n\n";
            $fscript .= "\nCREATE DATABASE IF NOT EXISTS $base;\n";
            $fscript .= $scr;
            file_put_contents("$path/table.sql", $fscript);
        }
    }


    private static function diff($tables, $project_id, $base_1, $base_2, $worker = "")
    {
        $script = [];

        $logger = new Logger("DiifTable$worker");
        $get_database_from_project = new GetDatabaseFromProject(new DatabaseRepository);
        $database_reference_dto = new GetDatabaseFromProjectDto($project_id, $base_1);
        $database_reference = $get_database_from_project->execute($database_reference_dto);
        $database_to_apply_dto = new GetDatabaseFromProjectDto($project_id, $base_2);
        $database_to_apply = $get_database_from_project->execute($database_to_apply_dto);
        $compare_tables = new Compare(new InfrastructureRepository);

        $total = count($tables);
        $count = 0;
        $logger->info("Generate Diff");
        foreach ($tables as $table_info) {
            $count++;
            $table = $table_info['table'];
            $base = $table_info['base'];
            $logger->debug("Table - Processing Table $base.$table ($count/$total)");
            try {
                if (!array_key_exists($base, $script))
                    $script[$base] = "";
                $dto = new CompareDto($base, $table, $database_reference, $database_to_apply);
                $script[$base] .=  $compare_tables->execute($dto);
            } catch (Exception $e) {
                $logger->error("ErrorTable - $base.$table");
                $logger->exception($e);
            }
        }

        return json_encode($script);
    }
}

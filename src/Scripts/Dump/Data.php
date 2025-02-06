<?php

namespace App\Scripts\Dump;

use Exception;
use Migration\Data\Application\GetDataFromTable;
use Migration\Data\Application\GetDataFromTableDto;
use Migration\Data\Infrastructure\Repository as InfrastructureRepository;
use Migration\Database\Application\GetDatabaseFromProject;
use Migration\Database\Application\GetDatabaseFromProjectDto;
use Migration\Database\Application\GetTablesMigrationFromProject;
use Migration\Database\Application\GetTablesMigrationFromProjectDto;
use Migration\Database\Domain\Database;
use Migration\Database\Infrastructure\Json as Repository;
use Migration\Event\Application\Diff;
use Migration\Event\Application\DiffDto;
use Shared\Utils;
use Sohris\Core\Loader;
use Sohris\Core\Logger;
use Sohris\Core\Server;
use Sohris\Core\Utils as CoreUtils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Data extends Command
{


    protected function configure(): void
    {
        $this
            ->setName("dump:data")
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
        $logger = new Logger("DumpData");
        $script_dir = CoreUtils::getConfigFiles('system')['files_dir'];

        $base1 = $input->getArgument("base");
        $project = $input->getArgument("project");

        $logger->info("Dump Data $project $base1");

        $database_repository = new Repository;
        $get_database_from_project = new GetDatabaseFromProject($database_repository);
        $get_tables_from_project = new GetTablesMigrationFromProject($database_repository);

        $repo = new InfrastructureRepository;
        $get_data_from_table = new GetDataFromTable($repo);

        $database_reference_dto = new GetDatabaseFromProjectDto($project, $base1);
        $database_reference = $get_database_from_project->execute($database_reference_dto);

        $tables_dto = new GetTablesMigrationFromProjectDto($project);
        $script = [];

        foreach ($get_tables_from_project->execute($tables_dto) as $table_info) {
            $table = $table_info['table'];
            $base = $table_info['base'];
            try {
                if ($table_info['create_data'] != "1") continue;
                $logger->debug("Dumping $base.$table");
                if (!isset($script[$base]))
                    $script[$base] = "";

                $dto = new GetDataFromTableDto($database_reference, $base, $table);
                $data =  $get_data_from_table->execute($dto);
                $script[$base] .= $data->getScript() . "\n";
            } catch (Exception $e) {
                $logger->exception($e);
            }
        }

        //Export script
        foreach ($script as $schema => $scripts) {

            $script = "/* *******DATA SCRIPT FROM $schema*******\nGenerete: " . date("Y-m-d H:i:s") . "\nPowered By: (Rodrigo Danieli) rodrigo.danieli@dbsnoop.com\n Database Host Reference: " . $database_reference->host() . "*/\n\n\n";
            $script .= $scripts;
            $path = "$script_dir/dump/data/" . date("Y_m_d") . '/' . $base . '/';
            CoreUtils::checkFolder($path, "create");
            file_put_contents("$path/$schema.sql", $script);
        }


        return Command::SUCCESS;
    }
}

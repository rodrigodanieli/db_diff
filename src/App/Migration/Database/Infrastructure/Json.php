<?php


namespace Migration\Database\Infrastructure;

use Migration\Database\Domain\Database;
use Migration\Database\Domain\Interfaces\Repository as InterfacesRepository;
use Sohris\Core\Utils;

class Json implements InterfacesRepository
{
    private static $config = [];

    public function __construct()
    {
        self::$config = Utils::getConfigFiles('databases_config');
    }


    public function getDatabase(int $project, string $name): Database
    {
        if (!isset(self::$config['databases'][$name])) return [];

        $info = self::$config['databases'][$name]['connection'];
        return new Database($info['user'],$info['pass'],$info['host'],$info['port'],$project);
    }

    public function getDatabases(int $project): array
    {
        $info = [];

        foreach (self::$config['databases'] as $name => $c) {
            $info[] = [
                "name" => $name,
                "active" => 1,
                "project_id" => $project,
                "db_type" => $c['db_type'],
                "connection" => $c['connection']
            ];
        }
        return $info;
    }

    public function getTablesMigration(int $project): array
    {
        $bases = self::$config['bases_tables'];
        $result = [];

        foreach ($bases as $name => $tables) {
            foreach ($tables as $table) {
                $table['base'] = $name;
                $result[] = $table;
            }
        }

        return $result;
    }

    public function getBasesMigration(int $project): array
    {
        $bases = self::$config['bases_tables'];
        $result = array_keys($bases);
        return $result;
    }
}

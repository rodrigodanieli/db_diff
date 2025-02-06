<?php


namespace Migration\Database\Infrastructure;

use Exception;
use Migration\Database\Domain\Database;
use Migration\Database\Domain\Interfaces\Repository as InterfacesRepository;
use Sohris\Core\Utils;

class  Repository implements InterfacesRepository
{
    private \PDO $dao;

    public function __construct()
    {
        $this->connect();
    }

    private function connect()
    {

        $config = Utils::getConfigFiles('mysql');
        $dns = "mysql:host=$config[host];port=$config[port];dbname=$config[base]";
        $this->dao = new \PDO($dns, $config['user'], $config['pass']);
    }

    private function execQuery($query, $params = [])
    {
        try {
            $stm = $this->dao->prepare($query);
            $stm->execute($params);
            return $stm->fetchAll(\PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            if ($e->getCode() == 2006) {
                $this->connect();

                $stm = $this->dao->prepare($query);
                $stm->execute($params);
                return $stm->fetchAll(\PDO::FETCH_ASSOC);
            }
        }
    }

    public function getDatabase(int $project, string $name): Database
    {

        $query = "SELECT mb.* 
                    FROM automations.migration_dbs mb 
                    JOIN automations.migration_projects p ON mb.project_id = p.id
                    WHERE mb.active = 1 and p.active = 1 and mb.name = :name and p.id = :id";
        $result = $this->execQuery($query, ['name' => $name, "id" => $project]);
        return Database::create($result[0]);
    }

    public function getDatabases(int $project): array
    {

        $query = "SELECT mb.* 
                    FROM automations.migration_dbs mb 
                    JOIN automations.migration_projects p ON mb.project_id = p.id
                    WHERE mb.active = 1 and p.active = 1 and p.id = :id";
        $result = $this->execQuery($query, ["id" => $project]);

        return $result;
    }

    public function getTablesMigration(int $project): array
    {
        $query = "SELECT mb.* 
                    FROM automations.migration_tables mb 
                    JOIN automations.migration_projects p ON mb.project_id = p.id
                    WHERE mb.active = 1 and p.active = 1 and p.id = :id";
        $result = $this->execQuery($query, ["id" => $project]);

        return $result;
    }

    public function getBasesMigration(int $project): array
    {
        $query = "SELECT mb.* 
                    FROM automations.migration_bases mb 
                    JOIN automations.migration_projects p ON mb.project_id = p.id
                    WHERE mb.active = 1 and p.active = 1 and p.id = :id";
        $result = $this->execQuery($query, ["id" => $project]);

        return array_map(fn ($el) => $el['name'], $result);
    }
}

<?php

namespace Migration\Trigger\Infrastructure;

use Exception;
use Migration\Database\Domain\Database;
use Migration\Trigger\Domain\Exceptions\TriggerNotFound;
use Migration\Trigger\Domain\Interfaces\Repository as InterfacesRepository;
use Migration\Trigger\Domain\Trigger;

class Repository implements InterfacesRepository
{
    public function getTrigger(Database $database, string $base, string $name): Trigger
    {
        $conn = $database->getConnection();
        try {
            $stm = $conn->query("SHOW CREATE TRIGGER $base.$name;");

            if (!$stm) throw new TriggerNotFound($base, $name);
            $result = $stm->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($result)) throw new TriggerNotFound($base, $name);
            $view = new Trigger($name, $base);
            $view->addCreation($result[0]['SQL Original Statement']);
        } catch (Exception $e) {
            throw new TriggerNotFound($base, $name);
        }
        return $view;
    }

    public function getTriggersFromBase(Database $database, string $base): array
    {
        $conn = $database->getConnection();
        try {
            $stm = $conn->query("SHOW TRIGGERS FROM `$base`");
            if (!$stm)
                throw new Exception("Triggers not found $base");
        } catch (Exception $e) {
            throw new Exception("Triggers not found $base");
        }
        return $stm->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTriggersFromBases(Database $database_1, Database $database_2, string $base): array
    {
        $conn = $database_1->getConnection();
        try {
            $stm = $conn->query("SHOW TRIGGERS FROM `$base`");
            $views_1 = [];
            if ($stm)
                $views_1 = $stm->fetchAll(\PDO::FETCH_ASSOC);
            $conn = $database_2->getConnection();

            $stm = $conn->query("SHOW TRIGGERS FROM `$base`");
            $views_2 = [];
            if ($stm)
                $views_2 = $stm->fetchAll(\PDO::FETCH_ASSOC);

            return array_unique(array_merge(array_column($views_1, "Trigger"), array_column($views_2, "Trigger")));
        } catch (Exception $e) {
            throw new Exception("Triggers not found $base");
        }
    }
}

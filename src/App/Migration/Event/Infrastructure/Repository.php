<?php

namespace Migration\Event\Infrastructure;

use Exception;
use Migration\Database\Domain\Database;
use Migration\Event\Domain\Exceptions\EventNotFound;
use Migration\Event\Domain\Interfaces\Repository as InterfacesRepository;
use Migration\Event\Domain\Event;

class Repository implements InterfacesRepository
{
    public function getEvent(Database $database, string $base, string $name): Event
    {
        $conn = $database->getConnection();
        try {
            $stm = $conn->query("SHOW CREATE EVENT $base.$name;");
            if (!$stm) throw new EventNotFound($base, $name);
            $result = $stm->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($result)) throw new EventNotFound($base, $name);
            $view = new Event($name, $base);
            $view->addCreation($result[0]['Create Event']);
            return $view;
        } catch (Exception $e) {
            throw new EventNotFound($base, $name);
        }
    }

    public function getEventsFromBase(Database $database, string $base): array
    {
        $conn = $database->getConnection();
        $stm = $conn->query("SHOW EVENTS FROM `$base`");

        if (!$stm)
            throw new Exception("Events not found $base");

        return $stm->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getEventsFromBases(Database $database_1, Database $database_2, string $base): array
    {
        $conn = $database_1->getConnection();
        $stm = $conn->query("SHOW EVENTS FROM `$base`");
        $views_1 = [];
        if ($stm)
            $views_1 = $stm->fetchAll(\PDO::FETCH_ASSOC);
        $conn = $database_2->getConnection();
        $stm = $conn->query("SHOW EVENTS FROM `$base`");
        $views_2 = [];
        if ($stm)
            $views_2 = $stm->fetchAll(\PDO::FETCH_ASSOC);

        return array_unique(array_merge(array_column($views_1, "Name"), array_column($views_2, "Name")));
    }
}

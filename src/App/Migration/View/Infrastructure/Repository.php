<?php

namespace Migration\View\Infrastructure;

use Exception;
use Migration\Database\Domain\Database;
use Migration\View\Domain\Exceptions\ViewNotFound;
use Migration\View\Domain\Interfaces\Repository as InterfacesRepository;
use Migration\View\Domain\View;

class Repository implements InterfacesRepository
{
    public function getView(Database $database, string $base, string $name): View
    {
        $conn = $database->getConnection();
        try {
            $stm = $conn->query("SHOW CREATE VIEW $base.$name;");
            if (!$stm) throw new ViewNotFound($base, $name);
            $result = $stm->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($result)) throw new ViewNotFound($base, $name);
            $view = new View($name, $base);
            $view->addCreation($result[0]['Create View']);
            return $view;
        } catch (Exception $e) {
            throw new ViewNotFound($base, $name);
        }
    }

    public function getViewsFromBase(Database $database, string $base): array
    {
        $conn = $database->getConnection();
        $stm = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES t where TABLE_SCHEMA = '$base' and TABLE_TYPE = 'VIEW';");

        if (!$stm)
            throw new Exception("Views not found $base");
        return $stm->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getViewsFromBases(Database $database_1, Database $database_2, string $base): array
    {
        $conn = $database_1->getConnection();
        $stm = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES t where TABLE_SCHEMA = '$base' and TABLE_TYPE = 'VIEW';");
        $views_1 = [];
        if ($stm)
            $views_1 = $stm->fetchAll(\PDO::FETCH_ASSOC);
        $conn = $database_2->getConnection();
        $stm = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES t where TABLE_SCHEMA = '$base' and TABLE_TYPE = 'VIEW';");
        $views_2 = [];

        if ($stm)
            $views_2 = $stm->fetchAll(\PDO::FETCH_ASSOC);

        return array_unique(array_merge(array_column($views_1, "TABLE_NAME"), array_column($views_2, "TABLE_NAME")));
    }
}

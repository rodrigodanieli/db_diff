<?php

namespace Migration\Functions\Infrastructure;

use Exception;
use Migration\Database\Domain\Database;
use Migration\Functions\Domain\Exceptions\FunctionsNotFound;
use Migration\Functions\Domain\Interfaces\Repository as InterfacesRepository;
use Migration\Functions\Domain\Functions;

class Repository implements InterfacesRepository
{
    public function getFunctions(Database $database, string $base, string $name): Functions
    {
        $conn = $database->getConnection();
        try{
        $stm = $conn->query("SHOW CREATE FUNCTION $base.$name;");
        if (!$stm) throw new FunctionsNotFound($base, $name);
        $result = $stm->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($result)) throw new FunctionsNotFound($base, $name);
        $view = new Functions($name, $base);
        $view->addCreation($result[0]['Create Function']);
        return $view;
        }catch(Exception $e)
        {
            throw new FunctionsNotFound($base, $name);
        }
    }

    public function getFunctionsFromBase(Database $database, string $base): array
    {
        $conn = $database->getConnection();
        $stm = $conn->query("select ROUTINE_NAME from information_schema.ROUTINES where ROUTINE_SCHEMA = '$base' AND ROUTINE_TYPE = 'FUNCTION'");

        if (!$stm)
            throw new Exception("Functions not found $base");
        return $stm->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getFunctionsFromBases(Database $database_1, Database $database_2, string $base): array
    {
        $conn = $database_1->getConnection();
        $stm = $conn->query("select ROUTINE_NAME from information_schema.ROUTINES where ROUTINE_SCHEMA = '$base' AND ROUTINE_TYPE = 'FUNCTION'");

        $views_1 = [];
        if ($stm)
            $views_1 = $stm->fetchAll(\PDO::FETCH_ASSOC);
        $conn = $database_2->getConnection();
        $stm = $conn->query("select ROUTINE_NAME from information_schema.ROUTINES where ROUTINE_SCHEMA = '$base' AND ROUTINE_TYPE = 'FUNCTION'");

        $views_2 = [];
        if ($stm)
            $views_2 = $stm->fetchAll(\PDO::FETCH_ASSOC);

        return array_unique(array_merge(array_column($views_1, "ROUTINE_NAME"), array_column($views_2, "ROUTINE_NAME")));
    }
}

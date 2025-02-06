<?php

namespace Migration\Procedure\Infrastructure;

use Exception;
use Migration\Database\Domain\Database;
use Migration\Procedure\Domain\Exceptions\ProcedureNotFound;
use Migration\Procedure\Domain\Interfaces\Repository as InterfacesRepository;
use Migration\Procedure\Domain\Procedure;

class Repository implements InterfacesRepository
{
    public function getProcedure(Database $database, string $base, string $name): Procedure
    {

        $conn = $database->getConnection();

        //echo "Getting Procedure $base.$name" . PHP_EOL;
        try {
            $stm = $conn->query("SHOW CREATE PROCEDURE $base.$name;");

            if (!$stm) throw new ProcedureNotFound($base, $name);

            $result = $stm->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($result)) throw new ProcedureNotFound($base, $name);

            $view = new Procedure($name, $base);

            $view->addCreation($result[0]['Create Procedure']);
        } catch (Exception $e) {
            throw new ProcedureNotFound($base, $name);
        }
        return $view;
    }

    public function getProceduresFromBase(Database $database, string $base): array
    {
        $conn = $database->getConnection();
        $stm = $conn->query("select * from information_schema.ROUTINES where ROUTINE_SCHEMA = '$base' AND ROUTINE_TYPE = 'PROCEDURE'");

        if (!$stm)
            throw new Exception("Procedure not found $base");
        return $stm->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getProceduresFromBases(Database $database_1, Database $database_2, string $base): array
    {
        $conn = $database_1->getConnection();
        $stm = $conn->query("select * from information_schema.ROUTINES where ROUTINE_SCHEMA = '$base' AND ROUTINE_TYPE = 'PROCEDURE'");

        $views_1 = [];
        if ($stm)
            $views_1 = $stm->fetchAll(\PDO::FETCH_ASSOC);
        $conn = $database_2->getConnection();
        $stm = $conn->query("select * from information_schema.ROUTINES where ROUTINE_SCHEMA = '$base' AND ROUTINE_TYPE = 'PROCEDURE'");

        $views_2 = [];
        if ($stm)
            $views_2 = $stm->fetchAll(\PDO::FETCH_ASSOC);

        return array_unique(array_merge(array_column($views_1, "ROUTINE_NAME"), array_column($views_2, "ROUTINE_NAME")));
    }
}

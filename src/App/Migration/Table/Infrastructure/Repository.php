<?php

namespace Migration\Table\Infrastructure;

use Exception;
use Migration\Database\Domain\Database;
use Migration\Table\Domain\Exceptions\TableNotFound;
use Migration\Table\Domain\Interfaces\Repository as InterfacesRepository;
use Migration\Table\Domain\Table;
use Migration\View\Domain\View;

class Repository implements InterfacesRepository
{

    public function getTable(Database $database, string $base, string $table_name): Table
    {
        $conn = $database->getConnection();

        $stm = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES t where TABLE_NAME = '$table_name' AND TABLE_SCHEMA = '$base' and TABLE_TYPE = 'BASE TABLE';");

        if (!$stm) throw new TableNotFound($base, $table_name);
        $a = $stm->fetchAll();
        if (empty($a)) throw new TableNotFound($base, $table_name);

        $stm = $conn->query("SHOW CREATE TABLE $base.$table_name");
        if (!$stm) throw new TableNotFound($base, $table_name);

        $result = $stm->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($result) || empty($result[0]) || !isset($result[0]['Create Table'])) throw new TableNotFound($base, $table_name);
        $table = new Table($table_name, $base);
        $table->setStructure($result[0]['Create Table']);
        // $stm = $conn->query("Show keys from $base.$table_name;");
        // $result2 = [];
        // if ($stm)
        //     $result2 = $stm->fetchAll(\PDO::FETCH_ASSOC);
        // if (!empty($result2)) {
        //     $table->addKeys($result2);
        // }

        // $stm = $conn->query("SELECT * FROM INFORMATION_SCHEMA.PARTITIONS WHERE PARTITION_NAME IS NOT NULL AND TABLE_NAME = '$table_name' AND TABLE_SCHEMA = '$base';");
        // $result3 = [];
        // if ($stm)
        //     $result3 = $stm->fetchAll(\PDO::FETCH_ASSOC);
        // if (!empty($result3)) {
        //     $table->addPartitions($result3);
        // }
        return $table;
    }

    public function getCreationTable(Database $database, string $base, string $table_name): string
    {
        $conn = $database->getConnection();

        $stm = $conn->query("SHOW CREATE TABLE $base.$table_name");

        if (!$stm) throw new TableNotFound($base, $table_name);
        $a = $stm->fetchAll();
        if (empty($a) || !isset($a[0]['Create Table'])) throw new TableNotFound($base, $table_name);
        return $a[0]['Create Table'];
    }

    public function getAllTablesFromBase(Database $database, string $base): array
    {
        $conn = $database->getConnection();
        $stm = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES t where TABLE_SCHEMA = '$base' and TABLE_TYPE = 'BASE TABLE';");

        if (!$stm)
            throw new Exception("Tables not found $base");
        return $stm->fetchAll(\PDO::FETCH_ASSOC);
    }
}

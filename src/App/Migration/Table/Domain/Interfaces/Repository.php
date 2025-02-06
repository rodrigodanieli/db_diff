<?php


namespace Migration\Table\Domain\Interfaces;

use Migration\Database\Domain\Database;
use Migration\Table\Domain\Table;

interface Repository
{

    public function getTable(Database $database, string $base, string $table_name): Table;
    public function getCreationTable(Database $database, string $base, string $table_name): string;
    public function getAllTablesFromBase(Database $database, string $base):array;
}
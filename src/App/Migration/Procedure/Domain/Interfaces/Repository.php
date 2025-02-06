<?php
namespace Migration\Procedure\Domain\Interfaces;

use Migration\Database\Domain\Database;
use Migration\Procedure\Domain\Procedure;

interface Repository
{
    public function getProcedure(Database $database,  string $base,string $name): Procedure;
    public function getProceduresFromBase(Database $database, string $base) : array;
    public function getProceduresFromBases(Database $database_1, Database $database_2, string $base): array;
}
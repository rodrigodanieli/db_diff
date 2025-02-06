<?php
namespace Migration\Functions\Domain\Interfaces;

use Migration\Database\Domain\Database;
use Migration\Functions\Domain\Functions;

interface Repository
{
    public function getFunctions(Database $database,  string $base,string $name): Functions;
    public function getFunctionsFromBase(Database $database, string $base) : array;
    public function getFunctionsFromBases(Database $database_1, Database $database_2, string $base): array;
}
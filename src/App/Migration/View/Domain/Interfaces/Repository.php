<?php
namespace Migration\View\Domain\Interfaces;

use Migration\Database\Domain\Database;
use Migration\View\Domain\View;

interface Repository
{
    public function getView(Database $database,  string $base,string $name): View;
    public function getViewsFromBase(Database $database, string $base) : array;
    public function getViewsFromBases(Database $database_1, Database $database_2, string $base): array;
}
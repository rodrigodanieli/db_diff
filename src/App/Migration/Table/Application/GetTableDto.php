<?php

namespace Migration\Table\Application;

use Migration\Database\Domain\Database;

class GetTableDto
{
    public string $base;
    public string $table;
    public Database $database;

    public function __construct(string $base, string $table, Database $database)
    {   
        $this->base = $base;
        $this->table = $table;
        $this->database = $database;
    }

}
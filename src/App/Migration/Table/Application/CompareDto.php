<?php

namespace Migration\Table\Application;

use Migration\Database\Domain\Database;

class CompareDto
{
    public Database $database_reference;
    public Database $database_to_apply;
    public string $base;
    public string $table;

    public function __construct(string $base, string $table, Database $database_reference, Database $database_to_apply)
    {   
        $this->base = $base;
        $this->table = $table;
        $this->database_reference = $database_reference;
        $this->database_to_apply = $database_to_apply;
    }

}
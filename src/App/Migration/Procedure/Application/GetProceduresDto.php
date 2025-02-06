<?php

namespace Migration\Procedure\Application;

use Migration\Database\Domain\Database;

class GetProceduresDto
{
    public string $base;
    public Database $database_1;

    public function __construct(Database $database_1, string $base)
    {   
        $this->base = $base;
        $this->database_1 = $database_1;
    }

}
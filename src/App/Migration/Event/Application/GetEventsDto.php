<?php

namespace Migration\Event\Application;

use Migration\Database\Domain\Database;

class GetEventsDto
{
   
    public string $base;
    public Database $database_1;
    public Database $database_2;

    public function __construct(Database $database_1, string $base)
    {   
        $this->base = $base;
        $this->database_1 = $database_1;
    }


}
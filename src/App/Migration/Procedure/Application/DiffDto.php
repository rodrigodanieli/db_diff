<?php

namespace Migration\Procedure\Application;

use Migration\Database\Domain\Database;

class DiffDto
{
    public int $project;
    public string $base_1;
    public string $base_2;
    public int $workers = 1;

    public function __construct(int $project, string $base_1, string $base_2)
    {
        $this->project = $project;
        $this->base_1 = $base_1;
        $this->base_2 = $base_2;
    }
}

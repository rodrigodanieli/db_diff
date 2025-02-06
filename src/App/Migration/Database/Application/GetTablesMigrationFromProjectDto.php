<?php

namespace Migration\Database\Application;

class GetTablesMigrationFromProjectDto
{
    public int $project_id;

    public function __construct(int $project_id)
    {
        $this->project_id = $project_id;
    }
}

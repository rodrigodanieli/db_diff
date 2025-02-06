<?php

namespace Migration\Database\Application;

class GetBasesMigrationFromProjectDto
{
    public int $project_id;

    public function __construct(int $project_id)
    {
        $this->project_id = $project_id;
    }
}

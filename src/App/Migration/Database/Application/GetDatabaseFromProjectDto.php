<?php

namespace Migration\Database\Application;

class GetDatabaseFromProjectDto
{
    public int $project_id;
    public string $database_name;

    public function __construct(int $project_id, string $database_name)
    {
        $this->project_id = $project_id;
        $this->database_name = $database_name;
    }
}

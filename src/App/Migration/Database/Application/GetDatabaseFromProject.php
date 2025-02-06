<?php

namespace Migration\Database\Application;

use Migration\Database\Domain\Database;
use Migration\Database\Domain\Interfaces\Repository;

class GetDatabaseFromProject
{
    private Repository $repository;

    public function __construct(Repository $repo)
    {
        $this->repository = $repo;
    }

    public function execute(GetDatabaseFromProjectDto $dto): Database
    {
        return $this->repository->getDatabase($dto->project_id, $dto->database_name);
    }
}

<?php

namespace Migration\Database\Application;

use Migration\Database\Domain\Database;
use Migration\Database\Domain\Interfaces\Repository;

class GetTablesMigrationFromProject
{
    private Repository $repository;

    public function __construct(Repository $repo)
    {
        $this->repository = $repo;
    }

    public function execute(GetTablesMigrationFromProjectDto $dto): array
    {
        return $this->repository->getTablesMigration($dto->project_id);
    }
}

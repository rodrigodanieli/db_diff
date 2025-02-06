<?php

namespace Migration\Database\Application;

use Migration\Database\Domain\Interfaces\Repository;

class GetBasesMigrationFromProject
{
    private Repository $repository;

    public function __construct(Repository $repo)
    {
        $this->repository = $repo;
    }

    public function execute(GetBasesMigrationFromProjectDto $dto): array
    {
        return $this->repository->getBasesMigration($dto->project_id);
    }
}

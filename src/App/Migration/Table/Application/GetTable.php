<?php

namespace Migration\Table\Application;

use Migration\Table\Domain\Interfaces\Repository;
use Migration\Table\Domain\Table;

class GetTable
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function execute(GetTableDto $dto): Table
    {
        return $this->repository->getTable($dto->database, $dto->base, $dto->table);
    }
}

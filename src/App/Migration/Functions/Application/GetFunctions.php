<?php

namespace Migration\Functions\Application;

use Migration\Functions\Domain\Interfaces\Repository;

class GetFunctions
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {   
        $this->repository = $repository;
    }

    public function execute(GetFunctionsDto $dto) : array
    {
        return array_column($this->repository->getFunctionsFromBase($dto->database_1, $dto->base),"ROUTINE_NAME");
    }

}
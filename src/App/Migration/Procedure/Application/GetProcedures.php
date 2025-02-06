<?php

namespace Migration\Procedure\Application;

use Migration\Procedure\Domain\Interfaces\Repository;

class GetProcedures
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {   
        $this->repository = $repository;
    }

    public function execute(GetProceduresDto $dto) : array
    {
        return array_column($this->repository->getProceduresFromBase($dto->database_1, $dto->base),"ROUTINE_NAME");
    }

}
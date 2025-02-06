<?php

namespace Migration\Procedure\Application;

use Migration\Procedure\Domain\Interfaces\Repository;

class GetProceduresFromBase
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {   
        $this->repository = $repository;
    }

    public function execute(GetProceduresFromBaseDto $dto) : array
    {
        return $this->repository->getProceduresFromBases($dto->database_1,$dto->database_2, $dto->base);
    }

}
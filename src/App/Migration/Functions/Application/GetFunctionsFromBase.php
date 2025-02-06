<?php

namespace Migration\Functions\Application;

use Migration\Functions\Domain\Interfaces\Repository;

class GetFunctionsFromBase
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {   
        $this->repository = $repository;
    }

    public function execute(GetFunctionsFromBaseDto $dto) : array
    {
        return $this->repository->getFunctionsFromBases($dto->database_1,$dto->database_2, $dto->base);
    }

}
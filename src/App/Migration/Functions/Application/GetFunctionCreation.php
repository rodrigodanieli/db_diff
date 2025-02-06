<?php

namespace Migration\Functions\Application;

use Migration\Functions\Domain\Interfaces\Repository;

class GetFunctionCreation
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {   
        $this->repository = $repository;
    }

    public function execute(GetFunctionCreationDto $dto) : string
    {
        $reference = $this->repository->getFunctions($dto->database_reference, $dto->base, $dto->name);
 
        return $reference->getCreateFunctions();
    }

}
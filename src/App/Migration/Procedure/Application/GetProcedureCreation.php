<?php

namespace Migration\Procedure\Application;

use DomainException;
use Migration\Procedure\Domain\Interfaces\Repository;

class GetProcedureCreation
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function execute(GetProcedureCreationDto $dto): string
    {
       $reference = $this->repository->getProcedure($dto->database_reference, $dto->base, $dto->name);

       return $reference->getCreateProcedure();
       
    }
}

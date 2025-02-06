<?php

namespace Migration\Trigger\Application;

use DomainException;
use Migration\Trigger\Domain\Interfaces\Repository;

class GetTriggerCreation
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {   
        $this->repository = $repository;
    }

    public function execute(GetTriggerCreationDto $dto) : string
    {
        $reference = $this->repository->getTrigger($dto->database_reference, $dto->base, $dto->name);
 
        return $reference->getCreateTrigger();
    }
}

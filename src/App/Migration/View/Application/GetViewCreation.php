<?php

namespace Migration\View\Application;

use Migration\View\Domain\Interfaces\Repository;

class GetViewCreation
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {   
        $this->repository = $repository;
    }

    public function execute(GetViewCreationDto $dto) : string
    {
        
        $reference = $this->repository->getView($dto->database_reference, $dto->base, $dto->name);
 
        return $reference->getCreateView() ;
    }

}
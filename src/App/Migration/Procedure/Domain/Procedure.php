<?php


namespace Migration\Procedure\Domain;

use Shared\Utils;

class Procedure
{

    private string $creation;
    private string $hash;
    private string $base;
    private string $name;


    public function __construct(string $name, string $base)
    {

        $this->name = $name;
        $this->base = $base;
    }

    public function addCreation(string $creation)
    {
        $this->creation = $creation;
        $this->hash = sha1(Utils::trimQuery($this->creation));
    }

    public function hash(): string
    {
        return $this->hash;
    }
    public function creation(): string
    {
        return $this->creation;
    }

    public function isSame(Procedure $procedure): bool
    {
        return $this->hash == $procedure->hash();
    }


    public function getCreateProcedure(): string
    {
        return "USE " . $this->base  . ";\n"
        . $this->getDropProcedure()
        . "DELIMITER &&\n"
        . $this->creation . " &&\n"
        . "DELIMITER ;\n";
    }

    public function getDropProcedure(): string
    {
        return "DROP PROCEDURE IF EXISTS " . $this->base . "." . $this->name . ";\n";
    }
}

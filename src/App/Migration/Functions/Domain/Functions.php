<?php


namespace Migration\Functions\Domain;

use Shared\Utils;

class Functions
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

    public function isSame(Functions $func): bool
    {
        return $this->hash == $func->hash();
    }


    public function getCreateFunctions(): string
    {
        return "USE " . $this->base  . ";\n"
            . $this->getDropFunctions()
            . "DELIMITER &&\n"
            . $this->creation . " &&\n"
            . "DELIMITER ;\n";
    }

    public function getDropFunctions(): string
    {
        return "DROP PROCEDURE IF EXISTS " . $this->base . "." . $this->name . ";\n";
    }
}

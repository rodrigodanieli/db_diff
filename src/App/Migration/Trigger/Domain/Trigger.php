<?php


namespace Migration\Trigger\Domain;

use Shared\Utils;

class Trigger
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

    public function isSame(Trigger $event): bool
    {
        return $this->hash == $event->hash();
    }


    public function getCreateTrigger(): string
    {
        return "USE " . $this->base  . ";\n"
        . $this->getDropTrigger()
        . "DELIMITER &&\n"
        . $this->creation . " &&\n"
        . "DELIMITER ;\n";
    }

    public function getDropTrigger(): string
    {
        return "DROP TRIGGER IF EXISTS " . $this->base . "." . $this->name . ";\n";
    }
}

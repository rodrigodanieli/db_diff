<?php


namespace Migration\View\Domain;

use Shared\Utils;

class View
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

    public function creation() : string
    {
        return $this->creation;
    }

    public function isSame(View $view): bool
    {
        return $this->hash == $view->hash();
    }


    public function getCreateView(): string
    {
        return "USE " . $this->base  . ";\n"
        . $this->getDropView()
        . "DELIMITER &&\n"
        . $this->creation . " &&\n"
        . "DELIMITER ;\n";
    }

    public function getDropView(): string
    {
        return "DROP VIEW IF EXISTS " . $this->base . "." . $this->name . ";\n";
    }
}

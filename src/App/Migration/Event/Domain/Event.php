<?php


namespace Migration\Event\Domain;

use Shared\Utils;

class Event
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

    public function isSame(Event $event): bool
    {
        return $this->hash == $event->hash();
    }


    public function getCreateEvent(): string
    {
        return
            "USE " . $this->base . ";\n"
            . $this->getDropEvent()
            . "DELIMITER &&\n"
            . $this->creation . " &&\n"
            . "DELIMITER ;\n";
    }

    public function getDropEvent(): string
    {
        return "DROP EVENT IF EXISTS " . $this->base . "." . $this->name . ";\n";
    }
}

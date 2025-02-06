<?php


namespace Migration\Table\Domain\Properties;

class Column
{
    private string $field;
    private string $type;
    private bool $null;
    private string $key;
    private string $default;
    private string $extra;
    private Column $previous;

    public function __construct(
        string $field,
        string $type,
        bool $null,
        $key,
        $default,
        $extra
    ) {
        $this->field = $field;
        $this->type = $type;
        $this->null = $null;
        if (!is_null($key))
            $this->key = $key;
        if (!is_null($default) || $default === "0")
            $this->default = $default;
        if (!is_null($extra))
            $this->extra = $extra;
    }

    public function field(): string
    {
        return $this->field;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function null(): bool
    {
        return $this->null;
    }

    public function default(): string
    {
        if (isset($this->default))
            return $this->default;
        return "";
    }

    public function extra(): string
    {
        if (isset($this->extra))
            return $this->extra;
        return "";
    }



    public function addPrevious(Column $column)
    {
        $this->previous = $column;
    }

    public function isSame(Column $column): bool
    {
        return $this->getStructure() == $column->getStructure();
    }

    public function getStructure(): string
    {
        $null = "";
        $default = "";
        if (!$this->null)
            $null = "NOT NULL";

        if (!empty($this->default)) {
            if ($this->default == "CURRENT_TIME")
                $default = "DEFAULT " . $this->default;
            else
                $default = "DEFAULT '" . $this->default . "'";
        } else if ($this->null)
            $default = "DEFAULT NULL";
        return "`" . $this->field . "` " . $this->type . " $null $default " . $this->extra;
    }

    public function getAddColumn(string $base, string $table)
    {
        $previous = "";
        if (isset($this->previous))
            $previous = " AFTER `" . $this->previous->field() . "`";
        return "ALTER TABLE $base.$table ADD COLUMN " . $this->getStructure() . $previous . ";\n";
    }

    public function getDropColumn(string $base, string $table)
    {
        return "ALTER TABLE $base.$table DROP COLUMN `" . $this->field() . "`;\n";
    }

    public function getModifyColumn(string $base, string $table)
    {
        return "ALTER TABLE $base.$table MODIFY COLUMN " . $this->getStructure() . ";\n";
    }
}

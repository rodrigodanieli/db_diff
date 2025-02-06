<?php

namespace Shared;


class Utils
{
    public static function trimQuery($query)
    {
        $query = preg_replace('/, /', ',', $query);
        $query = preg_replace('/([\n\t])/', ' ', $query);
        $query = preg_replace('/(\s\s+)/', ' ', $query);
        return $query;
    }
}

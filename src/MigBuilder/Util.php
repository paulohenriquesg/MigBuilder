<?php

namespace MigBuilder;

use Illuminate\Support\Str;

class Util
{
    public static function firstUpper(string $name, bool $evenFirstOne = true): string
    {
        $name = (stripos($name, '_') === false) ? Str::snake($name) : $name;

        $processedName = Str::of($name)->camel()->toString();

        if ($evenFirstOne === false) {
            return $processedName;
        }

        return ucfirst($processedName);
    }
}

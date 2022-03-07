<?php

namespace App\Misc;

/**
 * Temporarily add Doctrine DBAL type 'json_array' as a synonym for 'json' just to support 'make:migration' command
 */
class JsonArrayType extends \Doctrine\DBAL\Types\JsonType
{

    public function getName(): string
    {
        return 'json_array';
    }
}
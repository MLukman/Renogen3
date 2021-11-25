<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigTest;

class TwigInstanceOf extends AbstractExtension
{

    public function getTests(): array
    {
        return [
            new TwigTest('instanceof', [$this, 'isInstanceOf']),
        ];
    }

    public function isInstanceof($var, $instance)
    {
        if (!is_object($var)) {
            return false;
        }
        $reflexionClass = new \ReflectionClass($instance);
        return $reflexionClass->isInstance($var);
    }
}
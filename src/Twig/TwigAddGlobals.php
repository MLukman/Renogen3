<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class TwigAddGlobals extends AbstractExtension implements GlobalsInterface
{

    public function getGlobals(): array
    {
        return [
            "upload_max_filesize" => \ini_get("upload_max_filesize"),
        ];
    }
}
<?php

namespace App\Security\Authentication;

use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class EntryPoint implements AuthenticationEntryPointInterface
{

    use EntryPointTrait;
}
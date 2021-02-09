<?php

namespace MLukman\MultiAuthBundle\Authenticator;

use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class EntryPoint implements AuthenticationEntryPointInterface
{

    use EntryPointTrait;
}
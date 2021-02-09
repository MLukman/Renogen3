<?php

namespace MLukman\MultiAuthBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class RedirectAndCallback
{

    /**
     * @Route("/multiauth/hello", name="multiauth_hello", priority=100)
     */
    public function hello(Request $request)
    {
        return 'Hello';
    }
}
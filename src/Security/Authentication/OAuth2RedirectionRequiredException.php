<?php

namespace App\Security\Authentication;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class OAuth2RedirectionRequiredException extends AuthenticationException
{
    protected $url;

    public function __construct(string $url)
    {
        parent::__construct();
        $this->url = $url;
    }

    public function generateRedirectResponse()
    {
        return new RedirectResponse($this->url);
    }
}
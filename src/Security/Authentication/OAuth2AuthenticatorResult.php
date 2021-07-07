<?php

namespace App\Security\Authentication;

use Symfony\Component\HttpFoundation\RedirectResponse;

class OAuth2AuthenticatorResult
{
    private $redirectResponse;
    private $userInfo;

    static public function redirect(string $url)
    {
        $instance = new static();
        $instance->redirectResponse = new RedirectResponse($url);
        return $instance;
    }

    static public function userInfo(array $userInfo)
    {
        $instance = new static();
        $instance->userInfo = $userInfo;
        return $instance;
    }

    public function getRedirectResponse(): ?RedirectResponse
    {
        return $this->redirectResponse;
    }

    public function getUserInfo(): ?array
    {
        return $this->userInfo;
    }
}
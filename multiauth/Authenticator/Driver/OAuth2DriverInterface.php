<?php

namespace MLukman\MultiAuthBundle\Authenticator\Driver;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

interface OAuth2DriverInterface
{

    public function redirectToAuthorize(string $redirect_uri,
                                        SessionInterface $session): RedirectResponse;

    public function handleRedirectRequest(Request $request,
                                          HttpClientInterface $httpClient,
                                          SessionInterface $session): ?string;

    public function fetchAccessToken(string $code,
                                     HttpClientInterface $httpClient,
                                     SessionInterface $session): ?string;

    public function fetchUserInfo(string $access_token,
                                  HttpClientInterface $httpClient,
                                  SessionInterface $session): array;
}
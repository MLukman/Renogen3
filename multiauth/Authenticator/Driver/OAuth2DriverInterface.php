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
                                          SessionInterface $session,
                                          HttpClientInterface $httpClient,
                                          string $original_redirect_url): ?string;

    public function fetchAccessToken(HttpClientInterface $httpClient,
                                     string $code, string $redirect_uri): ?string;

    public function fetchUserInfo(HttpClientInterface $httpClient,
                                  string $access_token): array;
}
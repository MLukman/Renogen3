<?php

namespace MLukman\MultiAuthBundle\Authenticator\Driver;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

interface OAuth2DriverInterface
{

    public function redirectToAuthorize(string $redirect_uri): RedirectResponse;

    public function fetchAccessToken(HttpClientInterface $httpClient,
                                     string $code, string $redirect_uri): ?string;

    public function fetchUserInfo(HttpClientInterface $httpClient,
                                  string $access_token): array;
}
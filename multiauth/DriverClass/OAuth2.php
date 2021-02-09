<?php

namespace MLukman\MultiAuthBundle\DriverClass;

use MLukman\MultiAuthBundle\Authenticator\Driver\OAuth2DriverInterface;
use MLukman\MultiAuthBundle\DriverClass;
use MLukman\MultiAuthBundle\Identity\MultiAuthUserCredentialInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OAuth2 extends DriverClass implements OAuth2DriverInterface
{

    public function getLoginDisplay(): array
    {
        return array(
            'type' => 'oauth2',
            'params' => array(
                'id' => $this->instance->getId(),
                'name' => $this->instance->getTitle(),
            ),
        );
    }

    public static function checkParams(array $params)
    {

    }

    public static function getParamConfigs(): array
    {
        return array(
            array('authorize_url', 'Authorize URL', 'The OAuth2.0 provider authorize URL (e.g. login page)'),
            array('token_url', 'Token URL', 'The OAuth2.0 provider token exchange URL (code -> access token)'),
            array('userinfo_url', 'Userinfo URL', 'The OAuth2.0 provider get user info URL'),
            array('client_id', 'Client Id', 'Client Id'),
            array('client_secret', 'Client Secret', 'Client Secret'),
            array('scope', 'Requested Scope(s)', 'Space-delimited list of scopes (refer provider manual)'),
            array('username_field', 'Username Field', 'The field of inside responses of user info calls that refers to the username'),
            array('shortname_field', 'Short Name Field', 'The field of inside responses of user info calls that refers to the short name'),
            array('fullname_field', 'Full Name Field', 'The field of inside responses of user info calls that refers to the full name'),
            array('email_field', 'Email Field', 'The field of inside responses of user info calls that refers to the email address'),
        );
    }

    public static function getTitle(): string
    {

    }

    public function redirectToAuthorize(string $redirect_uri): RedirectResponse
    {
        return new RedirectResponse($this->params['authorize_url']."?".http_build_query(array(
                'client_id' => $this->params['client_id'],
                'response_type' => 'code',
                'scope' => $this->params['scope'],
                'redirect_uri' => $redirect_uri,
        )));
    }

    public function fetchAccessToken(HttpClientInterface $httpClient,
                                     string $code, string $redirect_uri): ?string
    {
        try {
            $token_response = $httpClient->request('POST', $this->params['token_url']."?".http_build_query(array(
                        'client_id' => $this->params['client_id'],
                        'client_secret' => $this->params['client_secret'],
                        'grant_type' => 'authorization_code',
                        'code' => $code,
                        'redirect_uri' => $redirect_uri,
                    )), array(
                    'headers' => array(
                        'Accept' => 'application/json'
                    )
                ))->toArray();
        } catch (\Exception $ex) {

        }
        return $token_response['access_token'] ?? null;
    }

    public function fetchUserInfo(HttpClientInterface $httpClient,
                                  string $access_token): array
    {
        try {
            $user_response = $httpClient->request('GET', $this->params['userinfo_url'], array(
                    'headers' => array(
                        'Authorization' => "Bearer $access_token",
                        'Accept' => 'application/json',
                    )
                ))->toArray();
        } catch (\Exception $ex) {

        }

        $user_info = array();
        foreach (array('username', 'shortname', 'fullname', 'email') as $field) {
            $user_info[$field] = $this->params["{$field}_field"] ? ($user_response[$this->params["{$field}_field"]]
                    ?? null) : null;
        }

        return $user_info;
    }

    public function prepareNewUser(MultiAuthUserCredentialInterface $user_credential)
    {
        
    }

    public function resetPassword(MultiAuthUserCredentialInterface $user_credential)
    {

    }

}
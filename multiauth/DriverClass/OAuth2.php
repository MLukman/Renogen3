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
        return [
            'type' => 'oauth2',
            'params' => [
                'id' => $this->instance->getId(),
                'name' => $this->instance->getTitle(),
            ],
        ];
    }

    public static function checkParams(array $params)
    {
        $errors = [];
        $param_configs = [];
        foreach (self::getParamConfigs() as $p) {
            $param_configs[$p[0]] = $p;
        }
        $required = ['authorize_url', 'userinfo_url', 'client_id',
            'scope', 'username_field'];

        foreach ($required as $r) {
            if (!isset($params[$r]) || empty($params[$r])) {
                $errors[$r] = $param_configs[$r][1].' is required';
            } elseif (substr($r, -4) == '_url' && (!filter_var($params[$r], FILTER_VALIDATE_URL)
                || !in_array(strtok($params[$r], ':'), ['http', 'https']))) {
                $errors[$r] = $param_configs[$r][1]." must be a valid URL";
            }
        }
        return (empty($errors) ? null : $errors);
    }

    public static function getParamConfigs(): array
    {
        return [
            ['authorize_url', 'Authorize URL *', 'The OAuth2.0 provider authorize URL (e.g. login page)'],
            ['token_url', 'Token URL', 'The OAuth2.0 provider token exchange URL (code -> access token)'],
            ['userinfo_url', 'Userinfo URL *', 'The OAuth2.0 provider get user info URL'],
            ['client_id', 'Client Id *', 'Client Id'],
            ['client_secret', 'Client Secret', 'Client Secret'],
            ['scope', 'Requested Scope(s) *', 'Space-delimited list of scopes (refer provider manual)'],
            ['username_field', 'Username Field *', 'The field of inside responses of user info calls that refers to the username'],
            ['shortname_field', 'Short Name Field', 'The field of inside responses of user info calls that refers to the short name'],
            ['fullname_field', 'Full Name Field', 'The field of inside responses of user info calls that refers to the full name'],
            ['email_field', 'Email Field', 'The field of inside responses of user info calls that refers to the email address'],
        ];
    }

    public static function getTitle(): string
    {
        return 'OAuth2.0 Provider';
    }

    public function redirectToAuthorize(string $redirect_uri): RedirectResponse
    {
        $params = [];
        $authorize_url = $this->params['authorize_url'];
        if (($qpos = strpos($authorize_url, '?')) !== false) {
            parse_str(substr($authorize_url, $qpos + 1), $params);
            $authorize_url = substr($authorize_url, 0, $qpos);
        }

        $params['response_type'] = (!empty($this->params['client_secret']) ?
            'code' : 'token');

        return new RedirectResponse($authorize_url."?".http_build_query(array_merge($params, [
                'client_id' => $this->params['client_id'],
                'scope' => $this->params['scope'],
                'redirect_uri' => $redirect_uri,
        ])));
    }

    public function fetchAccessToken(HttpClientInterface $httpClient,
                                     string $code, string $redirect_uri): ?string
    {
        try {
            $params = [];
            $token_url = $this->params['token_url'];
            if (($qpos = strpos($token_url, '?')) !== false) {
                parse_str(substr($token_url, $qpos + 1), $params);
                $token_url = substr($token_url, 0, $qpos);
            }

            $token_response = $httpClient->request('POST', $token_url."?".http_build_query(array_merge($params, [
                        'client_id' => $this->params['client_id'],
                        'client_secret' => $this->params['client_secret'],
                        'grant_type' => 'authorization_code',
                        'code' => $code,
                        'redirect_uri' => $redirect_uri,
                    ])), ['headers' => ['Accept' => 'application/json']])->toArray();
        } catch (\Exception $ex) {

        }
        return $token_response['access_token'] ?? null;
    }

    public function fetchUserInfo(HttpClientInterface $httpClient,
                                  string $access_token): array
    {
        try {
            $user_response = $httpClient->request('GET', $this->params['userinfo_url'], [
                    'headers' => [
                        'Authorization' => "Bearer $access_token",
                        'Accept' => 'application/json',
                    ]
                ])->toArray();
        } catch (\Exception $ex) {

        }

        $user_info = [];
        foreach (['username', 'shortname', 'fullname', 'email'] as $field) {
            $user_info[$field] = $this->params["{$field}_field"] ? ($user_response[$this->params["{$field}_field"]]
                    ?? null) : null;
        }
        $user_info['raw'] = $user_response;

        return $user_info;
    }

    public function prepareNewUser(MultiAuthUserCredentialInterface $user_credential)
    {
        
    }

    public function resetPassword(MultiAuthUserCredentialInterface $user_credential)
    {
        
    }
}
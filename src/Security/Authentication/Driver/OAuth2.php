<?php

namespace App\Security\Authentication\Driver;

use App\Entity\UserAuthentication;
use App\Security\Authentication\Driver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function GuzzleHttp\json_encode;
use function GuzzleHttp\Psr7\hash;

class OAuth2 extends Driver
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
            ['token_params_method', 'Token Parameters Method', 'Method for sending parameters of token_url',
                [
                    'form_urlencoded' => 'URL-Encoded Form',
                    'query_params' => 'Query Parameters (a.k.a GET Parameters)',
                    'json_body' => 'JSON Body',
                ]],
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

    public function generateRedirectToAuthorizeURL(string $redirect_uri,
                                                   SessionInterface $session): ?string
    {
        $params = [];
        $authorize_url = $this->params['authorize_url'];
        if (($qpos = strpos($authorize_url, '?')) !== false) {
            parse_str(substr($authorize_url, $qpos + 1), $params);
            $authorize_url = substr($authorize_url, 0, $qpos);
        }

        if (!$session->isStarted()) {
            $session->start();
        }

        $params['response_type'] = (!empty($this->params['token_url']) ? 'code' : 'token');

        if ($params['response_type'] === 'code' && empty($this->params['client_secret'])) {
            // use PKCE
            $session->set($this->getPKCESessionKey(), $code_verifier = bin2hex(random_bytes(64)));
            $params['code_challenge'] = rtrim(strtr(base64_encode(hash('sha256', $code_verifier, true)), '+/', '-_'), '=');
            $params['code_challenge_method'] = 'S256';
        } else {
            $session->remove($this->getPKCESessionKey());
        }

        $session->set($this->getSessionKey('redirect_uri'), $redirect_uri);
        return $authorize_url."?".http_build_query(array_merge($params, [
                'client_id' => $this->params['client_id'],
                'scope' => $this->params['scope'],
                'redirect_uri' => $redirect_uri,
                'state' => $session->getId(),
        ]));
    }

    public function handleRedirectRequest(Request $request,
                                          HttpClientInterface $httpClient,
                                          SessionInterface $session): ?string
    {
        if (!$session->isStarted()) {
            $session->start();
        }
        if ($request->query->get('state') !== $session->getId()) {
            // reject state param not matching the one provided to authorize_url
            return null;
        }
        if (!empty($token = $request->query->get('token'))) {
            // implicit flow
            return $token;
        }
        if (!empty($code = $request->query->get('code'))) {
            // auth token flow
            return $this->fetchAccessToken($code, $httpClient, $session);
        }
        return null;
    }

    public function fetchAccessToken(string $code,
                                     HttpClientInterface $httpClient,
                                     SessionInterface $session): ?string
    {
        try {
            $params = [];
            $token_url = $this->params['token_url'];
            if (($qpos = strpos($token_url, '?')) !== false) {
                parse_str(substr($token_url, $qpos + 1), $params);
                $token_url = substr($token_url, 0, $qpos);
            }

            if (($code_verifier = $session->get($this->getPKCESessionKey()))) {
                $params['code_verifier'] = $code_verifier;
            }
            if (!empty($this->params['client_secret'])) {
                $params['client_secret'] = $this->params['client_secret'];
            }

            $params = array_merge($params, [
                'client_id' => $this->params['client_id'],
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $session->get($this->getSessionKey('redirect_uri')),
            ]);
            $request_configs = ['headers' => ['Accept' => 'application/json']];

            switch ($this->params['token_params_method']) {
                case 'query_params':
                    $token_url = $token_url."?".http_build_query($params);
                    break;
                case 'json_body':
                    $request_configs['body'] = json_encode($params);
                    break;
                case 'form_urlencoded':
                default:
                    $request_configs['body'] = http_build_query($params);
                    break;
            }

            $token_response = $httpClient->request('POST', $token_url, $request_configs)->toArray();
            return $token_response['access_token'] ?? null;
        } catch (\Exception $ex) {
            return null;
        }
    }

    public function fetchUserInfo(string $access_token,
                                  HttpClientInterface $httpClient,
                                  SessionInterface $session): ?array
    {
        try {
            $user_response = $httpClient->request('GET', $this->params['userinfo_url'], [
                    'headers' => [
                        'Authorization' => "Bearer $access_token",
                        'Accept' => 'application/json',
                    ]
                ])->toArray();

            $user_info = [
                'raw' => $user_response,
            ];
            foreach (['username', 'shortname', 'fullname', 'email'] as $field) {
                $user_info[$field] = $this->params["{$field}_field"] ? ($user_response[$this->params["{$field}_field"]]
                        ?? null) : null;
            }

            return $user_info;
        } catch (\Exception $ex) {
            return null;
        }
    }

    public function resetPassword(UserAuthentication $user_auth)
    {

    }

    protected function getSessionKey(string $suffix): string
    {
        return "auth.oauth2.".$this->instance->name.".".$suffix;
    }

    protected function getPKCESessionKey()
    {
        return $this->getSessionKey('pkce');
    }

    public function prepareNewUser(UserAuthentication $user_auth)
    {

    }
}
<?php
namespace AceAuth;

use AceAuth\API\Authentication;
use AceAuth\API\Helpers\State\SessionStateHandler;
use AceAuth\Exception\ApiException;
use AceAuth\Exception\CoreException;
use AceAuth\Store\SessionStore;
use AceAuth\Store\StoreInterface;

class AceAuth{
    protected $domain;
    protected $clientId;
    protected $clientSecret;
    protected $responseType = 'code';
    protected $scope;
    protected $state;
    protected $accessToken;
    protected $refreshToken;
    protected $redirectUri;
    protected $stateHandler;
    protected $store;
    protected $responseMode = 'query';

    protected $user;
    public $persistantMap = [
        'refresh_token',
        'user',
        'access_token',
    ];

    public function __construct(array $config)
    {
        if (empty($config['domain'])) {
            throw new CoreException('Invalid domain');
        }

        if (empty($config['client_id'])) {
            throw new CoreException('Invalid client_id');
        }

        if (empty($config['client_secret'])) {
            throw new CoreException('Invalid client_secret');
        }

        if (empty($config['redirect_uri'])) {
            throw new CoreException('Invalid redirect_uri');
        }

        $this->domain              = $config['domain'];
        $this->clientId            = $config['client_id'];
        $this->clientSecret        = $config['client_secret'];
        $this->redirectUri         = $config['redirect_uri'];

        if (isset($config['response_type'])) {
            $this->responseType = $config['response_type'];
        }

        if (isset($config['scope'])) {
            $this->scope = $config['scope'];
        }

        $session_base_name = SessionStore::BASE_NAME;
        $session_cookie_expires = SessionStore::COOKIE_EXPIRES;
        $sessionStore = new SessionStore($session_base_name, $session_cookie_expires);

        $this->setStore($sessionStore);

        $stateStore         = new SessionStore($session_base_name, $session_cookie_expires);
        $this->stateHandler = new SessionStateHandler($stateStore);

        $this->authentication = new Authentication(
            $this->domain,
            $this->clientId,
            $this->clientSecret,
            $this->scope
        );

        $this->user         = $this->store->get('user');
        $this->accessToken  = $this->store->get('access_token');
        $this->refreshToken = $this->store->get('refresh_token');
    }
    public function login($state = null, $connection = null, array $additionalParams = [])
    {
        $params = [];

        if ($state) {
            $params['state'] = $state;
        }

        if ($connection) {
            $params['connection'] = $connection;
        }

        if (! empty($additionalParams) && is_array($additionalParams)) {
            $params = array_replace($params, $additionalParams);
        }

        $login_url = $this->getLoginUrl($params);

        header('Location: '.$login_url);
        exit;
    }
    public function getLoginUrl(array $params = [])
    {
        $default_params = [
            'scope' => $this->scope,
            'response_type' => $this->responseType,
            'response_mode' => $this->responseMode,
            'redirect_uri' => $this->redirectUri
        ];

        $auth_params = array_replace( $default_params, $params );
        $auth_params = array_filter( $auth_params );

        if (empty( $auth_params['state'] )) {
            $auth_params['state'] = $this->stateHandler->issue();
        } else {
            $this->stateHandler->store($auth_params['state']);
        }

        return $this->authentication->get_authorize_link(
            $auth_params['response_type'],
            $auth_params['redirect_uri'],
            null,
            null,
            $auth_params
        );
    }
    public function getUser()
    {
        if (! $this->user) {
            $this->exchange();
        }

        return $this->user;
    }
    public function getAccessToken()
    {
        if (! $this->accessToken) {
            $this->exchange();
        }

        return $this->accessToken;
    }
    public function getRefreshToken()
    {
        if (! $this->refreshToken) {
            $this->exchange();
        }

        return $this->refreshToken;
    }
    public function exchange()
    {
        $code = $this->getAuthorizationCode();
        if (! $code) {
            return false;
        }

        $state = $this->getState();
        if (! $this->stateHandler->validate($state)) {
            throw new CoreException('Invalid state');
        }

        if ($this->user) {
            throw new CoreException('Can\'t initialize a new session while there is one active session already');
        }

        $response = $this->authentication->code_exchange($code, $this->redirectUri);

        if (empty($response['access_token'])) {
            throw new ApiException('Invalid access_token - Retry login.');
        }

        $this->setAccessToken($response['access_token']);

        if (isset($response['refresh_token'])) {
            $this->setRefreshToken($response['refresh_token']);
        }
        $user = $this->authentication->userinfo($this->accessToken);
        if ($user)
        {
            if($user['code']===200)
            {
                $this->setUser($user['user']);
            }
            else
            {
                throw new CoreException($user['message']);
            }
        }

        return true;
    }
    public function renewTokens()
    {
        if (! $this->accessToken) {
            throw new CoreException('Can\'t renew the access token if there isn\'t one valid');
        }

        if (! $this->refreshToken) {
            throw new CoreException('Can\'t renew the access token if there isn\'t a refresh token available');
        }

        $response = $this->authentication->refresh_token( $this->refreshToken );

        if (empty($response['access_token']) || empty($response['id_token'])) {
            throw new ApiException('Token did not refresh correctly. Access or ID token not provided.');
        }

        $this->setAccessToken($response['access_token']);
        $this->setIdToken($response['id_token']);
    }
    public function setUser(array $user)
    {
        if (in_array('user', $this->persistantMap)) {
            $this->store->set('user', $user);
        }

        $this->user = $user;
        return $this;
    }
    public function setAccessToken($accessToken)
    {
        if (in_array('access_token', $this->persistantMap)) {
            $this->store->set('access_token', $accessToken);
        }

        $this->accessToken = $accessToken;
        return $this;
    }
    public function setRefreshToken($refreshToken)
    {
        if (in_array('refresh_token', $this->persistantMap)) {
            $this->store->set('refresh_token', $refreshToken);
        }

        $this->refreshToken = $refreshToken;
        return $this;
    }
    protected function getAuthorizationCode()
    {
        $code = null;
        if ($this->responseMode === 'query' && isset($_GET['code'])) {
            $code = $_GET['code'];
        } else if ($this->responseMode === 'form_post' && isset($_POST['code'])) {
            $code = $_POST['code'];
        }

        return $code;
    }
    protected function getState()
    {
        $state = null;
        if ($this->responseMode === 'query' && isset($_GET['state'])) {
            $state = $_GET['state'];
        } else if ($this->responseMode === 'form_post' && isset($_POST['state'])) {
            $state = $_POST['state'];
        }

        return $state;
    }
    public function logout()
    {
        $this->deleteAllPersistentData();
        $this->accessToken  = null;
        $this->user         = null;
        $this->idToken      = null;
        $this->refreshToken = null;
    }
    public function deleteAllPersistentData()
    {
        foreach ($this->persistantMap as $key) {
            $this->store->delete($key);
        }
    }
    public function setStore(StoreInterface $store)
    {
        $this->store = $store;
        return $this;
    }

}
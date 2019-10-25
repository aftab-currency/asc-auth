<?php

namespace AceAuth\API;

use AceAuth\API\Header\Authorization\AuthorizationBearer;
use AceAuth\API\Header\ForwardedFor;
use AceAuth\API\Helpers\ApiClient;
use AceAuth\Exception\ApiException;

use GuzzleHttp\Psr7;

class Authentication
{
    private $domain;
    private $client_id;
    private $client_secret;
    private $scope;
    private $apiClient;

    public function __construct(
        $domain,
        $client_id = null,
        $client_secret = null,
        $scope = null
    )
    {
        $this->domain        = $domain;
        $this->client_id     = $client_id;
        $this->client_secret = $client_secret;
        $this->scope         = $scope;

        $this->apiClient = new ApiClient( [
            'domain' => $this->domain,
            'basePath' => '/'
        ] );
    }

    public function get_authorize_link(
        $response_type,
        $redirect_uri,
        $connection = null,
        $state = null,
        array $additional_params = []
    )
    {
        $additional_params['response_type'] = $response_type;
        $additional_params['redirect_uri']  = $redirect_uri;
        $additional_params['client_id']     = $this->client_id;

        if ($connection !== null) {
            $additional_params['connection'] = $connection;
        }

        if ($state !== null) {
            $additional_params['state'] = $state;
        }

        return sprintf(
            '%s/oauth/authorize?%s',
            $this->domain,
            Psr7\build_query($additional_params)
        );
    }

    public function get_logout_link($returnTo = null, $client_id = null)
    {
        $params = [];

        if ($returnTo !== null) {
            $params['returnTo'] = $returnTo;
        }

        if ($client_id !== null) {
            $params['client_id'] = $client_id;
        }


        return sprintf(
            '%s/auth_logout?%s',
            $this->domain,
            Psr7\build_query($params)
        );
    }
    public function userinfo($access_token)
    {
        $options=array();
        if (! isset($options['client_id'])) {
            $options['client_id'] = $this->client_id;
        }
        return $this->apiClient->method('post')
        ->addPath('api/user')
        ->withBody(json_encode($options))
        ->withHeader(new AuthorizationBearer($access_token))
        ->call();
    }
    public function oauth_token(array $options = [])
    {
        if (! isset($options['client_id'])) {
            $options['client_id'] = $this->client_id;
        }

        if (! isset($options['client_secret'])) {
            $options['client_secret'] = $this->client_secret;
        }

        if (! isset($options['grant_type'])) {
            throw new ApiException('grant_type is mandatory');
        }

        $request = $this->apiClient->method('post')
            ->addPath( '/oauth/token' )
            ->withBody(json_encode($options));
        if (isset($options['auth0_forwarded_for'])) {
            $request->withHeader( new ForwardedFor( $options['auth0_forwarded_for'] ) );
        }

        return $request->call();

    }
    public function code_exchange($code, $redirect_uri)
    {
        $options = [];

        $options['client_secret'] = $this->client_secret;
        $options['redirect_uri']  = $redirect_uri;
        $options['code']          = $code;
        $options['grant_type']    = 'authorization_code';

        return $this->oauth_token($options);
    }
    public function refresh_token($refresh_token, array $options = [])
    {
        if (empty($refresh_token)) {
            throw new ApiException('Refresh token cannot be blank');
        }

        if (! isset($options['client_secret'])) {
            $options['client_secret'] = $this->client_secret;
        }

        if (empty($options['client_secret'])) {
            throw new ApiException('client_secret is mandatory');
        }

        if (! isset($options['client_id'])) {
            $options['client_id'] = $this->client_id;
        }

        if (empty($options['client_id'])) {
            throw new ApiException('client_id is mandatory');
        }

        $options['refresh_token'] = $refresh_token;
        $options['grant_type']    = 'refresh_token';

        return $this->oauth_token($options);
    }
    protected function setApiClient()
    {
        $apiDomain = $this->domain;

        $client = new ApiClient(
            [
                'domain' => $apiDomain,
                'basePath' => '/'
            ]
        );

        $this->apiClient = $client;
    }


}

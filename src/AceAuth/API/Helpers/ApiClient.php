<?php
namespace AceAuth\API\Helpers;

use AceAuth\API\Header\ContentType;

class ApiClient
{

    protected $domain;
    protected $basePath;
    protected $headers;
    protected $returnType;

    public function __construct($config)
    {
        $this->basePath      = $config['basePath'];
        $this->domain        = $config['domain'];
        $this->returnType    = isset( $config['returnType'] ) ? $config['returnType'] : null;
        $this->headers       = isset($config['headers']) ? $config['headers'] : [];
    }

    public function __call($name, $arguments)
    {
        $builder = new RequestBuilder([
            'domain' => $this->domain,
            'basePath' => $this->basePath,
            'method' => $name,
            'returnType' => $this->returnType,
        ]);

        return $builder->withHeaders($this->headers);
    }

    public function method($method, $set_content_type = true)
    {
        $method  = strtolower($method);
        $builder = new RequestBuilder([
            'domain' => $this->domain,
            'basePath' => $this->basePath,
            'method' => $method,
            'returnType' => $this->returnType,
        ]);
        $builder->withHeaders($this->headers);

        if ($set_content_type && in_array($method, [ 'patch', 'post', 'put', 'delete' ])) {
            $builder->withHeader(new ContentType('application/json'));
        }

        return $builder;
    }
}

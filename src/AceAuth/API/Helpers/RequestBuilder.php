<?php
namespace AceAuth\API\Helpers;

use AceAuth\API\Header\Header;
use AceAuth\Exception\CoreException;
use \GuzzleHttp\Client;
use \GuzzleHttp\Exception\RequestException;

class RequestBuilder
{
    protected $domain;
    protected $basePath;
    protected $path = [];
    protected $method = [];
    protected $headers = [];
    protected $params = [];
    protected $form_params = [];
    protected $files = [];
    protected $body;
    protected $returnTypes = [ 'body', 'headers', 'object' ];
    protected $returnType;
    public function __construct(array $config)
    {
        $this->method        = $config['method'];
        $this->domain        = $config['domain'];
        $this->basePath      = isset($config['basePath']) ? $config['basePath'] : '';
        $this->headers       = isset($config['headers']) ? $config['headers'] : [];

        if (array_key_exists('path', $config)) {
            $this->path = $config['path'];
        }

        $this->setReturnType( isset( $config['returnType'] ) ? $config['returnType'] : null );
    }

    public function __call($name, $arguments)
    {
        $argument = null;

        if (count($arguments) > 0) {
            $argument = $arguments[0];
        }

        $this->addPath($name, $argument);

        return $this;
    }
    public function addPath($name, $argument = null)
    {
        $this->path[] = $name;
        if ($argument !== null) {
            $this->path[] = $argument;
        }

        return $this;
    }
    public function addPathVariable($variable)
    {
        $this->path[] = $variable;
        return $this;
    }
    public function getUrl()
    {
        return trim(implode('/', $this->path), '/').$this->getParams();
    }
    public function getParams()
    {
        $paramsClean = [];
        foreach ($this->params as $param => $value) {
            if (! is_null( $value ) && '' !== $value) {
                $paramsClean[] = sprintf( '%s=%s', $param, $value );
            }
        }

        return empty($paramsClean) ? '' : '?'.implode('&', $paramsClean);
    }
    public function addFormParam($key, $value)
    {
        $this->form_params[$key] = $this->prepareBoolParam( $value );
        return $this;
    }
    public function getGuzzleOptions()
    {
        return array_merge(
            ['base_uri' => $this->domain.$this->basePath]
        );
    }
    public function call()
    {
        // Create a new Guzzle client.
        $client = new Client($this->getGuzzleOptions());

        try {
            $data = [
                'headers' => $this->headers,
                'body' => $this->body,
            ];

            if (! empty($this->files)) {
                $data['multipart'] = $this->buildMultiPart();
            } else if (! empty($this->form_params)) {
                $data['form_params'] = $this->form_params;
            }

            $response = $client->request($this->method, $this->getUrl(), $data);

            switch ($this->returnType) {
                case 'headers':
                return $response->getHeaders();

                case 'object':
                return $response;

                case 'body':
                default:
                    $body = (string) $response->getBody();
                    if (strpos($response->getHeaderLine('content-type'), 'json') !== false) {
                        return json_decode($body, true);
                    }
                return $body;
            }
        } catch (RequestException $e) {
            throw $e;
        }
    }
    public function withHeaders($headers)
    {
        foreach ($headers as $header) {
            $this->withHeader($header);
        }

        return $this;
    }
    public function withHeader($header, $value = null)
    {
        if ($header instanceof Header) {
            $this->headers[$header->getHeader()] = $header->getValue();
        } else {
            $this->headers[$header] = $value;
        }

        return $this;
    }
    public function withBody($body)
    {
        $this->body = $body;
        return $this;
    }
    public function withParam($key, $value)
    {
        $this->params[$key] = $this->prepareBoolParam( $value );
        return $this;
    }
    public function withParams($params)
    {
        foreach ($params as $param) {
            $this->withParam($param['key'], $param['value']);
        }

        return $this;
    }
    public function setReturnType($type)
    {
        if (empty($type)){
            $type = 'body';
        }
        if (!in_array($type, $this->returnTypes)) {
            throw new CoreException('Invalid returnType');
        }

        $this->returnType = $type;
        return $this;
    }
    private function buildMultiPart()
    {
        $multipart = [];

        foreach ($this->files as $field => $file) {
            $multipart[] = [
                'name' => $field,
                'contents' => fopen($file, 'r')
            ];
        }

        foreach ($this->form_params as $param => $value) {
            $multipart[] = [
                'name' => $param,
                'contents' => $value
            ];
        }

        return $multipart;
    }
    private function prepareBoolParam($value)
    {
        if (is_bool( $value )) {
            return true === $value ? 'true' : 'false';
        }

        return $value;
    }
}

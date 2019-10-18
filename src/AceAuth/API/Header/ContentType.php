<?php
namespace AceAuth\API\Header;

class ContentType extends Header
{
    public function __construct($contentType)
    {
        parent::__construct('Content-Type', $contentType);
    }
}

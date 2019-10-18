<?php
namespace AceAuth\API\Header\Authorization;

use AceAuth\API\Header\Header;

class AuthorizationBearer extends Header
{
    public function __construct($token)
    {
        parent::__construct('Authorization', "Bearer $token");
    }
}

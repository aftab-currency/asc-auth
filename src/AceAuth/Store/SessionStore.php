<?php

namespace AceAuth\Store;

class SessionStore implements StoreInterface
{

    const BASE_NAME = 'ace_auth';
    const COOKIE_EXPIRES = 604800;
    protected $session_base_name = self::BASE_NAME;
    protected $session_cookie_expires;
    public function __construct($base_name = self::BASE_NAME, $cookie_expires = self::COOKIE_EXPIRES)
    {
        $this->session_base_name      = (string) $base_name;
        $this->session_cookie_expires = (int) $cookie_expires;
    }
    private function initSession()
    {
        if (! session_id()) {
            if (! empty( $this->session_cookie_expires )) {
                session_set_cookie_params($this->session_cookie_expires);
            }

            session_start();
        }
    }
    public function set($key, $value)
    {
        $this->initSession();
        $key_name            = $this->getSessionKeyName($key);
        $_SESSION[$key_name] = $value;
    }
    public function get($key, $default = null)
    {
        $this->initSession();
        $key_name = $this->getSessionKeyName($key);

        if (isset($_SESSION[$key_name])) {
            return $_SESSION[$key_name];
        } else {
            return $default;
        }
    }
    public function delete($key)
    {
        $this->initSession();
        $key_name = $this->getSessionKeyName($key);
        unset($_SESSION[$key_name]);
    }
    public function getSessionKeyName($key)
    {
        $key_name = $key;
        if (! empty( $this->session_base_name )) {
            $key_name = $this->session_base_name.'_'.$key_name;
        }

        return $key_name;
    }
}

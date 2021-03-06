<?php
/**
 * NoFramework
 *
 * @author Roman Zaykin <roman@noframework.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 * @link http://noframework.com
 */

namespace NoFramework\Http;

class Request extends Url
{
    protected /*string*/ function __property_method()
    {
        return
            isset($_SERVER['REQUEST_METHOD'])
            ? substr($_SERVER['REQUEST_METHOD'], 0, 7)
            : 'GET';
    }

    protected /*string*/ function __property_scheme()
    {
            if (isset($_SERVER['SERVER_PROTOCOL'])
            and false !== strpos(
                strtolower($_SERVER['SERVER_PROTOCOL']),
                'https'
            )) {
                return 'https';
            }

            if (isset($this->base_url) or isset($this->url)) {
                return parent::__property_scheme();
            }

            return 'http';
    }

    protected /*string*/ function __property_host_port()
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            return $_SERVER['HTTP_X_FORWARDED_HOST'];
        }

        if (isset($_SERVER['HTTP_HOST'])) {
            return $_SERVER['HTTP_HOST'];
        }

        if (isset($this->base_url) or isset($this->url)) {
            return parent::__property_host_port();
        }

        return 'localhost';
    }

    protected function __property_request_uri()
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            return $_SERVER['REQUEST_URI'];
        }

        if (isset($this->url) or (
            (isset($this->path_string) or isset($this->path))
            and (isset($this->query_string) or isset($this->query))
        )) {
            return parent::__property_request_uri();
        }

        return '/';
    }

    protected function __property_query()
    {
        if (isset($_GET)) {
            return $_GET;
        }
        
        if (isset($this->url)
        or isset($this->request_uri)
        or isset($this->query_string)
        ) {
            return parent::__property_query();
        }

        return [];
    }

    protected function __property_post()
    {
        return isset($_POST) ? $_POST : [];
    }

    protected function __property_cookie()
    {
        return isset($_COOKIE) ? $_COOKIE : [];
    }

    protected function __property_files()
    {
        return isset($_FILES) ? $_FILES : [];
    }

    protected function __property_referer()
    {
        return isset($_SERVER['HTTP_REFERER'])
            ? $_SERVER['HTTP_REFERER']
            : false;
    }

    protected function __property_ip() {
        return isset($_SERVER['REMOTE_ADDR'])
            ? $_SERVER['REMOTE_ADDR']
            : '127.0.0.1';
    }

    protected function __property_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT'])
            ? $_SERVER['HTTP_USER_AGENT']
            : 'Unknown bot';
    }

    public function __call($property, $parameter) {
        if ( in_array($property, ['query', 'post', 'cookie', 'files']) ) {
            $out = $this->$property;

            if ( isset($out[$parameter[0]]) )
                $out = $out[$parameter[0]];
            else
                $out = null;

            return $out;
        }

        trigger_error(
            sprintf('Call to undefined method %s::%s.',
            get_called_class(),
            $property), 
        E_USER_ERROR);
    }
}


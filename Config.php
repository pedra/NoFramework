<?php
/**
 * NoFramework
 *
 * @author Roman Zaykin <roman@noframework.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 * @link http://noframework.com
 */

namespace NoFramework;

class Config
{
    const MAGIC_PARSE_METHOD = '__parse_';

    protected $script_path;
    protected $config_path;
    protected $cache_path;
    protected $local_path;

    public function __construct($state = [])
    {
        $this->script_path =
            isset($state['script_path'])
            ? $state['script_path']
            : dirname(realpath($_SERVER['SCRIPT_FILENAME']));

        foreach ([
            'config_path' => '.config' . DIRECTORY_SEPARATOR .
                str_replace('\\', DIRECTORY_SEPARATOR,  __NAMESPACE__),
            'cache_path' => '.cache',
            'local_path' => '.local'
        ] as $property => $find_path) {
            $this->$property =
                isset($state[$property])
                ? $state[$property]
                : $this->findPath($this->script_path, $find_path);
        }
    }

    protected function findPath($start, $find)
    {
        $current = $start;
        $found = false;

        do {
            if (is_dir($current . DIRECTORY_SEPARATOR . $find)) {
                $found = $current . DIRECTORY_SEPARATOR . $find;
                break;
            }

            $current = realpath($current . DIRECTORY_SEPARATOR . '..');
        } while (DIRECTORY_SEPARATOR !== $current);

        return $found ?: $start;
    }

    protected function getCallbacks()
    {
        $out = [];

        foreach (get_class_methods($this) as $method) {
            if (0 === strpos($method, static::MAGIC_PARSE_METHOD)) {
                $out['!' . substr($method, strlen(static::MAGIC_PARSE_METHOD))]
                    = [$this, $method];
            }
        }
        
        return $out;
    }

    public function withFile($input, $closure, $offset = 0, $pos = 0)
    {
        if (0 !== strpos($input, DIRECTORY_SEPARATOR)) {
            $input = $this->config_path . DIRECTORY_SEPARATOR . $input;
        }

        $yaml_parse = 'yaml_parse';

        if ($offset) {
            $input = file_get_contents($input, false, null, $offset);

        } else {
            $yaml_parse .= '_file'; 
        }

        $closure(
            $yaml_parse($input, $pos, $ndocs, $this->getCallbacks()),
            $ndocs
        );
        gc_collect_cycles();
        return $this;
    }

    public function withString($input, $closure, $pos = 0)
    {
        $closure(
            yaml_parse($input, $pos, $ndocs, $this->getCallbacks()),
            $ndocs
        );
        gc_collect_cycles();
        return $this;
    }

    public function __parse_ini_set($value, $tag, $flags)
    {
        if (is_array($value)) {
            foreach ($value as $ini_key => $ini_value) {
                ini_set($ini_key, $ini_value);
            }
        }

        return $value;
    }

    public function __parse_setTimeLimit($value, $tag, $flags)
    {
        set_time_limit($value);
        return $value;
    }

    public function __parse_setTimezone($value, $tag, $flags)
    {
        date_default_timezone_set($value);
        return $value;
    }

    public function __parse_period($value, $tag, $flags)
    {
        $interval = new \DateInterval(
            'P' . strtoupper(str_replace(' ', '', $value)));

        return ($interval->y * 365 * 24 * 60 * 60) +
            ($interval->m * 30 * 24 * 60 * 60) +
            ($interval->d * 24 * 60 * 60) +
            ($interval->h * 60 * 60) +
            ($interval->i * 60) +
            $interval->s;
    }

    public function __parse_script_path($value, $tag, $flags)
    {
        return $this->script_path .
            ($value ? DIRECTORY_SEPARATOR . $value : '');
    }

    public function __parse_cache_path($value, $tag, $flags)
    {
        return $this->cache_path .
            ($value ? DIRECTORY_SEPARATOR . $value : '');
    }

    public function __parse_local_path($value, $tag, $flags)
    {
        return $this->local_path .
            ($value ? DIRECTORY_SEPARATOR . $value : '');
    }

    public function __parse_read($value, $tag, $flags)
    {
        return ['$' => function($id = false) use ($value) {
            $offset = 0;

            if (isset($value['filename'])) {
                if (isset($value['offset'])) {
                    $offset = $value['offset'];
                }

                $value = $value['filename'];
            }

            $this->withFile($value, function ($in) use (&$out) {
                $out = $in;
            }, $offset);

            if (
                $id and
                isset($out['$new']) and
                !isset($out['$new']['local_reuse'])
            ) {
                $out['$new']['local_reuse'] = implode('.', $id);
            }

            return $out;
        }];
    }

    public function __parse_new($value, $tag, $flags)
    {
        return ['$new' => is_string($value) ? ['class' => $value] : $value];
    }

    public function __parse_reuse($value, $tag, $flags)
    {
        return ['$reuse' => $value];
    }

    public function __parse_autoloadRegister($value, $tag, $flags)
    {
        $out = [];

        foreach ((array)$value as $state) {
            if (!$state) {
                $state = ['namespace' => __NAMESPACE__];

            } elseif (is_string($state)) {
                $state = [
                    'namespace' => $state,
                    'path' => str_replace('\\', DIRECTORY_SEPARATOR, $state)
                ];
            }

            $class = __NAMESPACE__ . '\Autoload';

            if (isset($state['class'])) {
                $class = $state['class'];
                unset($state['class']);
            }

            if (isset($state['path']) and
                0 !== strpos($state['path'], DIRECTORY_SEPARATOR)
            ) {
                $state['path'] = realpath(
                    __DIR__ . DIRECTORY_SEPARATOR . '..' .
                    DIRECTORY_SEPARATOR . $state['path']
                );
            }

            if (!class_exists($class, false)) {
                require __DIR__ . DIRECTORY_SEPARATOR .
                '..' . DIRECTORY_SEPARATOR .
                str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
            }

            $out[] = (new $class($state))->register();
        }

        return $out;
    }

    public function __parse_errorHandlerRegister($value, $tag, $flags)
    {
        if (isset($value['error_types'])) {
            $error_types = (array)$value['error_types'];

        } elseif ($value) {
            $error_types = (array)$value;
        }

        $state = false;

        if (isset($error_types)) {
            $state = 0;

            foreach ($error_types as $error_type) {
                $state |= constant(
                    'E_' . str_replace(' ', '_', strtoupper($error_type))
                );
            }
        }

        $class = __NAMESPACE__ . '\Error\Handler';

        if (isset($value['class'])) {
            $class = $value['class'];
        }

        $error_handler = new $class($state);
        $error_handler->register();

        return $error_handler;
    }

    public static function __callStatic($name, $parameter)
    {
        $filename = $name . '.yaml';
        $offset = 0;

        if (isset($parameter[0])) {
            if (isset($parameter[1])) {
                $filename = $parameter[0];
                $offset = $parameter[1];

            } elseif (is_numeric($parameter[0])) {
                $offset = $parameter[0];

            } else {
                $filename = $parameter[0];
            }
        }

        (new static)->withFile($filename, function ($state) use ($name) {
            Factory::$name($state);
        }, $offset);

        return Factory::$name();
    }
}


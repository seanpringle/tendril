<?php

class Cache
{
    protected static $mc = null;
    protected static $cache = null;

    private static function connect()
    {
        if (is_null(static::$mc) && class_exists('Memcache') && isset($_ENV['mc_host']))
        {
            $env = dict($_ENV);
            if ($env->mc_host)
            {
                static::$mc = new Memcache();

                if ($env->debug)
                    error_log('memcache connect: '.$env->mc_host.':'.$env->mc_port);

                if (!static::$mc->addServer($env->mc_host, $env->mc_port))
                    static::$mc = null;
            }
        }
    }

    public static function get($key, $def=null)
    {
        static::connect();

        if (!is_null(static::$mc) && ($value = @static::$mc->get($key)))
            return $value;

        if (is_null(static::$cache))
            static::$cache = dict::make();

        return static::$cache->get($key, $def);
    }

    public static function set($key, $value, $expire=0)
    {
        static::connect();

        if (!is_null(static::$mc))
        {
            static::$mc->set($key, $value, 0, $expire);
            return;
        }

        if (is_null(static::$cache))
            static::$cache = dict::make();

        self::$cache->set($key, $value);
    }
}
<?php

class cache
{
    protected static $cache = array();

    public static function get($key, $type='string', $def=null)
    {
        if (function_exists('mc') && mc() && ($value = @mc()->get($key)))
        {
            $tmp[$key] = $value;
            return expect($tmp, $key, $type, $def);
        }
        return expect(self::$cache, $key, $type, $def);
    }

    public static function set($key, $value, $expire=0)
    {
        if (function_exists('mc') && mc())
        {
            if (class_exists('Memcache') && mc() instanceof Memcache)
                @mc()->set($key, $value, 0, $expire);
            if (class_exists('Memcached') && mc() instanceof Memcached)
                @mc()->set($key, $value, $expire);
            return;
        }
        self::$cache[$key] = $value;
    }
}
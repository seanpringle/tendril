<?php

function text($s=null)
{
    return Text::make($s);
}

function is_text($obj)
{
    return is_object($obj) && $obj instanceof Text;
}

class Text implements ArrayAccess, Iterator, Serializable
{
    protected $value = '';
    protected $ipos = 0;
    protected $matches = null;

    public static function htmlentities($str)
    {
        $charsets = array(
            'UTF-8',
            'ISO-8859-1',
            'ISO-8859-15',
            'GB2312',
            'BIG5',
            'BIG5-HKSCS',
            'Shift_JIS',
            'EUC-JP',
            'KOI8-R',
            'ISO-8859-5',
            'cp1251',
            'cp1252',
            'MacRoman',
        );

        $test = false;
        foreach ($charsets as $charset)
        {
            if ($test === false) $test = @iconv($charset, 'UTF-8//TRANSLIT', $str);
            if ($test !== false) { $str = $test; break; }
        }

        $flags = ENT_QUOTES;
        if (defined('ENT_SUBSTITUTE')) $flags |= ENT_SUBSTITUTE; // php 5.4
        if (defined('ENT_HTML5'))      $flags |= ENT_HTML5;      // php 5.4

        return htmlentities($str, $flags, 'UTF-8');
    }

    public function __construct($val=null)
    {
        $this->value = !is_null($val) ? strval($val): '';
    }

    public function __toString()
    {
        return $this->value;
    }

    public static function make($val=null)
    {
        return new static($val);
    }

    public function rewind()
    {
        $this->ipos = 0;
    }

    public function current()
    {
        return substr($this->value, $this->ipos, 1);
    }

    public function key()
    {
        return $this->ipos;
    }

    public function next()
    {
        $this->ipos++;
    }

    public function valid()
    {
        return strlen($this->value) > $this->ipos;
    }

    public function offsetSet($off, $val)
    {
        $this->value[$off] = $val;
    }

    public function offsetUnset($off)
    {
        $this->value[$off] = ' ';
    }

    public function offsetExists($off)
    {
        return strlen($this->value) > $off;
    }

    public function offsetGet($off)
    {
        return $this->value[$off];
    }

    public function serialize()
    {
        return $this->value;
    }

    public function unserialize($str)
    {
        $this->value = $str;
        return $this;
    }

    public function escape()
    {
        $this->value = static::htmlentities($this->value);
        return $this;
    }

    public function trim($mask=" \t\n\r\0\x0B")
    {
        $this->value = trim($this->value, $mask);
        return $this;
    }

    public function ltrim($mask=" \t\n\r\0\x0B")
    {
        $this->value = ltrim($this->value, $mask);
        return $this;
    }

    public function rtrim($mask=" \t\n\r\0\x0B")
    {
        $this->value = rtrim($this->value, $mask);
        return $this;
    }

    public function subtext($start, $length)
    {
        return text(substr($this->value, $start, $length));
    }

    public function match($pattern)
    {
        $this->matches = array();
        return preg_match($pattern, $this->value, $this->matches);
    }

    public function m($n)
    {
        return isset($this->matches[$n]) ? $this->matches[$n]: null;
    }

    public function match_all($pattern)
    {
        $this->matches = array();
        return preg_match_all($pattern, $this->value, $this->matches);
    }

    public function split($pattern=null)
    {
        if (!$this->value)
            return array();

        return preg_split($pattern, $this->value);
    }

    public function explode($pattern)
    {
        if (!$this->value)
            return array();

        return explode($pattern, $this->value);
    }

    public function replace($pattern=null, $replace)
    {
        $this->value = preg_replace($pattern, $replace, $this->value);
        return $this;
    }
}

<?php

class Package
{
    protected $_action = null;
    protected $_request = array();
    protected $_view = null;

    public function __construct()
    {
        global $package, $request;

        $action = count($request) && preg_match('/^(21)?[a-z]+[a-z0-9_\-]+$/i', $request[0])
            ? array_shift($request) : 'index';

        $this->_name      = $package;
        $this->_action    = $action;
        $this->_request   = array_merge($request, $_REQUEST);
    }

    public function action($text=null)
    {
        if (!is_null($text))
            $this->_action = $text;
        return $this->_action;
    }

    public function request($arg=null, $type='str', $def=null)
    {
        if (is_array($arg))
            $this->_request = $arg;
        if (!is_null($arg) && is_scalar($arg))
            return expect($this->_request, $arg, $type, $def);
        return $this->_request;
    }

    protected function head_refresh($seconds=60)
    {
        return sprintf('<meta http-equiv="refresh" content="%d">', $seconds);
    }

    public function head()
    {
        return '';
    }

    public function title($text=null)
    {
        return 'Package';
    }

    public function page()
    {
        return tag('section', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec congue arcu eget ipsum iaculis ornare.'
            .' Nulla semper, tortor sed dapibus laoreet, ante tortor feugiat risus, id placerat nunc mi quis enim.'
            .' Fusce sollicitudin diam a tellus molestie pellentesque. Nam et massa et enim malesuada vestibulum ac in arcu.'
            .' Praesent ipsum metus, pharetra vel aliquam eget, auctor a lorem. Suspendisse non dui sit amet augue mattis'
            .' mattis et id sapien. Duis vulputate scelerisque pulvinar. Vestibulum porttitor vehicula varius. Sed sagittis'
            .' rhoncus consequat. Ut fringilla quam id arcu rutrum pulvinar. Vivamus nec tortor vel augue interdum sagittis.'
            .' Donec venenatis, ipsum eu tincidunt interdum, mauris velit cursus odio, in facilisis elit turpis quis dui.'
            .' Proin volutpat ornare fermentum. Nulla quam enim, molestie at tristique nec, iaculis sed ipsum. Etiam suscipit,'
            .' dolor id malesuada lacinia, neque quam tempus urna, quis aliquam nunc diam tincidunt est. Nam ultricies mi eget'
            .' ipsum eleifend ut eleifend orci elementum.');
    }

    public function ajax()
    {
        return array();
    }

    public function process()
    {

    }

    public function view_ajax()
    {
        $this->_view = 'ajax';
    }

    public function display()
    {
        switch ($this->_view)
        {
            case 'ajax':
                header('Content-Type: application/json');
                $ajax = $this->ajax();
                //error_log(json_encode($ajax));
                die(json_encode($ajax));
        }
        require ROOT .'tpl/page.php';
    }

    public function die_404()
    {
        header('Status: 404 Not Found');
        die('huh?');
    }

}

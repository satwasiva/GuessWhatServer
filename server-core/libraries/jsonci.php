<?php
/*
* This file is the new amfphp library for CI
* It it loaded from the amf_gateway controller, and binds all of it together
* @Author Phil Palmieri ppalmieri [at] page12.com
*/
class Jsonci
{
    public $gateway;
    public $CI;

    public function __construct() {
        $this->CI = get_instance();

        require realpath(dirname(__FILE__))."/amfphp/globals.php";
        require realpath(dirname(__FILE__))."/amfphp/core/json/app/Gateway.php";
        define('AMFSERVICES', realpath(dirname(__FILE__))."/../controllers/services");

        $this->gateway = new Gateway();
//        $this->gateway->setCharsetHandler("utf8_decode", "ISO-8859-1", "ISO-8859-1");
//        $this->gateway->setLooseMode();
//        $this->gateway->setErrorHandling(E_ALL ^ E_NOTICE);
//        $this->gateway->setClassMappingsPath(AMFSERVICES.'/vo');
//        $this->gateway->setClassMappingsPath(realpath(dirname(__FILE__)).'/../models/');
        $search_dirs = array( APPPATH, COREPATH );
        if (is_array(config_item('additional_search_directories'))) {
            $search_dirs = array_merge($search_dirs, config_item('additional_search_directories'));
        }
        foreach($search_dirs as &$search_dir) {
            $search_dir = $search_dir . 'controllers/services';
        }
        $this->gateway->setClassPaths($search_dirs);

        if(PRODUCTION_SERVER) {
            //Disable profiling, remote tracing, and service browser
//            $this->gateway->disableDebug();
        }
    }

    public function service() {
        $this->gateway->service();
    }

}
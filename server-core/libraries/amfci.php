<?php
/*
* This file is the new amfphp library for CI
* It it loaded from the amf_gateway controller, and binds all of it together
* @Author Phil Palmieri ppalmieri [at] page12.com
*/
class Amfci
{
    public $gateway;
    public $CI;

    public function __construct() {
        $this->CI = get_instance();

        require realpath(dirname(__FILE__)) . "/amfphp/globals.php";
        require realpath(dirname(__FILE__)) . "/amfphp/core/amf/app/Gateway.php";
        define('AMFSERVICES', realpath(dirname(__FILE__))."/../controllers/services");

        $this->gateway = new Gateway();
        $this->gateway->setCharsetHandler("utf8_decode", "ISO-8859-1", "ISO-8859-1");
        $this->gateway->setLooseMode();
        $this->gateway->setErrorHandling(E_ALL ^ E_NOTICE);
//        $this->gateway->setClassMappingsPath(AMFSERVICES.'/vo');
        $this->gateway->setClassMappingsPath(realpath(dirname(__FILE__)).'/../models/');
        $this->gateway->setClassPath(AMFSERVICES);

        if(PRODUCTION_SERVER) {
            //Disable profiling, remote tracing, and service browser
            $this->gateway->disableDebug();
        }
    }

    public function service() {
        $this->gateway->service();
    }

}
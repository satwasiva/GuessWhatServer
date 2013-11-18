<?php

require_once(COREPATH . 'controllers/BaseController.php');

/*
* This file replaces the need for gateway.php
* setup netConnection to '/index.php/json_gateway'
* @Author Phil Palmieri ppalmieri [at] page12.com
*/
class Json_gateway extends BaseController
{

    public function __construct() {
        parent::BaseController();
        //Start our library (keep reading the tutorial)
        $this->load->library('jsonci');
        //Set new include path for services
        ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . AMFSERVICES);
    }

    //startup the amf gateway service
    public function index() {
        $this->jsonci->service();
    }

}
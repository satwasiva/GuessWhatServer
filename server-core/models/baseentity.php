<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class BaseEntity {

    function BaseEntity() {
	}

    public function db_fields() {
        throw new Exception("Forgot to add db_fields() function");
    }

}
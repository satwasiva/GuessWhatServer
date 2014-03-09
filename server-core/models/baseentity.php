<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class BaseEntity
{
    function __construct()
	{
	
	}

    public function db_fields()
	{
        throw new Exception("Forgot to add db_fields() function");
    }
}
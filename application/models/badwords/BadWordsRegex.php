<?php
require_once(COREPATH . 'models/baseentity.php');

/**
 * This class represents 
 *
 * @package DataModel
 *
 */
class BadWordsRegex extends BaseEntity {

    public $_explicitType = 'badwords.BadWordsRegex';

    public function db_fields() {
        return self::$_db_fields;
    }

    public static $_db_fields = array(
        "id" 							=> array("int", "none", false),
        "regex" 					    => array("string", "none", false),
    	"is_available" 					=> array("int", "none", false)
    );
    
    function BadWordsRegex() {
        parent::BaseEntity();
    }

    public $id;
    public $regex;
    public $is_available;

}

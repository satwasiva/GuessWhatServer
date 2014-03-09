<?php
require_once(COREPATH . '/models/baseentity.php');

/**
 * This class is the schema for all player tables
 *
 */
class JsonPlayerSchema extends BaseEntity {

    public $_explicitType = 'PlayerSchema';

    public static $_default_db_fields = array(
        "id" 				=> array("string", "none", false),
        "player_id" 		=> array("string", "none", false),
        "payload" 			=> array("string", "none", false),
        "time_created" 		=> array("datetime", "none", false),
        "time_updated" 		=> array("datetime", "none", false),
        "version" 			=> array("int", "none", false)
    );

    public static $_db_fields = array();
    
    public $id;
    public $player_id;
    public $payload;
    public $time_created;
    public $time_updated;
    public $version;
    
	/****************************************
	 * Constructor
	 */
	function __construct($db_fields = NULL)
	{
        if(!is_null($db_fields))
		{
            self::$_db_fields = $db_fields;
        } else {
            self::$_db_fields = self::$_default_db_fields;
        }
        parent::__construct();
    }
	
	/****************************************
	 * Accessor for db_fields
	 */
    public function db_fields()
	{
        return self::$_db_fields;
    }
}
<?php
require_once(COREPATH . 'models/baseentity.php');

/**
 * This class represents an item that a player owns.
 *
 * @package DataModel
 *
 *
 */
class PlayerBaseEntity extends BaseEntity {

    public $id;
    public $database_id;
    public $unique_id;
    public $player_id;
    public $payload;
    public $time_created;
    public $time_updated;
    public $version;
    
    public static $_db_fields = array(
    	"id" 						=> array("string", "none", false),
    	"player_id" 				=> array("string", "none", false),
    	"payload" 					=> array("string", "none", false),
    	"time_created" 				=> array("datetime", "now", false),
    	"time_updated" 				=> array("datetime", "now", false),
    	"version" 					=> array("int", 0, false)
    );

    function PlayerBaseEntity() {
        parent::BaseEntity();
        $sDate = date('Y-m-d H:i:s');
        $this->time_created = $sDate;
        $this->time_updated = $sDate;
        $this->version = 0;
    }

}
<?php
require_once(COREPATH . 'models/baseentity.php');

/**
 * This class represents a User.  It holds mapping information between a player_id, facebook_id, and iphone_udid.
 *
 * @package DataModel
 *
 *
 */
class User extends BaseEntity {

    public $_explicitType = 'user.User';

    public function db_fields() {
        return self::$_db_fields;
    }

    /**
     * @ignore
     */
    public static $_db_fields = array(
        "id"                    => array("string", "none", false),
        "player_id"             => array("string", "none", false),
        "facebook_id"           => array("string", "none", true),
        "facebook_user_name"    => array("string", "none", false),
        "first_name"            => array("string", "none", false),
        "last_name"             => array("string", "none", false),
        "gender"                => array("string", "none", false),
        "invite_code"           => array("string", "none", false),
        "ios_idfv"           => array("string", "none", true),
        "app_uuid"              => array("string", "none", false),
    	"game_center_id"        => array("string", "none", true),
    	"entry_source"          => array("string", "none", false),
        "os_type"               => array("string", "none", false),
        "third_party_id"        => array("string", "none", true),
        "country_code"          => array("string", "none", false),
    	"time_created"          => array("datetime", "now", false),
    	"version"               => array("int", "none", false)
    );

    function __construct() {
        parent::__construct();
    }

    public $id;
    public $player_id;
    public $facebook_id;
    public $facebook_user_name;
    public $first_name;
    public $last_name;
    public $gender;
    public $invite_code;
    public $ios_idfv;
    public $app_uuid;
    public $game_center_id;
    public $entry_source;
    public $os_type;
    public $country_code;
    public $third_party_id;
    public $time_created;
	public $version;
}


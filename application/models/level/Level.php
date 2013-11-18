<?php
require_once(COREPATH . 'models/baseentity.php');

/**
 * This class stores the experience amount required and respect amount given for a specific level.
 *
 * @package DataModel
 *
 */
class Level extends BaseEntity {

    public $_explicitType = 'level.Level';

	public function db_fields()
    {
        return self::$_db_fields;
    }

    public static $_db_fields = array(
        "id" 						    => array("int", "none", false),
    	"level" 					    => array("int", "none", false),
    	"exp_required" 				    => array("int", "none", false),
        "exp_increment"				    => array("int", "none", false),
        "welcome_reward"				=> array("string", "none", false)
    );

    public $id;
    public $level;
    public $exp_required;
    public $exp_increment;
    public $welcome_reward;

	function Level() {
        parent::BaseEntity();
    }

    public function sort_asc($level1, $level2)
    {
        if($level1->level == $level2->level)
        {
            return 0;
        }
        return ($level1->level < $level2->level) ? -1 : 1;
    }

    public function sort_desc($level1, $level2)
    {
        if($level1->level == $level2->level)
        {
            return 0;
        }
        return ($level1->level < $level2->level) ? 1 : -1;
    }
}

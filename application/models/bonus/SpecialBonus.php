<?php
require_once(COREPATH . 'models/baseentity.php');

    /**
     * This class stores the experience amount required and respect amount given for a specific level.
     *
     * @package DataModel
     *
     */
class AppointmentBonus extends BaseEntity {

    public $_explicitType = 'bonus.SpecialBonus';

    public function db_fields()
    {
        return self::$_db_fields;
    }

    public static $_db_fields = array(
        "id"                                    => array("int", 0, false),
        "bonus_level"                           => array("int", 0, false),
        "bonus_collect_in_mins"                 => array("int", 0, false),
        "bonus_collect_coins_payout"            => array("int", 0, false),
        "bonus_collect_coins_percent_payout"    => array("int", 0, false)
    );


    public $id;
    public $bonus_level;
    public $bonus_collect_in_mins;
    public $bonus_collect_coins_payout;
    public $bonus_collect_coins_percent_payout;

    function SpecialBonus()
    {
        parent::BaseEntity();
    }
}
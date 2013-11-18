<?php
require_once(COREPATH . 'models/playerbaseentity.php');

/**
 * This class represents an item that a player owns.
 *
 * @package DataModel
 *
 *
*/
class PlayerGamePayload extends PlayerBaseEntity
{
    public $_explicitType = 'PlayerGamePayload';

    public static $_json_fields = array(
        "special_bonus"            => array("string", "none", false),
        "puzzles_created"          => array("string", "none", false),
        "puzzles_solved"           => array("string", "none", false),
        "puzzles_pending"          => array("string", "none", false)
    );

    public $special_bonus;
    public $puzzles_created;
    public $puzzles_solved;
    public $puzzles_pending;

    function PlayerGamePayload()
    {
        parent::PlayerBaseEntity();
    }
}
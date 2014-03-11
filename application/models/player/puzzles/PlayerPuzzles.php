<?php
require_once(COREPATH . 'models/playerbaseentity.php');

/**
 * This class represents puzzles info.
 *
 * @package DataModel
 *
 *
*/
class PlayerPuzzles extends PlayerBaseEntity
{
    public $_explicitType = 'PlayerPuzzles';

    public static $_json_fields = array(
        "puzzle_url"           	=> array("string", "none", false),
        "choices"            	=> array("string", "none", false),
        "correct_answer_index"	=> array("int", "none", false),
        "solver_ids"            => array("string", "none", false),
        "attempter_ids"         => array("string", "none", false),
        "target_solve_time"     => array("string", "none", false),
        "latitude"     			=> array("float", "none", false),
        "longitude"    			=> array("float", "none", false)
    );

	public $puzzle_url;
	public $choices;
	public $correct_answer_index;
	public $solver_ids;
	public $attempter_ids;
	public $target_solve_time;
	public $latitude;
	public $longitude;
	
    function __construct()
    {
        parent::__construct();
    }
}

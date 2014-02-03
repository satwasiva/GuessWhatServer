<?php
require_once(COREPATH . 'models/jsonmodel.php');
require_once(dirname(__FILE__) .'/PlayerPuzzles.php');

class PlayerPuzzlesModel extends JsonModel
{
    protected $tbl_name = 'player_puzzles';

    function PlayerPuzzlesModel()
    {
        parent::JsonModel("player", "player_id");
    }

    public function add_player_puzzle($player_id, $choices, $correct_idx, $target_time, $latitude = 0, $longitude = 0)
    {
        $player_puzzle = $this->create();
		$player_puzzle->id = md5(date('Y-m-d H:i:s'));
        $player_puzzle->player_id = $player_id;
        $player_puzzle->choices = $choices;
        $player_puzzle->correct_idx = $correct_idx;
        $player_puzzle->target_time = $target_time;
        $player_puzzle->latitude = $latitude;
        $player_puzzle->longitude = $longitude;
        $player_puzzle->attempter_ids = "";
        $player_puzzle->solver_ids = "";
        $player_puzzle->puzzle_url = ""; // BASE_CDN + $puzzle_id
        $this->save($player_puzzle, true);
		
		return $player_puzzle;
	}
	
    private function client_player_puzzle_object($player_puzzle)
    {
        $client_player_puzzle = array();
        return $client_player_puzzle;
    }

    public function load_by_id($player_id, $puzzle_id)
    {
        $player_puzzle = parent::get_where(array('id' => $puzzle_id, 'player_id' => $player_id));
        return $player_puzzle;
    }

    public function load_all($player_id)
    {
        $player_puzzles = parent::get_where(array('player_id' => $player_id));
        
        return $player_puzzles;
    }
	
    public function set_payload_field($puzzle_id, $player_id, $field, $value)
    {
        $puzzle = parent::get_object(array("id" => $puzzle_id, "player_id" => $player_id));
        if ($puzzle && property_exists($puzzle, $field))
        {
            $puzzle->$field = json_encode($value);
            $this->save($puzzle);
        }
        else
        {
            warn(__FILE__, "GamePayload: No such field exist OR PlayerGame value is null! Field: $field");
        }
    }

	public function set_attempter_id($puzzle_id, $player_id, $attempter_id) {
        $puzzle = parent::get_object(array("id" => $puzzle_id, "player_id" => $player_id));
		
		if ($puzzle) {
			if ($puzzle->attempter_ids == '') {
				$puzzle->attempter_ids = $attempter_id;
			} else {
				$puzzle->attempter_ids .= ",".$attempter_id;
			}
			$this->save(puzzle);
		}
		else
        {
            warn(__FILE__, "No such puzzle exist");
        }
	}
	
	public function set_solver_id($puzzle_id, $player_id, $solver_id) {
        $puzzle = parent::get_object(array("id" => $puzzle_id, "player_id" => $player_id));
		
		if ($puzzle) {
			if ($puzzle->solver_ids == '') {
				$puzzle->solver_ids = $solver_id;
			} else {
				$puzzle->solver_ids .= ",".$solver_id;
			}
			$this->save(puzzle);
		}
		else
        {
            warn(__FILE__, "No such puzzle exist");
        }
	}
}
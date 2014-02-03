<?php

require_once(COREPATH . 'controllers/Base_Controller.php');

class Puzzle_Controller extends Base_Controller
{
	/**
	 * Constructor function
	 * @todo Document more please.
	 */
	public function __construct()
	{
		parent::__construct();
        $this->load->model('player/PlayerModel', '', FALSE);
        $this->load->model('player/puzzles/PlayerPuzzlesModel', '', FALSE);
        $this->load->model('user/UserModel','',FALSE);
        $this->load->model('player/puzzles/PlayerGamePayloadModel', '', FALSE);
		
        $this->load->helper('player_helper');
        $this->load->helper('data_helper');
        $this->load->helper('time_helper');
	}
		
	function post()
	{
		$data = $this->_post_args;
		$session_data = $data['session'];
		$session = new Session();
		$session->make_object_from_array($session_data);
		
    	$player = $this->PlayerModel->get_by_session($session);
		$service_data = array();
		
		switch ($data['puzzle_task'])
		{
			case 'create':
				$service_data['puzzle_created'] = $this->create_puzzle($data['puzzle_info']);
				break;
			case 'update_attempters':
				$service_data['puzzle_updated'] = $this->update_puzzle_attempters($data['player_id'], $data['puzzle_id']);
				break;
			case 'update_solvers':
				$this->update_puzzle_solvers($data['player_id'], $data['puzzle_id']);
				break;
			case 'update_attempters':
				$this->update_puzzle_attempters($data['player_id'], $data['puzzle_id']);
				$this->update_player_payload();
				break;
			case 'close':
				$this->close_puzzle($data['player_id'], $data['puzzle_id']);
				break;
		}
		
        $my_player = $this->PlayerModel->get($data['puzzle_info']['player_id']);
        $player_sync = sync_player($session, $my_player);
        $updated_player = $player_sync['player'];
        $updated_player->get_updated_client_obj_to_return();
        
		$service_data['player'] = $updated_player;
        $service_data['did_player_level_up'] = $player_sync['did_player_level_up'];
		$response = array();
		$response['success'] = true;
		$response['service_data'] = $service_data;
		
		return $this->response($response, 200);
	}
	
	function create_puzzle($puzzle_info)
	{
		$player_id = $puzzle_info['player_id'];
		$choices = $puzzle_info['choices'];
		$correct_idx = $puzzle_info['correct_idx'];
		$target_time = $puzzle_info['target_time'];
		$latitude = $puzzle_info['latitude'];
		$longitude = $puzzle_info['longitude'];
		$player_puzzle = $this->PlayerPuzzlesModel->add_player_puzzle($player_id, $choices, $correct_idx, $target_time, $latitude, $longitude);
		
		$this->PlayerGamePayloadModel->add_puzzle_created($player_id, $player_puzzle->id);
		
		return $player_puzzle;
	}
	
	function close_puzzle()
	{
		
	}
	
	function update_puzzle_solvers()
	{
	}
	
	function update_puzzle_attempters($puzzle_info)
	{
		$puzzle_id = $puzzle_info['puzzle_id'];
		$player_id = $puzzle_info['player_id'];
		$attempter_id = $puzzle_info['attempter_id'];
		$this->PlayerPuzzlesModel->set_attempter_id($puzzle_id, $player_id, $attempter_id);
		
		$this->PlayerGamePayloadModel->set_puzzles_pending($attempter_id, $puzzle_id);
	}
	
	function update_player_payload()
	{
	}
}
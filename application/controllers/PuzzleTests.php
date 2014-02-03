<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class PuzzleTests extends CI_Controller {

	/**
	 * Index Page for this controller.
	 *
	 * Maps to the following URL
	 * 		http://example.com/index.php/welcome
	 *	- or -  
	 * 		http://example.com/index.php/welcome/index
	 *	- or -
	 * Since this controller is set as the default controller in 
	 * config/routes.php, it's displayed at http://example.com/
	 *
	 * So any other public methods not prefixed with an underscore will
	 * map to /index.php/welcome/<method_name>
	 * @see http://codeigniter.com/user_guide/general/urls.html
	 */
	public function index()
	{  
		$username = 'admin';  
		$password = '1234';
		
		$this->load->library('curl');
		$session = array(
			'player_id' => '1285661465',
			'ios_idfv' => 'g567898056443dgfdgfgrty56565767',
			'session_id' => '1',
			'session_id' => '1',
			'session_key' => 'ghtyuiop9666',
			'api_version' => '1.2.3',
			'client_version' => '1.0.0',
			'game_name' => 'guess what',
			'game_data_version' => '1.0.1.1',
			'seconds_from_gmt' => '1367890346',
			'client_build' => '500',
			'transaction_time' => '1367890346',
			'invite_code' => '853782701'
		);
		
		$puzzle_info = array(
			'player_id' => '1285661465',
			'choices' => 'My plane ride, Fun With cousins, Fantastic foursome, Winner of all world!',
			'correct_idx' => 2,
			'target_time' => '30',
			'latitude' => '18.5',
			'longitude' => '82.5'
		);
		
		$this->curl->create('http://localhost/guesswhat/index.php/Puzzle_Controller/format/json');
		$this->curl->post(array(  
			'session' => $session,  
			'puzzle_info' => $puzzle_info,
			'puzzle_task' => 'create'
		));
		$result = $this->curl->execute();
  
		if($result !== null)  
		{
			echo 'User has been yyy updated.';
			echo $result;
		}
		else
		{
			echo 'Something has gone wrong';  
		}
	}
}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */
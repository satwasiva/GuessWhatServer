<?php
require_once(COREPATH . '/models/baseentity.php');

/**
 * This class stores the information needed to reference a player's currently active session on the server.
 */
class Session extends BaseEntity
{
    public $_explicitType = 'Session';

    public $transaction_time;
    public $ios_idfv;
    public $player_id;
    public $invite_code;
    public $session_id;
    public $session_key;
    public $api_version;
    public $client_version;
    public $client_build;
    public $game_name;
    public $game_data_version;
    public $seconds_from_gmt;

	/*
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}
	
	/*
	 * A utility function for setting session object members
	 *
	 * @param session_array (Array)
	 * @return void
	 */
	public function make_object_from_array($session_array)
	{
		$this->ios_idfv = $session_array['ios_idfv'];
		$this->player_id = $session_array['player_id'];
		$this->invite_code = $session_array['invite_code'];
		$this->session_id = $session_array['session_id'];
		$this->session_key = $session_array['session_key'];
		$this->api_version = $session_array['api_version'];
		$this->client_version = $session_array['client_version'];
		$this->game_name = $session_array['game_name'];
		$this->game_data_version = $session_array['game_data_version'];
		$this->seconds_from_gmt = $session_array['seconds_from_gmt'];
		$this->client_build = $session_array['client_build'];
		$this->transaction_time = $session_array['transaction_time'];
	}
}

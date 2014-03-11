<?php

require_once(COREPATH . 'models/playerbaseentity.php');
require_once(APPPATH . 'config/gameconstants.php');
require_once(COREPATH . 'libraries/DateTime_52.php');
require_once(APPPATH . 'models/SharedGameProperties.php');

/**
 * This class holds the primary game state for a player in the game.
 *
 * @package DataModel
 *
 *
 */
class Player extends PlayerBaseEntity {

    public $_explicitType = 'player.Player';

    public static $_db_fields = array(
    	"id" 						=> array("string", "none", false),
    	"player_id" 				=> array("string", "none", false),
    	"payload" 					=> array("string", "none", false),
    	"time_created" 				=> array("datetime", "none", false),
    	"time_updated" 				=> array("datetime", "none", false),
    	"version" 					=> array("int", "none", false)
    );

    public static $_json_fields = array(
        "invite_code"                   => array("string", "none", false),
        "username"                      => array("string", "none", false),
    	"points"                        => array("int", 0, false),
        "is_spender"                    => array("int", "none", false),
        "experience"                    => array("float", "0", false),
        "level"                         => array("int", "none", false),
    	"level_up"                      => array("int", "none", false),
        "ab_test"                       => array("string", "none", false),
        "is_banned"                     => array("int", "none", false),
        "is_muted"                      => array("int", "none", false),
    	"is_test_account" 				=> array("int", "none", false),
        "last_game_load_time"           => array("datetime", "none", false),
        "num_game_loads"                => array("int", "none", false),
        "server_sequence_num"			=> array("int", "none", false),
    	"available_vip_invites"         => array("int", "none", false),
    	"invite_vip_time"           	=> array("datetime", "none", false),
        "percent_level_complete"        => array("int", "none", false),
        "total_points_earned"            => array("float", "none", false),
        "total_usd_spent_to_date"       => array("int", "none", false),
        "seconds_from_gmt"              => array("int", "none", false),
        "game_payload"                  => array("string", "none", false),
        "time_last_player_sync" 		=> array("datetime", "none", false),
        "session_id"                    => array("int", "none", false),
        "session_start_time"           	=> array("datetime", "none", false),
        "last_session_request_time"     => array("datetime", "none", false),
        "package_name"                  => array("string", "none", false),
    );

    function __construct() {
        parent::__construct();
    }

    public $invite_code;
    public $username;
    public $points;
    public $money;
    public $is_spender;
    public $experience;
    public $level;
    public $level_up;
    public $ab_test;
    public $is_banned;
    public $is_muted;
    public $is_test_account;
    public $last_game_load_time;
    public $num_game_loads;
    public $server_sequence_num;
    public $available_vip_invites;
    public $invite_vip_time;

    //Analytics fields
    public $total_points_earned;
    public $percent_level_complete;
    public $total_usd_spent_to_date;
    public $seconds_from_gmt;
    public $game_payload;
    public $time_last_player_sync;
    public $session_id;
    public $session_start_time;
    public $last_session_request_time;
    public $package_name;


    public function get_username()
    {
    	if(!is_null($this->username))
    	{
    		return $this->username;
    	}
    	else
    	{
    		return "Unknown";
    	}
    }

    private function get_elapsed_time_in_minutes($start, $end) {
        $d1 = $start;
        if (is_string($start)) {
            $d1 = DateTime_52::createFromMysqlFormat($start);
            $d1 = $d1->getTimestamp();
        }

        $d2 = $end;
        if (is_string($end)) {
            $d2 = DateTime_52::createFromMysqlFormat($end);
            $d2 = $d2->getTimestamp();
        }
        
        $minutes = ($d2 - $d1) / 60;
        return $minutes;
    }

    public function increase_points($amount, $points_earned = false) {
        if(is_numeric($amount)){
            $money = (int) ($this->points + ($amount * 100));
            $this->points = ($money > 0) ? $money : 0;
            if ($points_earned) {
                $this->total_points_earned += $amount;
            }
        }
    }

    private function start_new_session() {
        $this->session_id += 1;
        $this->session_start_time = date('Y-m-d H:i:s');
        $this->last_session_request_time = date('Y-m-d H:i:s');
        debug(__FILE__, "SESSION: Starting New. NewSessionId: " . $this->session_id);
    }

    public function calculate_session_length() {
        return strtotime($this->last_session_request_time) - strtotime($this->session_start_time);
    }

    private function end_session($session_length_in_secs) {
        /* AnalyticsLogger::log('session_end', array(
            'session_id' => $this->session_id,
            'session_length' => $session_length_in_secs,
            'sec_since_last_activity' => (time() - strtotime($this->last_session_request_time)),
            'player_level' => $this->level,
            'player_id' => $this->player_id,
            'player_is_spender' => $this->is_spender,
            'player_ab_test' => $this->ab_test,
            'player_sc1_balance' => $this->get_points(),
            'player_num_totems' => 0, //TODO - kjs - This is per game now!
            'player_filter' => $this->is_test_account,
            'player_num_game_loads' => $this->num_game_loads,
            'player_percent_level_complete' => $this->percent_level_complete,
            'player_time_created' => $this->time_created,
            'player_total_sc1_earned' => $this->total_points_earned,
            'player_country_code' => $this->get_country_code())
        ); */
    }

    private function update_session() {
        $this->last_session_request_time = date('Y-m-d H:i:s');
    }

    public function is_same_session() {
        $max_inactivity_time = 5 * 60;
        $last_inactivity_secs = time() - strtotime($this->last_session_request_time);
        if($last_inactivity_secs > $max_inactivity_time)
        {
            $session_length_in_secs = $this->calculate_session_length();
            $this->end_session($session_length_in_secs);
            debug(__FILE__, "SESSION: End. LastInactivitySecs: $last_inactivity_secs LastSessionLengthSecs: $session_length_in_secs");
            $this->start_new_session();
            return false;
        }
        else {
            //debug(__FILE__, "SESSION: Same. Updating Last request Call time. LastInactivitySecs: $last_inactivity_secs");
            $this->update_session();
            return true;
        }
    }

    public function decrease_points($amount){
        if(is_numeric($amount)){
            $this->increase_points(-$amount);
        }
    }

    public function get_points(){
        return round(($this->points / 100), 2);
    }

    public function get_updated_client_obj_to_return() {
        //For client compatibility!
        $CI = & get_instance();
        $CI->load->model('player/game_payload/PlayerGamePayloadModel');
        $this->money = $this->get_points();
        $this->game_payload = $CI->PlayerGamePayloadModel->get_client_game_payload($this->id);
    }

    public function increase_experience($amount, $action, $action_detail = 'NULL')
    {
        $CI = & get_instance();
        $CI->load->model('level/LevelModel');
        $CI->load->model('player/PlayerModel');
        //$CI->load->model('player/achievements/PlayerAchievementsModel');
        $CI->load->helper('player');

        // Taking min of zero in case experience is going negative!
        $this->experience = max($this->experience + $amount, 0);
        $curr_level = $CI->LevelModel->get_level($this->level);
       // stat_log_experience($this->id, $this->get_iphone_udid(), $action, $action_detail, $this->level, $amount, $curr_level->exp_increment);
        $this->percent_level_complete = $this->calculate_percent_level_complete($curr_level);

        //Getting new level for experience
        $new_level = $CI->LevelModel->get_level_for_experience($this->experience);

        if ($curr_level->level != $new_level->level)
        {
            debug (__FILE__, "Level: New level " . $new_level->level);
           /*  AnalyticsLogger::log('level_up', array(
                'session_id' => $this->session_id,
                'player_level' => $this->level,
                'player_id' => $this->player_id,
                'player_is_spender' => $this->is_spender,
                'player_ab_test' => $this->ab_test,
                'player_sc1_balance' => $this->get_points(),
                'player_filter' => $this->is_test_account,
                'player_num_game_loads' => $this->num_game_loads,
                'player_percent_level_complete' => $this->percent_level_complete,
                'player_time_created' => $this->time_created,
                'player_total_sc1_earned' => $this->total_points_earned,
                'player_country_code' => $this->get_country_code(),
                'current_machine_version' => $curr_machine->version,
                'current_machine_name' => $curr_machine->name,
                'current_machine_id' => $curr_machine->id,
                'unlocked_machine_version' => $highest_unlocked_machine->version,
                'unlocked_machine_id' => $highest_unlocked_machine->id,
                'unlocked_machine_name' => $highest_unlocked_machine->name,
                'sc1_rewarded' => $new_level->welcome_reward)
            ); */

            $this->level = $new_level->level;
            //$CI->PlayerAchievementsModel->check_and_update_for_achievement($this->player_id, 'level_up', $this->level);
            //$CI->PlayerAchievementsModel->check_and_update_for_achievement($this->player_id, 'unlock_machine', $highest_unlocked_machine->id);

            //Give out payout for new level if it is greater than this level!
            if ($curr_level->level < $new_level->level)
            {
                $this->level_up = 1;
                $this->increase_points($new_level->welcome_reward, true);
                player_level_up($this);
            }

            $this->percent_level_complete = $this->calculate_percent_level_complete($new_level);
            debug(__FILE__, 'Player id = '.$this->id.', Level = '.$this->level.', Experience = '.$this->experience);
            $CI->PlayerModel->save($this, true);
            return true;
        }
        else
        {
            //debug(__FILE__, "CurrLevel: " . $curr_level->level . " NewLevel: " . $new_level->level);
            return false;
        }

    }

    private function calculate_percent_level_complete($curr_level) {
        $percent_complete = min(round( (($this->experience - $curr_level->exp_required) / $curr_level->exp_increment) * 100), 100);
        return $percent_complete;
    }

    public function is_US_user()
    {
        $CI = & get_instance();
        $CI->load->model('user/UserModel', '', FALSE);
        $myUser = $CI->UserModel->get_by_facebook_id($this->facebook_id);
        return ($myUser->country_code == 'US');
    }

    public function get_ios_idfv() {
        $CI = & get_instance();
        $CI->load->model('user/UserModel', '', FALSE);
        $user = $CI->UserModel->get_by_player_id($this->id);
        return $user->ios_idfv;
    }
    public function get_country_code() {
        $CI = & get_instance();
        $CI->load->model('user/UserModel', '', FALSE);
        $user = $CI->UserModel->get_by_player_id($this->id);
        return $user->country_code;
    }

    public function get_entry_source() {
        $CI = & get_instance();
        $CI->load->model('user/UserModel', '', FALSE);
        $user = $CI->UserModel->get_by_player_id($this->id);
        return $user->entry_source;
    }

    // takes the client_version as input in case we eventually need to change the return value based on client version
    public function get_token_for_md5($client_version)
    {
        $token = "";
        $token .= $this->get_points();
        $token .= $this->experience;
        $token .= $this->level;

        return $token;
    }
}

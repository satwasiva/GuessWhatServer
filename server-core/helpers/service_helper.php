<?php
require_once(APPPATH . 'config/gameconstants.php');

if (!defined('service_helper')) {
    define('service_helper', TRUE);
	
    /**
     * Determines whether the iphone user should be on this server
     * @param $session
     * @return array of new server to redirect user to, or nothing
     */
    function redirect_user($idfv)
    {
        $ios_idfv = $idfv;
        if ($ios_idfv == "RANDOM CONDITION"){
            return array('server_url' => 'CHANGEME!');
        }
        return array('server_url' => '');
    }

    function is_valid_request_payload($payload, $md5) {
        $SALT = "request-check-Salt-4-vam$i";
        $computed_md5 = md5($payload . $SALT);
        if ($computed_md5 != $md5) {
            warn(__FILE__, "Failed md5 check:  md5 = " . $md5 . "  computed md5 = " . $computed_md5);
        }
        return ($computed_md5 == $md5);
    }
	
    function get_md5_for_player($player)
    {
        $str = "$o$spicy-$alt-4-fun";
        $str .= ",".floor($player->get_coins());
        $str .= ",".$player->level;
        $str .= ",".$player->is_banned;
        $str .= ",".$player->is_test_account;
        $str .= ",".(is_null($player->last_game_load_time) ? "null" : $player->last_game_load_time);
        $str .= ",".$player->server_sequence_num;
        $str .= ",".$player->id;

        //debug(__FILE__, "str for md5".$str);

        return md5($str);
    }

    function get_start_time($transaction_start_time_client)
    {
        if($transaction_start_time_client < 946684800){        //This value corresponds to 01-01-2000 00:00:00
            $transaction_start_time = date('Y-m-d H:i:s');
        }
        else{
            $transaction_start_time =  date('Y-m-d H:i:s',$transaction_start_time_client);
        }
        return $transaction_start_time;
    }
	
    /**
     * Check if game is down for a user
     * @return array
     */
    function check_is_game_down($myPlayer) {
        $is_down = false;

        if (isset($myPlayer)) {
            if ($myPlayer->player_id % 100 < PERCENT_PLAYERS_DISABLED) {
                info(__FILE__, "GAME_DOWN - player is one of " . PERCENT_PLAYERS_DISABLED . "% players disabled.");
                $is_down = true;
            }
        }
        return $is_down;
    }

    /**
     * Check if user master database is down, returns true if user master database is down
     * @return boolean
     */
    function check_user_master_is_down() {
        //check if user master database is down
        require_once(APPPATH . 'config/database.php');
        require_once(COREPATH . 'database/DbConnStat.php');
        $is_down = false;
        if (DB_FAILOVER) {
            return false;
        }
        if (isset($GLOBALS['db_config']) && isset($GLOBALS['db_config']['user'])) {
            $user_master = $GLOBALS['db_config']['user'];
            $database_name = $user_master['database']; //this database name is different for each game
            $db_stat = DbConnStat::get_instance();
            $is_down = $db_stat->is_db_down($database_name);
        }

        if ($is_down) {
            warn(__FILE__, "GAME_DOWN USER_DATABASE_IS_DOWN db_stat: " . print_r($db_stat->get_stats(), true), WarnTypes::GAME_DOWN);
        }

        return $is_down;
    }

    /**
     * Check if player shard database is down, returns true if user master database is down
     * @return boolean
     */
    function check_player_database_is_down() {
        require_once(COREPATH . 'database/DbConnStat.php');
        if (!isset($GLOBALS['session'])) {
            return false;
        }
        if (DB_FAILOVER) {
            return false;
        }
        $player_id = $GLOBALS['session']->player_id;
        if (!$player_id) {
            $this->load->model('user/UserModel');
            $user = $this->UserModel->get_by_session($GLOBALS['session']);
            if ($user) {
                $player_id = $user->player_id;
            }
        }
        $is_down = false;
        if ($player_id) {
            $this->load->model('ShardMapModel');
            $shard = $this->ShardMapModel->get_db_shard('player', $player_id);
            if ($shard) {
                $db_stat = DbConnStat::get_instance();
                $is_down = $db_stat->is_db_down($shard->db_name);
            }
        }

        if ($is_down) {
            warn(__FILE__, "GAME_DOWN PLAYER_DATABASE_IS_DOWN db_stat: " . print_r($db_stat->get_stats(), true), WarnTypes::GAME_DOWN);
        }

        return $is_down;
    }
	
    function get_game_down_response() {
        $response = array();
        $response['status'] = "GAME_DOWN";
        $response['responses'] = array();
        $response['message'] = "The game is down for maintenance.  Please try again later.";
        return $response;
    }
	
    function get_md5_for_shared_properties($shared_game_properties)
    {
        $str = $shared_game_properties->server_time;
        $str .= ",".$shared_game_properties->apple_store_url;
        $str .= ",".$shared_game_properties->auto_spin_min_level;
        $str .= ",".$shared_game_properties->max_level;
        $str .= ","."shabhoom_82739472973_jjkvsjm";

        //debug(__FILE__, "Shared string: ".$str);
        return md5($str);
    }
}
?>
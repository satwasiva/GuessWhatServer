<?php

require_once(dirname(__FILE__) .'/Player.php');
require_once(COREPATH . 'models/jsonmodel.php');
require_once(APPPATH . 'config/gameconstants.php');
require_once(APPPATH . 'models/SharedGameProperties.php');
//require_once(APPPATH . 'models/request/GameRequest.php');
require_once(APPPATH . 'config/game_config.php');

//require_once(APPPATH . 'libraries/facebook/FacebookRequestManager.php');

class PlayerModel extends JsonModel {

    protected $tbl_name = 'player';

    function PlayerModel() {
        parent::JsonModel("player", "id");
        $this->load->model('player/game_payload/PlayerGamePayloadModel');
    }

    function create() {
        $player = new Player();
        $game_props = SharedGameProperties::get_instance();
        $sDate = date('Y-m-d H:i:s');
        $player->time_created = $sDate;
        $player->time_updated = $sDate;
        $player->version = 0;
        $player->username = 'Guest';
        $player->game_account_created = true;
        $player->increase_points($game_props->initial_points);
        $player->is_spender = 0;
        $player->experience = 0;
        $player->level = 1;
        $player->level_up = 0;
        $player->is_banned = 0;
        $player->is_muted = 0;
        $player->is_test_account = 0;
        $player->last_game_load_time = 0;
        $player->num_game_loads = 0;
        $player->server_sequence_num = 0;
        $player->total_points_earned = 0;
        $player->percent_level_complete = 0;
        $player->total_usd_spent_to_date = 0;
        $player->session_id = 1;
        $player->session_start_time = $sDate;
        $player->last_session_request_time = $sDate;
        return $player;
    }

    protected function generate_9digit_id() {
        return mt_rand(100000000, 999999999);
    }

    function get_or_create_player($user, $entry_source=NULL, $client_version=NULL) {
        $CI = & get_instance();
        $CI->load->helper('client_helper');

        if (is_null($user)) {
            throw new Exception("Cannot retrieve a player without a user object");
        }

        $player = $this->get($user->player_id);
        if (is_null($player)) {
            debug(__FILE__, 'Creating player! User: ' . json_encode($user));
            $player = $this->create();
            $player->id = $user->player_id;
            $player->player_id = $user->player_id;
            $player->invite_code = $user->invite_code;
            $player->entry_source = $entry_source;

            // Creating player game payload object when we create player.
            $this->PlayerGamePayloadModel->init($player->id);

            $last_game_load_time = $player->last_game_load_time;
            if($last_game_load_time == 0){
                debug(__FILE__, "Last game load time is 0. Substituting it with current time for analytics logging.");
                $last_game_load_time = date("Y-m-d H:i:s");
				$player->last_game_load_time = $last_game_load_time;
            }
            parent::save($player, true);
        }
        return $player;
    }

    function get_by_invite_code($invite_code) {
        if (is_null($invite_code)) {
            warn(__FILE__, "Attempt to get player object where invite_code = NULL", WarnTypes::DB_FRAMEWORK_WARN);
            return NULL;
        }
        $CI = & get_instance();
        $CI->load->model('user/UserModel');
        $user = $CI->UserModel->get_by_invite_code($invite_code);
        if (is_null($user)) {
            return NULL;
        }
        $player = $this->get($user->player_id);
        return $player;
    }

    /**
     *
     * This method is deprecated, please call get_by_session instead due to ios_idfv is deprecated
     * @param unknown_type $ios_idfv
     * @return NULL | Player <NULL, multitype:unknown >
     */
    function get_by_ios_idfv($ios_idfv)
    {
        if (is_null($ios_idfv)) {
            warn(__FILE__, "Attempt to get player object where ios_idfv = NULL", WarnTypes::DB_FRAMEWORK_WARN);
            return NULL;
        }
        $CI = & get_instance();
        $CI->load->model('user/UserModel');
        $user = $CI->UserModel->get_by_ios_idfv($ios_idfv);
        if (is_null($user)) {
            return NULL;
        }

        $player = $this->get($user->player_id);
        return $player;
    }

    function get_by_app_uuid($app_uuid)
    {
        if (is_null($app_uuid)) {
            warn(__FILE__, "Attempt to get player object where app_uuid = NULL", WarnTypes::DB_FRAMEWORK_WARN);
            return NULL;
        }
        $CI = & get_instance();
        $CI->load->model('user/UserModel');
        $user = $CI->UserModel->get_by_app_uuid($app_uuid);
        if (is_null($user)) {
            return NULL;
        }

        $player = $this->get($user->player_id);
        return $player;
    }

    function get_by_session($session = null) {
        if (!$session) {
            $session = $GLOBALS['session'];
        }

        $CI = get_instance();
        $CI->load->helper('session_helper');
        if ($session->player_id) {
            return $this->get($session->player_id);
        }
        if ($session->ios_idfv) {
            $player = $this->get_by_ios_idfv($session->ios_idfv);
            if ($player) {
                $session->player_id = $player->id;
                save_session($session);
                return $player;
            }
        }

        // TODO: Remove this, dangerous hack
        /**
        if ($session->app_uuid) {
            $player = $this->get_by_app_uuid($session->app_uuid);
            if ($player) {
                $session->player_id = $player->id;
                save_session($session);
                return $player;
            }
        }
         **/

        return NULL;
    }


    /**
     *
     * Get a list of players by player ids
     * Will try to fetch the players from the cache object first, if not found then query the db
     * If a db shard is down, will filter the players from the downed db
     * @param $player_ids
     * @return array map of player_id to player_obj
     */
    public function get_players($player_ids) {
        debug(__FILE__, "get_players called count: " . sizeof($player_ids));
        if (sizeof($player_ids) > 0) {
            return parent::get_where_in_cached($player_ids);
        } else {
            return array();
        }
    }

    public function get($id) {
        $player = parent::get_object(array("id" => $id));
        if(is_null($player))
        {
            return NULL;
        }
        $dirty = false;
        $sDate = date('Y-m-d H:i:s');

        //TODO Vamsi check if we need the following
        if($dirty)
        {
            $this->save($player);
        }
        return $player;
    }

//    public function reset_player($player_id) {
//        debug(__FILE__, 'resetting player');
//        $player = $this->get($player_id);
//        $old_player = $player;
//        $player = $this->create();
//        $player->id = $old_player->id;
//        $player->player_id = $old_player->player_id;
//        $player->invite_code = $old_player->invite_code;
//        $player->is_test_account = $old_player->is_test_account;
//        $player->force_win_option = $old_player->force_win_option;
//        $player->version = $old_player->version + 1;
//        $this->save($player);
//        debug(__FILE__, 'player reset complete');
//        return $player;
//    }

    public function save(&$player, $force_save = FALSE) {
        unset($GLOBALS['myPlayer']);
        $async = FALSE;
        $player->version += 1;
        $is_success = false;

        if (! isset($GLOBALS['playermodel_save_count'])) {
            $GLOBALS['playermodel_save_count'] = 0;
            $GLOBALS['playermodel_backtrace'] = array();
            $GLOBALS['playermodel_versions'] = array();
        }
        $GLOBALS['playermodel_save_count'] += 1;
        $GLOBALS['playermodel_backtrace'][] = debug_backtrace();
        $player_debug_save = array('player_id' => $player->id,
            'level' => $player->level,
            'experience' => $player->experience,
            'version' => $player->version);
        $GLOBALS['playermodel_versions'][] = $player_debug_save;

        // MAKE SURE ASYNC_WRITE_MODULO is always greater than 2 if you want player saves to go to the db
        if (ASYNC_WRITE_MODULO > 0 && ($player->version % ASYNC_WRITE_MODULO > 0)) {
            //debug(__FILE__, get_class($this) . ":  ASYNC_WRITE_CHECK = " . ($player->version+1));
            $async = TRUE;
        }

        $CI = get_instance();

        if (ENVIRONMENT == 'dev' || ENVIRONMENT == 'qa') {
            //debug(__FILE__, get_class($this) . ":  PLAYER SAVE PLAYER dev/qa");
            $is_success = parent::save_as_update_where($player, array("version <" => $player->version), FALSE, FALSE);
            //debug(__FILE__, 'SAVE_AS_UPDATE_WHERE success is of course:' . $is_success);
            return $is_success;
        }

        if ($force_save) {
            // TODO:  Eventually change to only perform update if not older version, ie array("version" < $player->version)
            //debug(__FILE__, get_class($this) . ":  PLAYER SAVE PLAYER force save");
            $is_success = parent::save_as_update_where($player, array("version <" => $player->version), $async, TRUE);
        } else {
            if (! isset($GLOBALS['playermodel_finalizer'])) {
                $GLOBALS['playermodel_finalizer'] = array();
            }

            $GLOBALS['playermodel_finalizer'][$player->id] = $player;
            //debug(__FILE__, get_class($this) . ":  PLAYER SAVE PLAYER not force_save: " . json_encode($player));
            $async_write = FALSE;

            if(NODEJS_MYSQL) {
                $async_write = FALSE;
            }

            $is_success = parent::save_as_update_where($player, array("version <" => $player->version), $async_write);
        }

        //debug(__FILE__, 'SAVE_AS_UPDATE_WHERE success is for fun:' . $is_success);
        return $is_success;
    }

    public static function flush_playermodel_save() {
        if (isset($GLOBALS['playermodel_finalizer'])) {
            $CI = & get_instance();
            $CI->load->model('player/PlayerModel');
            $players_to_save = $GLOBALS['playermodel_finalizer'];
            foreach ($players_to_save as $player_id => $player) {
                // 2011-03-12 - alk - commenting out to reduce logging output
                //debug(__FILE__, "PlayerModel:  PLAYERFINALIZER: Saving player: id = " . $player->id . "  version = " . $player->version);
                if (!NODEJS_MYSQL) {
                    $CI->PlayerModel->save($player, true);
                }
            }
        }
    }

    public function link_request_to_player($request_type, $request_id, $player_id) {
        if ($request_type === NULL || $request_id === NULL || $player_id === NULL) {
            throw new Exception("The request type, request id and player id is mandatory!");//, GameRequest::INVALID_PARAMS_ERROR_CODE);
        }

        $CI = & get_instance();
        $CI->load->model('user/UserModel', '', FALSE);

        $found_user = $CI->UserModel->get_by_player_id($player_id);

        if ($found_user === NULL) {
            throw new Exception("The player id specified does not exist!");//, GameRequest::INVALID_PARAMS_ERROR_CODE);
        }

        // generate mapping for linked request
        $success = true;
        $this->update_user_by_request($found_user, $request_type, $request_id);
        return $success;
    }

    protected function update_user_by_request($user, $request_type, $request_id) {
        //if ($request_type === GameRequest::FACEBOOK_RECIPIENT_TYPE) {
            //$facebook_user_profile = FacebookRequestManager::get_user_profile($request_id);

            if ($facebook_user_profile !== NULL) {
                $user->facebook_id = $facebook_user_profile['id'];
                $user->first_name = $facebook_user_profile['first_name'];
                $user->last_name = $facebook_user_profile['last_name'];

                if(isset($facebook_user_profile['gender'])) {
                    $user->gender = $facebook_user_profile['gender'];
                }

                if(isset($facebook_user_profile['username'])) {
                    $user->facebook_user_name = $facebook_user_profile['username'];
                }
            }

            $user->facebook_id = $request_id;

            $CI = & get_instance();
            $CI->load->model('user/UserModel', '', FALSE);
            $CI->UserModel->save($user);
        //} else {
        //    throw new Exception("Cannot update reward request by {$request_type}!", GameRequest::INVALID_PARAMS_ERROR_CODE);
        //}
    }
}

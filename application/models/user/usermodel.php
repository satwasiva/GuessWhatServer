<?php
require_once(dirname(__FILE__) .'/User.php');
require_once(COREPATH . 'models/singledbmodel.php');

class UserModel extends SingleDbModel {

    protected $tbl_name = 'user';

    function UserModel()
    {
        parent::SingleDbModel("user");
    }

    //deprecated method, use generate_player_id
    protected function generate_64bit_id() {
        // Each shard will hold about (2^40-1)ids for now.
        $generated_id = (0 + mt_rand(1, 1099511627776));
        $num_of_attempts = 1;
        while( !is_null($this->get_by_player_id($generated_id)) ){
            warn(__FILE__, 'Found a collision for player id: ' . strval($generated_id), WarnTypes::ID_COLLISION);
            $generated_id = (0 + mt_rand(1, 1099511627776));
            $num_of_attempts += 1;
            if ($num_of_attempts > 5){
                return NULL;
            }
        }
        return $generated_id;
    }

    protected function generate_9digit_id() {
        $generated_id = mt_rand(100000000, 999999999);
        $num_of_attempts = 1;
        while( !is_null($this->get_by_invite_code($generated_id)) ){
            warn(__FILE__, 'Found a collision for invite id: ' . strval($generated_id), WarnTypes::ID_COLLISION);
            $generated_id = mt_rand(100000000, 999999999);
            $num_of_attempts += 1;
            if ($num_of_attempts > 5){
                return NULL;
            }
        }
        return $generated_id;
    }

    public function create()
    {
        $obj = new User();
        $obj->time_created = date('Y-m-d H:i:s');
        $obj->version = 0;
        return $obj;
    }

    public function get_by_ios_idfv($ios_idfv) {
        $user_results = parent::get_where(array("ios_idfv" => $ios_idfv));
        if (sizeof($user_results) == 0) {
            return NULL;
        }
        return $user_results[0];
    }

    public function get_by_app_uuid($app_uuid) {
        $user_results = parent::get_where(array("app_uuid" => $app_uuid));
        if (sizeof($user_results) == 0) {
            return NULL;
        }
        return $user_results[0];
    }

    /**
     *
     * Replacement for get_by_ios_idfv
     * @param unknown_type $session
     * @return User <NULL, User>|NULL
     */
    public function get_by_session($session) {
        if ($session->ios_idfv) {
            $user_results =  $this->get_by_ios_idfv($session->ios_idfv);
            if (sizeof($user_results) > 0) {
                return $user_results;
            }
        }

        return NULL;
    }

    public function get_by_invite_code($invite_code) {
        $user_results = parent::get_where(array("invite_code" => $invite_code));
        //$user_results = parent::get_where(array("invite_code" => $invite_code));
        if (sizeof($user_results) == 0) {
            return NULL;
        }
        return $user_results[0];
    }


    public function get_by_player_id($player_id) {
        $user_results = parent::get_where(array("player_id" => $player_id));
        if (sizeof($user_results) == 0) {
            return NULL;
        }
        return $user_results[0];
    }

    public function get_by_third_party_id($third_party_id) {
        $user_results = parent::get_where(array("third_party_id" => $third_party_id));
        if (sizeof($user_results) == 0) {
            return NULL;
        }
        return $user_results[0];
    }

    public function get_friends_with_game_installed($player_id, $fb_friends) {
        if ($fb_friends == "") {
            return array();
        }
        $friends_with_game_installed = $this->get_users_by_facebook_ids($fb_friends);
        return $friends_with_game_installed;
    }

    public function get_by_facebook_id($facebook_id)
    {
        $user_results = parent::get_where(array("facebook_id" => $facebook_id));
        if (sizeof($user_results) == 0)
        {
            return NULL;
        }
        return $user_results[0];
    }

    private function print_request() {
        $request = array();
        foreach ($_REQUEST as $key => $value) {
            if (strpos($key, 'b_sig')) {
                continue;
            }
            $request[$key] = $value;
        }
        debug(__FILE__, 'ENTRYSOURCE: ' . json_encode($request));
    }


    public function generate_player_id() {
        $CI = & get_instance();
        $CI->load->model('player/PlayerModel');
        $CI->load->helper('net_helper');

        require_once(COREPATH . 'database/DbConnStat.php');
        $shards = DbConnStat::get_instance()->filter_db_shards('player');
        if (count($shards) == 0) {
            return $this->generate_64bit_id();
        }

        //pick a random index from the array of shards
        $index = array_rand($shards, 1);
        $shard = $shards[$index];
        $try = 1;
        //make sure the player id is unique
        do {
            $try++;
            if ($try > 5) {
                return null;
            }
            $player_id = mt_rand($shard->start_id + 1, $shard->end_id);
        } while ($this->get_by_player_id($player_id) != null);
        return $player_id;
    }

    public function disable($user, $disable_prefix="DISABLED_") {
        //Invalidate cache because we're about to change the cache key values
        $this->UserModel->invalidate($user);

        // Change device identifier values
        $udid = $user->ios_idfv;
        $app_uuid = $user->app_uuid;

        $reset_udid = $disable_prefix . $udid . '_' . date('YmdHis');
        $reset_app_uuid = $disable_prefix . $app_uuid . '_' . date('YmdHis');

        $user->ios_idfv = $reset_udid;
        $user->app_uuid = $reset_app_uuid;

        // Save disabled user
        $this->UserModel->save($user);
    }

    public function get_or_create_player($ios_idfv, $entry_source=NULL, $client_version = NULL,
                                        $device_type = NULL, $ios_version = NULL, $data_connection_type = NULL,
                                        $app_uuid = NULL, $client_build = null, $seconds_from_gmt = null, $os_type = IOS) {
        $CI = & get_instance();
        $CI->load->model('player/PlayerModel');
        $CI->load->helper('net_helper');
        $CI->load->model('ShardMapModel');

        $user_result = parent::get_where(array("ios_idfv" => $ios_idfv));

        // TODO: Remove this, dangerous hack
        //try to use app_uuid if udid is not found
        /*
        if (sizeof($user_result) == 0 && $app_uuid) {
            $user_result = parent::get_where(array("app_uuid" => $app_uuid));
        }
        */

        $user = NULL;
        if (is_null($entry_source)) {
            $entry_source = 'unknown';
        }
        if (sizeof($user_result) == 0) {
            debug(__FILE__, 'creating user');
            $user = $this->create();
            $player_id  = $this->generate_player_id();

            $user->player_id = $player_id;
            $user->invite_code = $this->generate_9digit_id();
            if ( is_null($user->player_id) || is_null($user->invite_code) ){
                throw new Exception('PLAYER ID OR INVITE CODE COLLISION FOR 5 TIMES');
            }
            $user->ios_idfv = $ios_idfv;
            $user->entry_source = $entry_source;
            $user->country_code = get_country_code();
            $user->id = $player_id;
            $user->os_type = $this->get_os_type($os_type);
            $user->app_uuid = $app_uuid;
            $this->save($user, true);


            /* AnalyticsLogger::log('user', array(
                'player_id' => $user->player_id,
                'ios_idfv' => $ios_idfv,
                'device_type' => $device_type,
                'ios_version' => $ios_version,
                'data_connection_type' => $data_connection_type,
                'country_code' => $user->country_code,
                'client_ip' => get_client_ip(),
                'entry_source' => $entry_source,
                'app_uuid' => $app_uuid,
                'mac_address' => $mac_address,
                'seconds_from_gmt' => $seconds_from_gmt,
                'client_build' => $client_build,
                'filter' => 0,
                'client_data_version' => $client_version,
                'invite_code' => $user->invite_code)
            );
 */
            $GLOBALS['new_user_during_request'] = true;
            $GLOBALS['session']->player_id = $user->player_id;
            $GLOBALS['session']->invite_code = $user->invite_code;

            //Graphite Logging
            //GraphiteLogger::add(GraphiteMetrics::$installs, 1, array('entry_source' => $entry_source));
        } else {
            $user = $user_result[0];
            $user->entry_source = $entry_source;
            if ($app_uuid) {
                $user->app_uuid = $app_uuid;
            }
            $this->save($user);
        }

		error_log("creating player from playermodel");
        $player = $CI->PlayerModel->get_or_create_player($user, $entry_source, $client_version);
		error_log("got player from playermodel");
        return $player;
    }

    private function get_os_type($passed_os_type) {
        if (is_null($passed_os_type) || $passed_os_type != ANDROID){
            $os_type  = IOS;
        } else {
            $os_type  = ANDROID;
        }
        debug(__FILE__, "OsType: $os_type");
        return $os_type;
    }

    // ONLY USE THIS ON DEV
    public function get_random_player_ids($facebook_id) {
        if (ENVIRONMENT == 'dev') {
            $CI = &get_instance();
            $CI->load->helper('battle_list_helper');
            $battle_list = array(); //get_weighted_battle_list($facebook_id, true);
            $user_ids = array();
            $i = 0;
            foreach ($battle_list as $opponent) {
                $user_ids[] = $opponent->id;
                $i++;
                if ($i > 10) {
                    break;
                }
            }
            debug_obj(__FILE__, $user_ids);
            return $user_ids;
        }
        return array();
    }

    public function save($obj, $force_insert = false) {
        $obj->version++;
        parent::save($obj, $force_insert);
    }

    public function invalidate($user)
    {
        return parent::invalidate($user);
    }

    public function remove_by_id($user) {

        return parent::delete($user, array("id" => $user->id));

    }
}

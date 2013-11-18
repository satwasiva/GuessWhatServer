<?php
//require_once(COREPATH . 'cache/CacheDataStore.php');
require_once(COREPATH . 'models/Session.php');

if (!defined('session_helper')) {
    define('session_helper', TRUE);

    function create_session_id() {
        $length = 10;
        $characters = "0123456789abcdefghijklmnopqrstuvwxyz";
        $string = "";
        for ($p = 0; $p < $length; $p++) {
            $string .= $characters[mt_rand(0, 35)];
        }
        return $string;
    }

    function create_new_session($facebook_id, $session_key) {
        //$dataStore = new CacheDataStore("session");
        $sess = new Session();
        $sess->fb_uid = $facebook_id;
        $sess->session_id = create_session_id();
        $sess->session_key = $session_key;
        //$dataStore->put(get_fb_session_key($facebook_id), $sess, 7200);  // Keep session around for 2 hours

        info(__FILE__, "Created new session for facebook_id = " . $facebook_id);
        return $sess;
    }

    function get_iphone_session_key($ios_idfv) {
        return "session:iphone:" . $ios_idfv;
    }

    function get_iphone_stored_session($ios_idfv, $app_uuid = NULL)
	{
		if (is_null($ios_idfv) && is_null($app_uuid)) {
            warn(__FILE__, "Received a null ios_idfv", WarnTypes::SESSION_WARN);
            return NULL;
        }

        //$dataStore = new CacheDataStore("session");

        $key = $ios_idfv ? $ios_idfv : $app_uuid;
        $stored_session = null;//$dataStore->get(get_iphone_session_key($key));

        if (is_null($stored_session)) {
            debug(__FILE__, "Could not find stored session using input ios_idfv = " . $key, WarnTypes::SESSION_WARN);
            return NULL;
        }

        return $stored_session;
    }

    function invalidate_iphone_stored_session($ios_idfv, $app_uuid = NULL) {
        if (is_null($ios_idfv) && is_null($app_uuid)) {
            warn(__FILE__, "Received a null ios_idfv", WarnTypes::SESSION_WARN);
            return NULL;
        }

        //$dataStore = new CacheDataStore("session");

        //$dataStore->delete(get_iphone_session_key($ios_idfv));
        //if ($app_uuid) {
        //    $dataStore->delete(get_iphone_session_key($app_uuid));
        //}
    }


    function create_new_iphone_session($ios_idfv, $app_uuid = null, $game_data_version = null) {
        //$dataStore = new CacheDataStore("session");
        $sess = new Session();
        $sess->ios_idfv = $ios_idfv;
        $sess->game_data_version = $game_data_version;

        $CI = & get_instance();
        $CI->load->model('UserModel', '', FALSE);
		error_log("app uuid" . $app_uuid);
        $user = $CI->UserModel->get_by_ios_idfv($ios_idfv);
		error_log("app uuid" . $app_uuid);

        // TODO: Remove this, dangerous hack
        /*
        if ($user == NULL && $app_uuid) {
            $user = $CI->UserModel->get_by_app_uuid($app_uuid);
        }
        */

        if($user != NULL) {
            $sess->player_id = $user->player_id;
            $sess->invite_code = $user->invite_code;
        }

        save_session($sess);
        info(__FILE__, "Created new session for ios_idfv = " . $ios_idfv);
        return $sess;
    }

    function save_session($session, $ttl = 7200) {
        //$dataStore = new CacheDataStore("session");
        $key = $session->ios_idfv ? $session->ios_idfv : $session->app_uuid;
        if (is_null($key)) {
            warn("session key cannot be null", WarnTypes::SESSION_WARN);
            return;
        }
        if (!$session->session_id) {
            $session->session_id = create_session_id();
        }
        //$dataStore->put(get_iphone_session_key($key), $session, $ttl);
    }

}

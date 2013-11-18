<?php
if (!defined('player_helper')) {
    define('player_helper', TRUE);
    function player_level_up($player) {
        $GLOBALS['did_player_level_up'] = true;
        $CI = & get_instance();
    }
    
    function sync_player($session, $player) {
        $CI = & get_instance();
        $CI->load->model('player/PlayerModel', '', FALSE);
        $did_player_level_up = false;
        //If the player leveled up, save the player and alert the client
        if ($player->level_up == 1) {
            $did_player_level_up = true;
            $player->level_up = 0;
            $CI->PlayerModel->save($player);
        }
        $player = $CI->PlayerModel->get($player->id);
        return array('player' => $player, 'did_player_level_up' => $did_player_level_up);
    }

    function record_player_login($myPlayer, $entry_source, $device_type, $ios_version, $data_connection_type, $client_version, $client_build, $client_data_version) {
        debug_obj(__FILE__, $GLOBALS['session']);
        debug(__FILE__, "Data conneciton type: " . json_encode($data_connection_type));

        try {
            $new_user = false;
            if (isset($GLOBALS['new_user_during_request'])) {
                $new_user = true;
            }

            $client_ip = get_client_ip();
            $country_code = get_country_code();

        } catch (Exception $e) {
            exception(__FILE__, $e);
        }

        AnalyticsLogger::log('player_login', array(
            'session_id' => $myPlayer->session_id,
            'player_id' => $myPlayer->player_id,
            'player_is_spender' => $myPlayer->is_spender,
            'player_ab_test' => $myPlayer->ab_test,
            'player_sc1_balance' => $myPlayer->get_coins(),
            'player_filter' => $myPlayer->is_test_account,
            'player_level' => $myPlayer->level,
            'player_num_game_loads' => $myPlayer->num_game_loads,
            'player_percent_level_complete' => $myPlayer->percent_level_complete,
            'player_time_created' => $myPlayer->time_created,
            'player_total_sc1_earned' => $myPlayer->total_coins_earned,
            'player_entry_source' => $myPlayer->get_entry_source(),
            'player_country_code' => $myPlayer->get_country_code(),
            'is_new_user' => $new_user ? 1 : 0,
            'client_ip' => $client_ip,
            'device_type' => $device_type,
            'ios_version' => $ios_version,
            'data_connection_type' => $data_connection_type,
            'client_version' => $client_version,
            'client_build' => $client_build,
            'client_data_version' => $client_data_version,
            'sec_since_last_activity' => (time() - strtotime($myPlayer->last_session_request_time)),
            'is_login' => 1
            )
        );
        //Graphite
        GraphiteLogger::add(GraphiteMetrics::$login, 1, array('login_source' => 'unknown'));
    }

    function record_player_login_from_session($myPlayer, $session) {

        try {
            $new_user = false;
            if (isset($GLOBALS['new_user_during_request'])) {
                $new_user = true;
            }

            $client_ip = get_client_ip();
            $country_code = get_country_code();

        } catch (Exception $e) {
            exception(__FILE__, $e);
        }

        AnalyticsLogger::log('player_login', array(
                'session_id' => $myPlayer->session_id,
                'player_id' => $myPlayer->player_id,
                'player_is_spender' => $myPlayer->is_spender,
                'player_ab_test' => $myPlayer->ab_test,
                'player_sc1_balance' => $myPlayer->get_coins(),
                'player_filter' => $myPlayer->is_test_account,
                'player_level' => $myPlayer->level,
                'player_num_game_loads' => $myPlayer->num_game_loads,
                'player_percent_level_complete' => $myPlayer->percent_level_complete,
                'player_time_created' => $myPlayer->time_created,
                'player_total_sc1_earned' => $myPlayer->total_coins_earned,
                'player_entry_source' => $myPlayer->get_entry_source(),
                'player_country_code' => $myPlayer->get_country_code(),
                'is_new_user' => $new_user ? 1 : 0,
                'client_ip' => $client_ip,
                'device_type' => isset($session->device_type) ? $session->device_type : NULL,
                'ios_version' => isset($session->ios_version) ? $session->ios_version : NULL,
                'data_connection_type' => isset($session->data_connection_type) ? $session->data_connection_type : NULL,
                'client_version' => isset($session->client_version) ? $session->client_version : NULL,
                'client_build' => isset($session->client_build) ? $session->client_build : NULL,
                'client_data_version' => isset($session->client_data_version) ? $session->client_data_version : NULL,
                'sec_since_last_activity' => (time() - strtotime($myPlayer->last_session_request_time)),
                'is_login' => 0
            )
        );
    }

    function record_link_player($myPlayer, $success, $request_type, $request_id) {

        $CI = & get_instance();
        $CI->load->model('user/UserModel', '', FALSE);

        try {
            $found_user = $CI->UserModel->get_by_player_id($myPlayer->id);
        } catch(Exception $e) {
            $found_user = NULL;
        }

        AnalyticsLogger::log('social_link', array(
                'session_id' => $myPlayer->session_id,
                'player_id' => $myPlayer->player_id,
                'player_is_spender' => $myPlayer->is_spender,
                'player_ab_test' => $myPlayer->ab_test,
                'player_sc1_balance' => $myPlayer->get_coins(),
                'player_filter' => $myPlayer->is_test_account,
                'player_level' => $myPlayer->level,
                'player_num_game_loads' => $myPlayer->num_game_loads,
                'player_percent_level_complete' => $myPlayer->percent_level_complete,
                'player_time_created' => $myPlayer->time_created,
                'player_total_sc1_earned' => $myPlayer->total_coins_earned,
                'player_entry_source' => $myPlayer->get_entry_source(),
                'player_country_code' => $myPlayer->get_country_code(),
                'success' => $success,
                'link_type' => $request_type,
                'link_id' => $request_id,
                'gender' => isset($found_user) ? $found_user->gender : null,
            )
        );
    }
}
?>

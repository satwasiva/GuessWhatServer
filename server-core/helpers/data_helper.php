<?php

if (! function_exists('get_field_from_objects'))
{

    function get_field_from_objects($field, $data) {
        $retval = array();
        foreach ($data as $d) {
            $retval[] = $d->$field;
        }
        return $retval;
    }
    
    function get_value_from_array($key, $data, $default_value)
    {
        if(array_key_exists($key, $data))
        {
            return $data[$key];
        }
        else
        {
            return $default_value;
        }
    }

    function get_exclude_lists(&$data, $player_id, $fb_friends, $exclude_ids) {
        $CI = & get_instance();
        $CI->load->model('user/UserModel', '', FALSE);
        $friends_with_game_installed = $CI->UserModel->get_friends_with_game_installed($player_id, $fb_friends);
        $friends_with_game_installed_fb_uids = get_field_from_objects("facebook_id", $friends_with_game_installed);

        $exclude_ids_all_friends_cs = '';
        if (sizeof($exclude_ids) > 0) {
            $exclude_ids_all_friends_cs = implode(",", $exclude_ids);
        }

        $exclude_ids_cc_friends = array_diff($fb_friends, $friends_with_game_installed_fb_uids);
        $exclude_ids_cc_friends = array_merge($exclude_ids_cc_friends, $exclude_ids);
        $exclude_ids_cc_friends_cs = '';
        if (sizeof($exclude_ids_cc_friends) > 0) {
            $exclude_ids_cc_friends_cs = implode(",", $exclude_ids_cc_friends);
        }

        $default_tab = 'all_friends';
        if (sizeof($fb_friends) - sizeof($exclude_ids_cc_friends) > 10) {
            $default_tab = 'cc_friends';
        }

        $data['exclude_ids_cc_friends'] = $exclude_ids_cc_friends_cs;
        $data['exclude_ids_all_friends'] = $exclude_ids_all_friends_cs;
        $data['default_tab'] = $default_tab;
    }

    function get_exclude_list($exclude_ids) {
        $exclude_list = '';
        if (sizeof($exclude_ids) > 0) {
            $exclude_list = implode(",", $exclude_ids);
        }
        return $exclude_list;
    }

}
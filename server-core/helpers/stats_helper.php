<?php
require_once(APPPATH . 'config/gameconstants.php');

if (!defined('stats_helper')) {
    define('stats_helper', TRUE);

    function get_entry_source($request) {
        $entry_source = NULL;
        if (isset($request['entry'])) {
            $entry_source = $request['entry'];
        }
        else if (isset($request['ref'])) {
            $entry_source = $request['ref'];
        }
        else if (isset($request['entrya'])) {
            if (isset($request['request_ids'])) {
                $entry_source = 'request';
            }
            else {
                $entry_source = $request['entrya'];
            }
        }
        return $entry_source;
    }

    function string_starts_with($needle, $haystack)
    {
        return (substr($haystack, 0, strlen($needle))==$needle);
    }

    function run_query($query, $name) {
        return query_dbs($query, $name);
    }

    function run_query_single_shard($query, $shard_num = 1) {
        $shard_slave_groups = $GLOBALS['shard_slave_groups'];
        $player_shards = $shard_slave_groups['player'];
        $i = 1;
        foreach ($player_shards as $shard) {
            $hostname = $shard[0];
            $database = $shard[1];
            if ($i >= $shard_num) {
                break;
            }
            $i++;
        }
        $db = $GLOBALS['temp_db'];
        $username = $db['default']['username'];
        $password = $db['default']['password'];

        $CI = & get_instance();
        $config = get_db_config($hostname, $username, $password, $database);
        $db = $CI->load->database($config, TRUE);
        $result = $db->query($query);
        return $result;
    }

    function run_query_get_total_count($query) {
        $db = $GLOBALS['temp_db'];
        $shard_slave_groups = $GLOBALS['shard_slave_groups'];
        $username = $db['default']['username'];
        $password = $db['default']['password'];
        
        $player_shards = $shard_slave_groups['player'];
        $summedResult = 0;
        foreach ($player_shards as $shard) {
            $hostname = $shard[0];
            $database = $shard[1];
            $summedResult += query_shard_raw($query, $hostname, $username, $password, $database);
        }
        return $summedResult;
    }

    function query_dbs($query, $name) {
        $db = $GLOBALS['temp_db'];
        $shard_slave_groups = $GLOBALS['shard_slave_groups'];
        $username = $db['default']['username'];
        $password = $db['default']['password'];

        $player_shards = $shard_slave_groups['player'];
        $result = '<h2>' . $name . '</h2>';
        foreach ($player_shards as $shard) {
            $hostname = $shard[0];
            $database = $shard[1];
            $result .= query_shard($query, $hostname, $username, $password, $database);
        }
        return $result;
    }

    function query_shard_raw($query, $hostname, $username, $password, $database) {
        $CI = & get_instance();
        $config = get_db_config($hostname, $username, $password, $database);
        $db = $CI->load->database($config, TRUE);
        $result = $db->query($query);
        foreach ($result->result_array() as $row) {
            debug_obj(__FILE__, $row);
            $field = "count(*)";

            return $row[$field];
        }
        return 0;
    }


    function query_shard($query, $hostname, $username, $password, $database) {
        $CI = & get_instance();
        $config = get_db_config($hostname, $username, $password, $database);
        $db = $CI->load->database($config, TRUE);
        $result = $db->query($query);
        $string_result = '<b>' . $database . '</b>:';
        foreach ($result->result() as $row) {
            $string_result .= json_encode($row);
        }
        $string_result .= "<br>";
        return $string_result;
    }

    function get_db_config($hostname, $username, $password, $database) {
        $config['hostname'] = $hostname;
        $config['username'] = $username;
        $config['password'] = $password;
        $config['database'] = $database;
        $config['dbdriver'] = "mysql";
        $config['dbprefix'] = "";
        $config['pconnect'] = FALSE;
        $config['db_debug'] = FALSE;
        $config['cache_on'] = FALSE;
        $config['cachedir'] = "";
        $config['char_set'] = "utf8";
        $config['dbcollat'] = "utf8_general_ci";
        return $config;
    }

    function do_player_detail($player_id) {
        if (!PLAYER_DETAIL) {
            return false;
        }
        else {
            $modulus = $player_id % PLAYER_DETAIL_MOD;
            return $modulus == 0;
        }
    }

}

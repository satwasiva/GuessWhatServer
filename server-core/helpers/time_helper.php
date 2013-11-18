<?php

if(!defined('time_helper')) {
    define('time_helper', TRUE);
    
    function get_time() {
        if (defined("UNIT_TEST") && isset($GLOBALS['faketime'])) {
            return $GLOBALS['faketime'];
        } else {
            return time();
        }
    }

    function set_time($time) {
        if (defined("UNIT_TEST")) {
            $GLOBALS['faketime'] = $time;
        }
    }

    function advance_time($seconds) {
        if (defined("UNIT_TEST")) {
            $time = $GLOBALS['faketime'];
            $new_time = $time + $seconds;
            $GLOBALS['faketime'] = $new_time;
        }
    }

    function get_date_stamp() {
        return date("Y-m-d H:i:s", get_time());
    }
}

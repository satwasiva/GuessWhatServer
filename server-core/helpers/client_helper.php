<?php
if (!defined('client_helper')) {
    define('client_helper', TRUE);

    function is_old_device($device_type) {
        $is_simulator = strpos($device_type, "Simulator") !== false;
        return $is_simulator;
    }

    function is_device_iPad($device_type){
        if (strpos($device_type, "iPad") !== false){
            return true;
        } else {
            return false;
        }
    }
    
    function is_device_retina($device_type) {
        if ($device_type == "iPhone 4" || $device_type == "Verizon iPhone 4" || $device_type == "iPhone 4S" || $device_type == "iPod Touch 4G"){
            return true;
        } else {
            return false;
        }
    }

    function is_ios_version_less_than($version, $base_version) {
        $ios_version = str_replace('iOS ', '', $version);
        return is_version_less_than($ios_version, $base_version);
    }

    function is_client_version_less_than($version, $base_version) {
        return false;
    }

    function is_version_less_than($version, $base_version) {
        $version_split = preg_split('/\./', $version);
        $min_version_split = preg_split('/\./', $base_version);

        for ($i = 0; $i < count($version_split); $i++) {
            if (count($min_version_split) > $i && $version_split[$i] > $min_version_split[$i]) {
                return false;
            } else if (count($min_version_split) > $i && $version_split[$i] < $min_version_split[$i]) {
                return true;
            }
        }
        if (count($version_split) < count($min_version_split)) {
            return true;
        }
        return false;

    }

    function is_game_data_version_less_than($version, $base_version) {
        if($version == NULL) {
            return true;
        }
        $version_split = preg_split('/_/', $version);
        $min_version_split = preg_split('/_/', $base_version);

        for ($i = 0; $i < count($version_split); $i++) {
            if (count($min_version_split) > $i && $version_split[$i] > $min_version_split[$i]) {
                return false;
            } else if (count($min_version_split) > $i && $version_split[$i] < $min_version_split[$i]) {
                return true;
            }
        }
        if (count($version_split) < count($min_version_split)) {
            return true;
        }
        return false;
    }

    function is_client_HD($version){
        return (strpos($version, "HD") !== false);
    }
    
    function is_client_ipad($device_type){
    	return strpos($device_type, "iPad") !== false;
    }

}

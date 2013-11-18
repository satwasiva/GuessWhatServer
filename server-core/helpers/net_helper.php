<?php
if (!defined('net_helper')) {
    define('net_helper', TRUE);

    function get_local_ip() {
    	if (ENVIRONMENT == 'dev') {
    		return "127.0.0.1";
    	}
        $success = false;
        $ip = apc_fetch('local_ip', $success);
        if (! $success) {
            $ip = exec("/sbin/ifconfig eth0 | grep \"inet addr\" | cut -d: -f2 | awk '{print $1}'");
            apc_add('local_ip', $ip);
        }
        return $ip;
    }


    function get_client_ip() {
        if (ENVIRONMENT == 'dev') {
            return "127.0.0.1";
        }

        $ip = NULL;

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] != "") {
            // Amazon ELB
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if(isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'] != "") {
            // Zeus
            $ip = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        } else if(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] != "") {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    function get_country_code() {
        // TODO - jlw - Should we filter local ips?

        $country_code = "--";
        $client_ip = get_client_ip();
        //debug(__FILE__, "Getting client IP: " . $client_ip);
        if (function_exists("geoip_country_code_by_name")) {
            if(filter_var($client_ip, FILTER_VALIDATE_IP)) {
                $country_code = geoip_country_code_by_name($client_ip);
                debug(__FILE__, "Client IP: " . $client_ip . "  Country Code: " . $country_code);
            }
        } else {
            debug(__FILE__, "GEOIP_COUNTRY_CODE_BY_NAME_NOT_FOUND: client_ip: " . $client_ip);
        }

        if ($country_code == "0" || $country_code === null) {
            debug(__FILE__, "GEOIP_COUNTRY_CODE_INVALID: ". $country_code ." client_ip: " . $client_ip);
        }

        return $country_code;
    }
}

<?php

if (!defined('global_helper')) {
    define('global_helper', TRUE);

    function define_platform($os_type) {
        if (empty($os_type) || $os_type != ANDROID) {
            $os_type = IOS;
        } else {
            $os_type = ANDROID;
        }

        if (!defined('PLATFORM')) {
            define('PLATFORM', $os_type);
            //debug(__FILE__, "Defining PLATFORM:" . PLATFORM);
        }
    }
}
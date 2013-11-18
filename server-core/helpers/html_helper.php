<?php

if (! function_exists('facebook_redirect')) {
    function facebook_redirect($url) {
        if (preg_match('/^https?:\/\/([^\/]*\.)?facebook\.com(:\d+)?/i', $url)) {
            // make sure facebook.com url's load in the full frame so that we don't
            // get a frame within a frame.
            echo "<script type=\"text/javascript\">\ntop.location.href = \"$url\";\n</script>";
        } else {
            header('Location: ' . $url);
        }
        exit;
    }
}

if (! function_exists('create_url_get'))
{

    function create_url_get($url) {
        return $url;
        /*
        $new_url = $url;
        if (strpos($url, '?')) {
            $new_url = $new_url . "&cc_fbuid=" . $GLOBALS['session']->fb_uid . "&token=" . $GLOBALS['session']->session_id;
        } else {
            $new_url = $new_url . "?cc_fbuid=" . $GLOBALS['session']->fb_uid . "&token=" . $GLOBALS['session']->session_id;
        }

        return $new_url;
        */
    }


}

if (! function_exists('set_url_source'))
{

    function set_url_source($url, $source) {
        $new_url = $url;
        if (strpos($url, '?')) {
            $new_url = $new_url . "&entry=" . $source;
        } else {
            $new_url = $new_url . "?entry=" . $source;
        }

        return $new_url;
    }


}
if (! function_exists('set_url_cache_buster_version')) {
    function set_url_cache_buster_version($url) {
        require_once(APPPATH . "config/game_config.php");
        $new_url = $url;
        if (strpos($url, '?')) {
            $new_url = $new_url . "&cb=".CACHE_BUSTER;
        }
        else {
            $new_url = $new_url . "?cb=".CACHE_BUSTER;
        }
        if (strpos($url, "crimecity.js") !== false) {
            $new_url = $new_url ."&cjs=".CC_JS_CACHE_BUSTER;
        }
        return $new_url;
    }
}


// TODO:  Maybe need to send in a request id instead of just sender id.
if (! function_exists('create_url_request_callback')) {
    function create_url_request_callback($url) {
        $new_url = $url;
        if (strpos($url, '?')) {
            $new_url = $new_url . "&sender_facebook_id=" . $GLOBALS['session']->fb_uid;
        }
        else {
            $new_url = $new_url . "?sender_facebook_id=" . $GLOBALS['session']->fb_uid;
        }
        return $new_url;
    }
}

if (! function_exists('is_ajax')) {
    function is_ajax() {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest'));
    }
}

if (! function_exists('create_url_with_params')) {
    function create_url_with_params($url, $params) {
        $new_url = $url;
        $first = false;
        if (! strpos($url, '?')) {
            $new_url = $new_url . "?";
            $first = true;
        }
        foreach ($params as $key=>$value) {
            if ($first) {
                $new_url = $new_url . $key . "=" . $value;
            } else {
                $new_url = $new_url . "&" . $key . "=" . $value;
            }
        }
        return $new_url;
    }
}

// TODO:  Maybe need to send in a request id instead of just sender id.
if (! function_exists('encode_url')) {
    function encode_url($url, $array) {
        $new_url = $url;
        if (strpos($url, '?')) {
            $new_url = $new_url . "&sender_facebook_id=" . $GLOBALS['session']->fb_uid;
        }
        else {
            $new_url = $new_url . "?sender_facebook_id=" . $GLOBALS['session']->fb_uid;
        }
        foreach ($array as $key=>$value) {
            $new_url = $new_url ."&".$key."=".$value;
        }
        return $new_url;
    }
}

if (! function_exists('load_html_content')) {
    function load_html_content($controller, $view_name, $data) {
        if (is_ajax()) {
            $controller->load->view($view_name, $data);
	    } else {
	        $data['page_content'] = $view_name . '.php';
    	    $controller->load->view('home', $data);
	    }
    }
}

if (! function_exists('get_current_player')) {
    function get_current_player() {
        if (isset($GLOBALS['myPlayer'])) {
            return $GLOBALS['myPlayer'];
        }
        else {
            $fb_uid = $GLOBALS['session']->fb_uid;
            $CI = & get_instance();
            $CI->load->model('player/PlayerModel', '', FALSE);
            $myPlayer = $CI->PlayerModel->get_by_session($session);
            $GLOBALS['myPlayer'] = $myPlayer;
            return $myPlayer;
        }
    }
}



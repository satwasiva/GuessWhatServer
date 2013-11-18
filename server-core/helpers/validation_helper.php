<?php
if (! function_exists('is_safe'))
{
    function is_safe($string) {
        $CI = & get_instance();
        $CI->load->model('badwords/BadWordsRegexModel');
        $active_regexes = $CI->BadWordsRegexModel->load_all(false);
        $data = array();
        foreach($active_regexes as $active_regex){
            array_push($data, $active_regex->regex);
        }

        foreach ($data as $line) {
            //debug(__FILE__, trim($line) . ' : ' . trim($string));
            if (preg_match('/'.trim($line).'/i', trim($string))) {
                return false;
            }
        }
        return true;
    }
}

if (! function_exists('make_list_safe'))
{
    function make_list_safe($list, $replace_string = '******') {
        $CI = & get_instance();
        $CI->load->model('badwords/BadWordsRegexModel');
        $active_regexes = $CI->BadWordsRegexModel->load_all(false);
        $data = array();
        foreach($active_regexes as $active_regex){
            array_push($data, $active_regex->regex);
        }
        $string = implode("|&", $list);
        $to_return = $string;
        foreach ($data as $line) {
            $to_return = preg_replace('/'.trim($line).'/i', $replace_string, $to_return);
        }
        $ret_val = explode("|&", $to_return);
        return $ret_val;
    }
}

if (! function_exists('make_safe'))
{
    function make_safe($string, $replace_string = '******') {
        $CI = & get_instance();
        $CI->load->model('badwords/BadWordsRegexModel');
        $active_regexes = $CI->BadWordsRegexModel->load_all(false);
        $data = array();
        foreach($active_regexes as $active_regex){
            array_push($data, $active_regex->regex);
        }

        $to_return = $string;
        foreach ($data as $line) {
            //debug(__FILE__, trim($line) . ' : ' . trim($string));
            $to_return = preg_replace('/'.trim($line).'/i', $replace_string, trim($to_return));
        }
        return $to_return;
    }
}

if (!function_exists('is_admin_user'))
{
    function is_admin_user($session)
    {
        require_once(APPPATH . 'config/game_config.php');

        if (strcmp(FB_ADMINS, "0") == 0)
        {
            $is_prod_or_staging = ENVIRONMENT == "prod" || ENVIRONMENT == "staging";
            return !$is_prod_or_staging;
        }

        $allowed_ids = explode(',', FB_ADMINS);

        return in_array($session->fb_uid, $allowed_ids);
    }
}

if (!function_exists('is_authorized_user')) {
    function is_authorized_user($session) {
        require_once(APPPATH . 'config/game_config.php');
        if (strcmp(FB_WHITELIST, "0")==0) {
            return true;
        }
        $allowed_ids = explode(',',FB_WHITELIST);
        if (in_array($session->fb_uid, $allowed_ids)) {
            return true;
        }
        return false;
    }
}

if (!function_exists('urlsafe_b64encode')) {
    function urlsafe_b64encode($string) {
        $data = base64_encode($string);
        $data = str_replace(array('+','/','='),array('-','_',''),$data);
        return $data;
    }
}

if (!function_exists('urlsafe_b64decode')) {
    function urlsafe_b64decode($string) {
        $data = str_replace(array('-','_'),array('+','/'),$string);
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= substr('====', $mod4);
        }
        return base64_decode($data);
    }    
}

if (!function_exists('cc_encrypt')) {
    function cc_encrypt($string) {
        require_once(APPPATH . 'config/game_config.php');
        return urlsafe_b64encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5(SECRET_KEY), $string, MCRYPT_MODE_CBC, md5(md5(SECRET_KEY))));
    }
}

if (!function_exists('cc_decrypt')) {
    function cc_decrypt($string) {
        require_once(APPPATH . 'config/game_config.php');
        return rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5(SECRET_KEY), urlsafe_b64decode($string), MCRYPT_MODE_CBC, md5(md5(SECRET_KEY))), "\0");
    }
}

<?php
require_once(COREPATH . 'libraries/error_handler.php');

class BaseController extends Controller {

    public function __construct()
	{
		parent::__construct();
        $this->load->helper('validation_helper');
        $this->load->helper('html_helper');
        $this->load->helper('session_helper');
        $this->load->helper('stats_helper');
        
        $last_handler = set_error_handler('handle_error');

        $this->log_request_entry();
        $entry_source = get_entry_source($_REQUEST);
        if (isset($_REQUEST['post_install'])) {
            
            debug(__FILE__, "entry source:" . $entry_source);
	        if (!is_null($entry_source) && strpos($entry_source, "ct_ingamepromo") !== false)
	    	{
	    		debug(__FILE__, "Setting ct pixel after install.");
	    		$GLOBALS['ct'] = true;
	    	}
        }
    	if (!is_null($entry_source) && strpos($entry_source, "mc_bannerpromo") !== false)
    	{
    		debug(__FILE__, "Setting mc pixel after install.");
    		$GLOBALS['mc'] = true;
    	}
    }

    protected function log_request_entry() {
        $logstr = 'REQLOG';
        $should_log = false;
        if (isset($_REQUEST['entry'])) {
            $logstr = $logstr . ',  ENTSRC: ' . $_REQUEST['entry'];
            $should_log = true;
        }
        if (isset($_REQUEST['ref'])) {
            $logstr = $logstr . ',  REF: ' . $_REQUEST['ref'];
            $should_log = true;
        }
        if (isset($_REQUEST['installed'])) {
            $logstr = $logstr . ',  INSTALLED: ' . $_REQUEST['installed'];
            if ($_REQUEST['installed'] == '1') {
                $should_log = true;
            }
        }
        if (isset($_REQUEST['post_install'])) {
            $logstr = $logstr . ',  FB_JUST_ADDED: ' . $_REQUEST['post_install'];
        }
        if (isset($_REQUEST['fb_sig_user'])) {
            $logstr = $logstr . ',  USER: ' . $_REQUEST['fb_sig_user'];
        }
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $logstr = $logstr . ',  IP: ' . $_SERVER['REMOTE_ADDR'];
        }
        if (isset($_SERVER['PATH_INFO'])) {
            $logstr = $logstr . ',  PATH: ' . $_SERVER['PATH_INFO'];
        }
        if (isset($_REQUEST['count'])) {
            $count = $_REQUEST['count'];
            $logstr = $logstr . ',  COUNT=' . $count;
        }
        if ($should_log) {
            debug(__FILE__, $logstr);
        }
    }

}
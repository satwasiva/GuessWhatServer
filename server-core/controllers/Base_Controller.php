<?php

require COREPATH.'/controllers/REST_Controller.php';

class Base_Controller extends REST_Controller
{
	/**
	 * Constructor function
	 * @todo Document more please.
	 */
	public function __construct()
	{
		parent::__construct();
        $this->load->helper('validation_helper');
        $this->load->helper('html_helper');
        $this->load->helper('session_helper');
        $this->load->helper('stats_helper');
        $this->load->helper('service_helper');
        $this->load->helper('net_helper');
		$this->load->helper('global_helper');
		$this->load->helper('log_helper');
        
        //$last_handler = set_error_handler('handle_error');

        $this->log_request_entry();
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

?>
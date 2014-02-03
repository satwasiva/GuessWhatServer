<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class CurlTest extends CI_Controller {

	/**
	 * Index Page for this controller.
	 *
	 * Maps to the following URL
	 * 		http://example.com/index.php/welcome
	 *	- or -  
	 * 		http://example.com/index.php/welcome/index
	 *	- or -
	 * Since this controller is set as the default controller in 
	 * config/routes.php, it's displayed at http://example.com/
	 *
	 * So any other public methods not prefixed with an underscore will
	 * map to /index.php/welcome/<method_name>
	 * @see http://codeigniter.com/user_guide/general/urls.html
	 */
	public function index()
	{  
		$username = 'admin';  
		$password = '1234';  
		 
		$client_identifier = array(
			'device_idfv'	=> '976789a56443dgfdgfgrty56565767',
			'app_uuid'		=> '34567801234'
		);
		
		$client_metadata = array(
			'client_version' 	=> '1.0.0',
			'device_type'		=> 'iPhone',
			'os'				=> 'iOS',
			'os_version'		=> '6.1',
			'ios_version'		=> '6.1',
			'data_connection_type'=>'http',
			'client_static_table_data'=>array('test'=>'none'),
			'game_data_version'	=> '1.0.1.1',
			'game_data_md5'		=> 'D813A774F42DE8E623FCB0BEFF83F9AF',
			'client_build'		=> '500',
			'previous_client_version'=>'1.0.0',
			'transaction_time'	=> '1367890346',
			'session_id'		=> '1',
			'load_source'		=> 'client_test_url',
			'assets_loaded_level'=>'1.0',
			'seconds_from_gmt'	=> '1367890346',
			'game_name'			=> 'guess what'
		);
		
		$this->load->library('curl');  
		  
		$this->curl->create('http://localhost/guesswhat/index.php/authenticater/format/json');
		$this->curl->post(array(  
			'client_identifier' => $client_identifier,  
			'client_metadata' => $client_metadata  
		));
		$result = $this->curl->execute();
  
		if($result !== null)  
		{
			echo 'User has been updated.';
			$result_arr = json_decode($result);
			echo $result;
		}
		else
		{
			echo 'Something has gone wrong';  
		}
	}
}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */
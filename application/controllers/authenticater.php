<?php

require_once(COREPATH . 'controllers/Base_Controller.php');

class Authenticater extends Base_Controller
{
	/**
	 * Constructor function
	 * @todo Document more please.
	 */
	public function __construct()
	{
		parent::__construct();
        $this->load->model('player/PlayerModel', '', FALSE);
        $this->load->model('user/UserModel','',FALSE);
		
        $this->load->helper('player_helper');
        $this->load->helper('data_helper');
	}
		
	function post()
	{
		$data = $this->_post_args;
		$client_identifier = $data['client_identifier'];
		$client_metadata = $data['client_metadata'];
		
		$idfv = $client_identifier['device_idfv'];
		$app_uuid = $client_identifier['app_uuid'];
		
        $client_version = $client_metadata['client_version'];
        if (array_key_exists('device_type' , $client_metadata)){
            $device_type = $client_metadata['device_type'];
        } else {
            $device_type = 'unknown';
            debug(__FILE__, "Device Type Unknown!" . print_r($client_metadata, true) . " ClientIden: " . print_r($client_identifier, true));
        }

        $os_type = array_key_exists('os' , $client_metadata) ? $client_metadata['os'] : IOS;
        define_platform($os_type);
        $os_version = array_key_exists('os_version' , $client_metadata) ? $client_metadata['os_version'] : '';
        $ios_version = $client_metadata['ios_version'];
        $data_connection_type = $client_metadata['data_connection_type'];
        $game_data_version = $client_metadata['game_data_version'];
        $game_data_md5 = $client_metadata['game_data_md5'];
        $client_build = $client_metadata['client_build'];
        $previous_client_version = $client_metadata['previous_client_version'];
        $transaction_time = $client_metadata['transaction_time'];
        $session_id = $client_metadata['session_id'];
        $load_source = $client_metadata['load_source'];
        $assets_loaded_level = $client_metadata['assets_loaded_level'];
        $seconds_from_gmt = $client_metadata['seconds_from_gmt'];
        $game_name = $client_metadata['game_name'];
		
        $this->load->model('user/UserModel');
        $this->load->model('player/PlayerModel');

        if (is_null($idfv)) {
            warn(__FILE__, "Invalid iphone idfv during authentication.", WarnTypes::STARTUP_FAILURE);
            return $this->response(array('AUTH_STATUS' => 'INVALID_IDFV'), 404);
        }

        // Check user database is down
        if (check_user_master_is_down()) {
			return $this->response(get_game_down_response(), 404);
        }
		
        //Normal Authentication Procedure
        $is_new_session = false;
        $stored_session = get_iphone_stored_session($idfv, $app_uuid);
        if (is_null($stored_session)) {
            $GLOBALS['session'] = create_new_iphone_session($idfv, $app_uuid, $game_data_version);
            debug(__FILE__, 'IOS_SESSION - Creating new session' . print_r($GLOBALS['session'], true));
            $is_new_session = true;
        } else {
            debug(__FILE__, 'IOS_SESSION - Using stored session');
            $GLOBALS['session'] = $stored_session;
            debug(__FILE__, 'IOS_SESSION - Getting from stored session' . print_r($GLOBALS['session'], true));
        }

        // Create new player if they don't exist
        try{
            $myPlayer = $this->UserModel->get_or_create_player($idfv, $load_source,
                        $client_version, $device_type, $ios_version,
                        $data_connection_type, $app_uuid,
                        $client_build, $seconds_from_gmt, $os_type);
        }catch(Exception $exception){
            error(__FILE__, "CREATE PLAYER FAILED. ID COLLISION 5 TIMES. This should not happen. Message:" . $exception->getMessage());
			return $this->response(array('success'=> false, 'reason' => 'CREATE ID FAILED BECAUSE OF COLLISIONS'), 404);
        }

        // Check if the game is available to the player
        if (check_is_game_down($myPlayer)) {
			return $this->response($this->get_game_down_response(), 404);
        }

        if (isset($myPlayer->is_banned) && $myPlayer->is_banned) {
            info(__FILE__, "User has been banned.  idfv = " . $idfv);
            $myPlayer->get_updated_client_obj_to_return();
            $global_response['metadata']['player'] = $myPlayer;
            $global_response['AUTH_STATUS'] = 'BANNED';
			return $this->response($global_response, 404);
        }
		
        $GLOBALS['session']->client_version = $client_version;
        $GLOBALS['session']->game_name = $game_name;
        $GLOBALS['session']->seconds_from_gmt = $seconds_from_gmt;
        $GLOBALS['session']->game_data_version = $game_data_version;
        $GLOBALS['session']->game_data_md5 = $game_data_md5;
        $GLOBALS['session']->transaction_time = $transaction_time;
        $GLOBALS['session']->client_build = $client_build;
        $GLOBALS['session']->session_id = $session_id;
        $GLOBALS['session']->previous_client_version = $previous_client_version;
        $GLOBALS['session']->app_uuid = $app_uuid;

        $session = $GLOBALS['session'];

		$response = array();
		$response = $this->load();
		$response['session'] = $session;
		
        //Record a player login everytime authenticate_iphone is called.
        //record_player_login($myPlayer, $load_source, $device_type, $ios_version, $data_connection_type, $client_version, $client_build, $client_static_table_data["using"]);
		
		$response['success'] = true;
		return $this->response($response, 200);
	}
	
    public function load($session, $client_static_table_data=NULL, $assets_load_level=0, $device_type=NULL, $ios_version=NULL, $client_version=NULL)
    {
        $myPlayer = $this->PlayerModel->get_by_session($session);
        $user = $this->UserModel->get_by_session($session);

        if ($myPlayer->num_game_loads == 0 && ENVIRONMENT != "dev") {
            //send_ad_installs($user, $device_type, $ios_version, $session);
        }

        $last_game_load_time = date('Y-m-d H:i:s');
        $myPlayer->last_game_load_time = $last_game_load_time;
        $myPlayer->num_game_loads += 1;

        //set_ab_test_group($myPlayer, $client_version);

        //PushNotificationClient::update_player_notifications_on_game_load($myPlayer->id, $session->seconds_from_gmt);

        $this->PlayerModel->save($myPlayer);

        $commerce_products = $this->CommerceProductModel->load_all($myPlayer);
        $asset_type_md5s = $this->AssetTypeMd5Model->load_all();
        $asset_type_md5s = json_encode($asset_type_md5s);

        //TODO - kjs - Not sure if we are using this or if we should!
        $assets_level_to_background_load = 0;
        if(is_numeric($assets_load_level) && $myPlayer->level >= 4)  {
            $assets_level_to_background_load = $assets_load_level + 5;
        }

        //TODO - kjs - Not sure if we are setting up or using this correctly!
        $static_data_to_load = CURRENT_STATIC_DATA_TO_LOAD;
        debug(__FILE__, "Static data: ".CURRENT_STATIC_DATA_TO_LOAD." - ".$static_data_to_load.", client_version: ".$client_version);
        debug(__FILE__, "PLAYER_DUMP: ".json_encode($myPlayer));

		date_default_timezone_set(date_default_timezone_get());
		
        $shared_game_properties = SharedGameProperties::get_instance();
        //$shared_game_properties->user_sale_discount = $this->PlayerCommerceSaleModel->get_sale_discount($myPlayer);
        $shared_game_properties->server_time = time();
        $shared_game_properties->server_time_offset = (int)date('Z');
        $shared_game_properties_md5 = get_md5_for_shared_properties($shared_game_properties);

        //$game_data_changes = $this->get_game_data_changes_for_ab_test($myPlayer, $client_version);
        $game_data_changes = json_encode($game_data_changes);
        $game_data_changes_md5 = md5($game_data_changes . "asdfgpoirtyu_!@#*&^#%_vam$I");

        $popup = NULL;
        /*if(is_null($popup)) {
            $popup = get_xpromo_popup($myPlayer->id, $client_version, $device_type);
        }*/
        if (is_null($popup)) {
            //$popup = get_client_new_content_popup($myPlayer, $session, $client_static_table_data['active'], $device_type);
        }

       /*  if (isset($GLOBALS['sale_packages']) && $GLOBALS['sale_packages'] == true) {
            $sale_end_date = $this->PlayerCommerceSaleModel->get_sale_end_date($myPlayer);
            $updated_unix_ts = strtotime($sale_end_date) - ((int)date('Z') - $myPlayer->seconds_from_gmt);
            $sale_end_date = date('Y-m-d H:i:s', $updated_unix_ts);
            $displaying_commerce_packages = true;
        } else {
            $displaying_commerce_packages = false;
            $sale_end_date = null;
        } */

        //$startup_popups = get_startup_popups($myPlayer->id, $session->game_data_version, $displaying_commerce_packages);

        $result_array = array(
            'asset_type_md5s' => $asset_type_md5s,
            'assets_load_level' => $assets_level_to_background_load,
            'cdn_url' => CDN_URL,
            'commerce_products' => $commerce_products,
            'game_data_changes' => $game_data_changes,
            'game_data_changes_md5' => $game_data_changes_md5,
            'shared_game_properties' => $shared_game_properties,
            'shared_game_properties_md5' => $shared_game_properties_md5,
            //'sale_end_date' => $sale_end_date,
            //'startup_popup_list' => $startup_popups,
            'show_promo' => false,
            'popup' => $popup,
            'static_data_to_load' => $static_data_to_load,
            'user' => $user
        );
		
        return $result_array;
	}
}
?>
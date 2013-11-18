<?php

require_once(COREPATH . 'controllers/Base_Controller.php');

class Game_Loader extends Base_Controller
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

        $startup_popups = get_startup_popups($myPlayer->id, $session->game_data_version, $displaying_commerce_packages);

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
            'startup_popup_list' => $startup_popups,
            'show_promo' => false,
            'popup' => $popup,
            'static_data_to_load' => $static_data_to_load,
            'user' => $user
        );
		
        return $result_array;
	}
}
?>
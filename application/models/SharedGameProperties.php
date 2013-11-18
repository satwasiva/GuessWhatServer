<?php
require_once(COREPATH . 'models/baseentity.php');

class SharedGameProperties extends BaseEntity {
	public $_explicitType = 'SharedGameProperties';

	public static $instance;

    public $initial_points = 1000.00001;
    public $ios_user_sale_active = 0;
    public $ios_sale_product_group = 'sale';
    public $ios_user_sale_start_date = '2012-11-29 12:00:00';
    public $ios_user_sale_end_date = '2012-12-02 11:59:59';
    public $ios_is_targeted_sale_active = 0;
    public $ios_targeted_sale_start_date = '2012-09-21 12:00:00';
    public $ios_targeted_sale_end_date = '2012-09-21 12:00:00';
    public $ios_targeted_sale_duration_hours = 24;
    public $ios_targeted_sale_min_level = 10;
    public $ios_is_targeted_sale_nonspenders_only = 0;
    public $sending_time_cutoff_hours = 24;
    public $pending_time_cutoff_hours = 720;
    public $redeem_time_cutoff_hours = 24;
    public $accept_time_cutoff_hours = 24;
    public $sending_threshold_count = 1;
    public $pending_threshold_count = 1;
    public $redeem_threshold_count = 8;
    public $accept_threshold_count = 1;
	
    public $num_points_given_at_level_up = 3;
    public $max_level = 200;
    public $currency_type = 'points';
    public $type_avatar = 'avatar';
    public $apple_store_url = 'http://itunes.apple.com/app/slotzio/id532623192';
    public $help_url = '';
    public $background_asset_loader_secs_between = 1.5;
    public $max_network_download_per_time_period = 700;
    public $network_download_sleep_period = 30;
    public $retina_on = 1;
    public $prop_directory = '24props_80';
    public $user_sale_discount = 20;
    public $show_commerce_price = TRUE;
    public $log_server_errors_to_analytics = TRUE;
    public $special_bonus_multiplier = 2;
    public $local_notifications_on = TRUE;
    public $ios_sales_title = '30% OFF!';
    public $ios_sales_subtitle = 'LIMITED TIME ONLY!';
    public $ios_sales_body = 'Save 30% or more when you buy coins';

	function SharedGameProperties() {
		parent::BaseEntity();
	}

	public static function get_instance() {
		if(is_null(self::$instance)) {
			self::$instance = new SharedGameProperties();
		}
		return self::$instance; 
	}
}

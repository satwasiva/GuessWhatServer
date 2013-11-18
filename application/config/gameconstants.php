<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

define('MD5_SECRET', "4a8e5f8cc316d727d7649a3f20819c41");
define('MD5_SEPARATOR', ':');

define('PHEANSTALK_SERVER_ERRORS', 'server_errors');
define('PHEANSTALK_CLIENT_ERRORS', 'client_errors');
define('PHEANSTALK_PUSH_NOTIFICATIONS', 'push_notifications');
define('PHEANSTALK_FACEBOOK_REQUESTS', 'facebook_requests');
define('PHEANSTALK_SERVICES_PERFORMANCE', 'service_performance');
define('MONGO_SERVER_WARNS', 'server_warns');

define('WALL_TAGS', '<u><i><b><font>');
//define('MONGO_DB_NAME', 'ios_sl');

//Whether to log service's execution data
define('LOG_SERVICE_PERFORMANCE_DATA', 1);	//1 - To Log Data
define('SERVICE_PERFORMANCE_RAND_NUM', 1);	//Does SERVICES_PERFORMANCE_RAND_NUM % of sampling i.e. SERVICES_PERFORMANCE_RAND_NUM=40 does 40% of sampling.

// Facebook constants
define('FACEBOOK_APP_ID', '382768148462804');
define('FACEBOOK_APP_SECRET', 'f951a4a31728804b2aae6da39f59ed32');

// iap package mapping
define('APP_STORE_KEY_SUBSTRING_JPS_OLD', 'com.prakhya.guesswhat');
define('APP_STORE_KEY_SUBSTRING_SW', 'com.funzio.');
define('APP_STORE_KEY_SUBSTRING_JPS_NEW', 'com.funzio.jackpot');
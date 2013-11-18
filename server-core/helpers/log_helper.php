<?php
require_once(COREPATH . 'libraries/KLogger.php');
//require_once(COREPATH . 'libraries/error_handler.php');
require_once(APPPATH . 'config/game_config.php');
require_once(COREPATH . 'helpers/core_log_helper.php');

class WarnTypes extends CoreWarnTypes {

    const API_OUTDATED = "API_OUTDATED";
    const BUY_WARN = "BUY_WARN";
    const DISPATCHER_WARN = "DISPATCHER_WARN";
    const SERVICE_ERROR = "SERVICE_ERROR";
    const HASH_AUTH_FAILED = "HASH_AUTH_FAILED";
    const INVALID_SEQUENCE_NUM = "INVALID_SEQUENCE_NUM";
    const MD5_WARN = "MD5_WARN";
    const OTHER = "OTHER";
    const STARTUP_FAILURE = "STARTUP_FAILURE";
    const FUNZIO_ID_WARN = "FUNZIO_ID_WARN";
    const TILE_EMPTY_BUG = "TILE_EMPTY_BUG";
    const PHEANSTALK_ERROR = "PHEANSTALK_ERROR";
    const MEMCACHE_ERROR = "MEMCACHED_ERROR";
    const CLIENT_CRASH = "CLIENT_CRASH";
    const SESSION_INVALID = "SESSION_INVALID";
    const GAME_DOWN = "GAME_DOWN";
    const INVALID_PLAYER = "INVALID_PLAYER";
    const LAST_UPDATED_VALUES = "LAST_UPDATE_VALUES";
    const SERVICE_WARN = "SERVICE_WARN";
    const HELPER_WARN = "HELPER_WARN";
    const SESSION_WARN = "SESSION_WARN";
    const DB_FRAMEWORK_WARN = "DB_FRAMEWORK_WARN";
    const OBJECT_STALE_SINGLEDB = "OBJECT_STALE_SINGLEDB";
    const NULL_OUTFIT = "NULL_OUTFIT";
    const ID_COLLISION = "ID_COLLISION";
    const NIL_TILE = "NIL_TILE";
    const PVE_ATTACK_WARN = "PVE_ATTACK_WARN";
    const AREA_LOAD_WARN = "AREA_LOAD_WARN";
    const ANALYTICS_WARN = "ANALYTICS_WARN";
    const APC_WARN = "APC_WARN";
    const GEO_IP_WARN = "GEO_IP_WARN";
    const BATTLE_WARN = "BATTLE_WARN";
    const NODE_MYSQL_WARN = "NODE_MYSQL_WARN";
    const JSON_MEMCACHE_ERROR = "JSON_MEMCACHE_ERROR";
    const IN_APP_PAYMENT = "IN_APP_PAYMENT";
    const NEGATIVE_BET = "NEGATIVE_BET";
    const INVALID_MACHINE_ID = "INVALID_MACHINE_ID";
}


if (!defined('log_helper')) {
    define('log_helper', TRUE);

    function stat_log($line) {
        $date = date('Y-m-d');
        $logger = SimpleLogger::instance(LEADERBOARD_LOG_DIRECTORY.'/analytics-' . $date . '.log', 1);
        $logger->writeFreeFormLine($line, false);
    }

    function stat_log_experience($player_id, $iphone_udid, $action, $action_detail, $level, $xp_change, $xp_level_delta) {
        $date = date('Y-m-d H:i:s');
        $time = time();
        stat_log('level_v1' . ' ' . $date . ' ' . $time . ' ' . $player_id . ' ' . $iphone_udid . ' ' . $action . ' ' . $action_detail . ' ' . $level . ' ' . $xp_change . ' ' . $xp_level_delta ."\n");
    }

}

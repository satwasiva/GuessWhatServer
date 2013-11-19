<?php
require_once(COREPATH . 'libraries/KLogger.php');
require_once(APPPATH . 'config/game_config.php');

class CoreWarnTypes {

    // alphabetize
    const ANALYTICS_WARN = "ANALYTICS_WARN";
    const APC_WARN = "APC_WARN";
    const API_OUTDATED = "API_OUTDATED";
    const AREA_LOAD_WARN = "AREA_LOAD_WARN";
    const BATTLE_WARN = "BATTLE_WARN";
    const BUY_WARN = "BUY_WARN";
    const CLIENT_CRASH = "CLIENT_CRASH";
    const DB_FRAMEWORK_WARN = "DB_FRAMEWORK_WARN";
    const FUNZIO_ID_WARN = "FUNZIO_ID_WARN";
    const GAME_DOWN = "GAME_DOWN";
    const GEO_IP_WARN = "GEO_IP_WARN";
    const HASH_AUTH_FAILED = "HASH_AUTH_FAILED";
    const HELPER_WARN = "HELPER_WARN";
    const ID_COLLISION = "ID_COLLISION";
    const INVALID_SEQUENCE_NUM = "INVALID_SEQUENCE_NUM";
    const JSON_MEMCACHE_ERROR = "JSON_MEMCACHE_ERROR";
    const LAST_UPDATED_VALUES = "LAST_UPDATE_VALUES";
    const MD5_WARN = "MD5_WARN";
    const MEMCACHE_ERROR = "MEMCACHED_ERROR";
    const MODEL_WARN = "MODEL_WARN";
    const MONGO_WARN = "MONGO_WARN";
    const NIL_TILE = "NIL_TILE";
    const NODE_MYSQL_WARN = "NODE_MYSQL_WARN";
    const NULL_OUTFIT = "NULL_OUTFIT";
    const OBJECT_STALE_SINGLEDB = "OBJECT_STALE_SINGLEDB";
    const OTHER = "OTHER";
    const PHEANSTALK_ERROR = "PHEANSTALK_ERROR";
    const PVE_ATTACK_WARN = "PVE_ATTACK_WARN";
    const SERVICE_ERROR = "SERVICE_ERROR";
    const SERVICE_WARN = "SERVICE_WARN";
    const SESSION_INVALID = "SESSION_INVALID";
    const SESSION_WARN = "SESSION_WARN";
    const STALE_ERROR = "STALE_ERROR";
    const STARTUP_FAILURE = "STARTUP_FAILURE";
    const TILE_EMPTY_BUG = "TILE_EMPTY_BUG";
}

const EXCEPTION_SEVERITY = 7;

if (!defined('core_log_helper')) {
    define('core_log_helper', TRUE);

    function info($file, $message) {
        $logger = KLogger::instance(LOG_DIRECTORY, 1);
        $file_segments = explode('/', $file);
        $logger->logInfo(end($file_segments) . ' - ' . $message);
    }

    function debug($file, $message) {
        $logger = KLogger::instance(LOG_DIRECTORY, 1);
        $file_segments = explode('/', $file);
        $logger->logDebug(end($file_segments) . ' - ' . $message);
    }

    function warn($file, $message, $warn_type=WarnTypes::OTHER) {
        $logger = KLogger::instance(LOG_DIRECTORY, 1);
        $file_segments = explode('/', $file);
        $logger->logWarn(end($file_segments) . ' - ' . $message);
        //send_server_warn_to_analytics($file, $message, $warn_type);
    }

    function error($file, $message, $is_from_error_handler = false) {

        $logger = KLogger::instance(LOG_DIRECTORY, 1);
        $file_segments = explode('/', $file);

        try {
            throw new Exception();
        } catch (Exception $e) {
            $error_message = (end($file_segments) . ' - ' . $message);
            $stack_trace = format_stack_trace($error_message, $e);
            $logger->logError($stack_trace);

            // If called from error handler then get the line number from message otherwise from the stack trace.
            $line_no = 0;
            if ($is_from_error_handler) {
                $message_arr = explode(":", $message);
                $line_no = trim(end($message_arr));
                $new_message_arr = explode("file =", $message);
                $message = trim(trim($new_message_arr[0]), ",");
            }
            else {
                $stack_trace_array = explode("\n", $stack_trace);
                // Grab the line number from the stack trace string.
                $line_no = substr($stack_trace_array[1], strpos($stack_trace_array[1], "(") + 1, ( strpos($stack_trace_array[1], ")") - strpos($stack_trace_array[1], "(") - 1 ) );
            }

            //send_server_error_to_analytics($file, $line_no, $message, get_server_error_type($message));
            //output the stack when testing on a browser and make it easier to read the error stack
            if (ENVIRONMENT == 'dev' && isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Mozilla') !== FALSE) {
                echo "<br/>ERROR: " . $message;
                echo str_replace("\n", "<br/>", $e->getTraceAsString());
            }
        }
    }

    function format_stack_trace($error_message, $e) {
        return $error_message . "\n\t" . str_replace("\n", "\n\t", $e->getTraceAsString());
    }

    function fatal($file, $message) {
        $logger = KLogger::instance(LOG_DIRECTORY, 1);
        $file_segments = explode('/', $file);
        $logger->logFatal(end($file_segments) . ' - ' . $message);
    }

    function debug_obj($file, $obj) {
        $a = json_encode($obj);
        $logger = KLogger::instance(LOG_DIRECTORY, 1);
        $file_segments = explode('/', $file);
        $logger->logDebug(end($file_segments) . ' - ' . $a);
    }

    function exception($file, $e) {
        handle_error(EXCEPTION_SEVERITY, $e->getMessage(), $e->getFile(), $e->getLine());
        error($file, $e->getTraceAsString());
    }
}

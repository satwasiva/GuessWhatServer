<?php
require_once(dirname(__FILE__) .'/DbConnWrapper.php');

class DbConnManager {

    private static $_DB_CONNS = array();
    static $_VERBOSE_QUERY_LOGGING = 0;

    public static function get_db_conn($db_name) {
        $db = NULL;
        if (array_key_exists($db_name, self::$_DB_CONNS)) {
//            debug(__FILE__, "Re-using db connection: " . $db_name);
            $db = self::$_DB_CONNS[$db_name];
        } else {
        	require_once(COREPATH . 'database/DbConnStat.php');
            require_once(APPPATH . 'config/database.php');

            $db_stat = DbConnStat::get_instance();
            if (!DB_FAILOVER && isset($GLOBALS['db_config'][$db_name]) && ($db_info = $GLOBALS['db_config'][$db_name]) && isset($db_info['database']) && $db_stat->is_db_down($db_info['database'])) {
                throw new DbException('db_marked_down db is marked as down: ' . $db_info['database']);
            }

            $database_name = $db_info['database'];
		error_log("conn config ".$db_name);
            $CI = & get_instance();
            $db = $CI->load->database($db_name, TRUE);
            $db_info = $GLOBALS['db_config'][$db_name];

		error_log("conn config done:".$database_name);
            //append slave to distinguish the name
            if (strpos($db_name, 'slave') !== FALSE) {
                $database_name = $database_name . '_slave';
            }

            if (self::$_VERBOSE_QUERY_LOGGING) {
                $logger = KLogger::instance(LOG_DIRECTORY.'/verbose-query.log', 1);
                $line = $db->database .":". 'establishing_db_connection';
                $logger->logInfo($line);
            }

            //Mark the host as down
            if (!DB_FAILOVER && !$db->conn_id) {
                $error_message = $db->_error_message();
                $error_no = $db->_error_number();
                error(__FILE__, "Database connection error no: " . $error_no . " error msg: $error_message database: " . $db->database);
                $db_stat->increment_host_error($db->database, 1);
                require_once(COREPATH . 'application/database/DbConnWrapper.php');
                throw new DbException('DB connection manager cannot connect to database: ' . $db->database);
            }

            //try to initialize if db is not marked as down
            if (DB_FAILOVER && !$db_stat->is_db_down($database_name)) {
                $db->initialize();
                if (!$db->conn_id) {
                    $error_message = $db->_error_message();
                    $error_no = $db->_error_number();
                    warn(__FILE__, "Database connection error no: " . $error_no . " error msg: $error_message database: " . $database_name . ' config: ' . $db_name, WarnTypes::DB_FRAMEWORK_WARN);
                    $db_stat->increment_host_error($database_name, 1);
                }
            }

            self::$_DB_CONNS[$db_name] = $db;
        }

        return new DbConnWrapper($db);
    }

    /**
     *
     * Get the slave database connection for the database connection
     * @param unknown_type $db_name
     */
    public static function get_slave_conn($db_name) {
    	require_once(APPPATH . 'config/database.php');

    	$slave_name = '';
    	if (strpos($db_name, 'slave') !== FALSE) {
    		$slave_name = $db_name;
    	} else if (strpos($db_name, 'shard_') !== FALSE) {
    		$slave_name = 'shard_slave_' . substr($db_name, 6);
    	} else {
    		$slave_name = $db_name . '_slave';
    	}

    	if (isset($GLOBALS['db_config'][$slave_name])) {
    		return self::get_db_conn($slave_name);
    	} else {
    		warn(__FILE__, 'invalid slave db connection for: ' . $db_name, WarnTypes::DB_FRAMEWORK_WARN);
    	}

    	return null;
    }

    /* Sumit -
     * Only to be used for testing purposes in order to clear the db cache to force failures and test the failure handling.
    * Again, should NEVER be called from any other place.
    */
    public static function reset_db_conections(){
        self::$_DB_CONNS = array();
    }

}
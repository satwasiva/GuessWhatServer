<?php
//require_once(COREPATH . 'analytics/PerformanceLogger.php');
require_once(dirname(__FILE__) .'/DbConnStat.php');
require_once(COREPATH .'libraries/KLogger.php');

require_once(APPPATH .'config/game_config.php');

class DbException extends Exception {
    public $message;
    public $error_type;
    function DbException($msg) {
        $this->message = $msg;
        $error_str = strtolower($msg);
        if (stripos($error_str, 'duplicate') !== false) {
            $this->error_type = "duplicate";
        } else {
            $this->error_type = "unknown";
        }
    }
}

class DbConnWrapper {
    static $_VERBOSE_QUERY_LOGGING = 0;

    private $ci_db;
    private $insert_id = 0;

    static  $filter_fields = array(
                'ar_select'			=> 1,
                'ar_from'			=> 1,
                'ar_join'			=> 1,
                'ar_where'			=> 1,
                'ar_like'			=> 1,
                'ar_groupby'		=> 1,
                'ar_having'			=> 1,
                'ar_orderby'		=> 1,
                'ar_wherein'		=> 1,
                'ar_aliased_tables'	=> 1,
                'ar_distinct'		=> 1,
                'ar_limit'			=> 1,
                'ar_offset'			=> 1,
                'ar_order'			=> 1,
                'ar_set'            => 1,
        );

    public function DbConnWrapper($db) {
        $this->ci_db = $db;
    }

    public function get_db_name() {
        return $this->ci_db->database;
    }

    public function from($from) {
        $this->ci_db->from($from);
        return $this;
    }

    public function where($key, $value = NULL, $escape = TRUE) {
        $this->ci_db->where($key, $value, $escape);
        return $this;
    }

    public function limit($limit, $offset) {
        $this->ci_db->limit($limit, $offset);
        return $this;
    }

    private function log_query($stmt_type, $table, $extra) {
        if (self::$_VERBOSE_QUERY_LOGGING) {
            $logger = KLogger::instance(LOG_DIRECTORY.'/verbose-query.log', 1);
            $line = $this->get_db_name() .":". $stmt_type .":". $table .":". $extra;
            $logger->logInfo($line);
            debug(__FILE__, $line);
        }
    }

    public function where_in($key = NULL, $values = NULL) {
        $this->ci_db->where_in($key, $values);
        $this->log_query("where_in", "player", "NUM:".sizeof($values));
        return $this;
    }

    public function order_by($orderby, $direction = '') {
        $this->ci_db->order_by($orderby, $direction);
        return $this;
    }

    public function get($table = '', $limit = null, $offset = null) {
        if (!DB_FAILOVER) {
            //PerformanceLogger::start_query("get", $this->get_db_name(), $table);
            try {
                $result = $this->ci_db->get($table, $limit, $offset);
            } catch(DbException $exception) {
                error(__FILE__, 'DB wrapper caught exception: ' . $exception->getMessage());
            }

            $this->log_query("get", $table, "");
            $this->handle_error();
            //PerformanceLogger::end_query();
            return $result;
        }

        $db_stat = DbConnStat::get_instance();
        //PerformanceLogger::start_query("get", $this->get_db_name(), $table);
        $result = null;
        $error = false;
        $db_state = $this->get_db_state();
        try {
            if (!$db_stat->is_db_down($this->get_db_name())) {
                $result = $this->ci_db->get($table, $limit, $offset);
            }
        } catch(DbException $exception) {
            error(__FILE__, 'DB wrapper caught exception: ' . $exception->getMessage());
            $error = true;
        }

        $this->error_tracking();
        if (!$this->ci_db->conn_id || $db_stat->is_db_down($this->get_db_name())) {
            try {
                $slave_db = DbConnManager::get_slave_conn($this->ci_db->db_group);
                if ($slave_db) {
                    $this->initialize_db_connection($slave_db->ci_db, $db_state);
                    $result = $slave_db->ci_db->get($table, $limit, $offset);
                    $this->handle_error_message($slave_db->ci_db->_error_message());
                }

                if (!$result || $result->num_rows() == 0) {
                    if (!$result && $slave_db) {
                        debug(__FILE__, 'slave result is null: ' . print_r($slave_db->ci_db, true));
                    }
                    $backup_db = $this->get_backup_conn($db_state);
                    if ($backup_db) {
                        $result = $backup_db->ci_db->get($table, $limit, $offset);
                        $this->handle_error_message($backup_db->ci_db->_error_message());
                    }
                }
                $error = false;
            } catch (Exception $exception) {
                warn(__FILE__, 'error getting to data from backup db: ' . $this->get_db_name() . ' table: ' . $table . ' error' . $exception->getMessage(), WarnTypes::DB_FRAMEWORK_WARN);
                $this->handle_error_message($exception->getMessage());
                $error = true;
            }
        }
        if (!$result) {
            debug(__FILE__, "null result conn_id: " . $this->ci_db->conn_id . " db_name: " . $this->get_db_name()  . ' db_state: ' . print_r($db_state, true));
        }

        $this->log_query("get", $table, $offset);
        $this->ci_db->_reset_select();
        if ($error) {
            $this->handle_error();
        }
        //PerformanceLogger::end_query();
        return $result;
    }

    public function insert($table = '', $set = NULL) {
        if (!DB_FAILOVER) {
            //PerformanceLogger::start_query("insert", $this->get_db_name(), $table);
            try {
                $result = $this->ci_db->insert($table, $set);
            } catch(DbException $exception) {
                error(__FILE__, 'DB wrapper caught exception: ' . $exception->getMessage());
            }
            $this->log_query("insert", $table, "");
            $this->handle_error();
            //PerformanceLogger::end_query();
            return $result;
        }

        $db_stat = DbConnStat::get_instance();
        //PerformanceLogger::start_query("insert", $this->get_db_name(), $table);
        $result = false;
        $error = false;
        $this->insert_id = 0;  //init insert_id to 0
        try {
            if (!$db_stat->is_db_down($this->get_db_name())) {
                $result = $this->ci_db->insert($table, $set);
                $this->insert_id = $this->ci_db->insert_id();
            }
        } catch(DbException $exception) {
            error(__FILE__, 'DB wrapper caught exception: ' . $exception->getMessage());
            $error = true;
        }

        $this->error_tracking();
        if (!$this->ci_db->conn_id || $db_stat->is_db_down($this->get_db_name())) {
            $backup_db = $this->get_backup_conn();
            $aux_id = 0;
            if ($backup_db) {
                try {
                    if ($set && empty($set['id'])) {
                        require_once(APPPATH . 'models/auxiliary/auxiliarymodel.php');
                        $aux_id = AuxiliaryModel::get_or_create_id($backup_db->ci_db, $table);
                        $set['id'] = $aux_id;
                    }

                    if (isset($set['id']) && $set['id'] > 0) {
                        $result = $backup_db->ci_db->insert($table, $set);
                        $this->insert_id = $aux_id;
                        debug(__FILE__, sprintf("insert into backup db: %s table: %s id: %s", $this->get_db_name(), $table, $aux_id));
                        $error = false;
                    }
                } catch(Exception $ex) {
                    warn(__FILE__, 'attempting to insert backup database' . $this->get_db_name() . ' table: ' . $table . ' error: ' . $ex->getMessage(), WarnTypes::DB_FRAMEWORK_WARN);
                    $this->handle_error_message($ex->getMessage());
                }
                $this->handle_error_message($backup_db->ci_db->_error_message());
            }
        }
        $this->log_query("insert", $table, "");
        $this->ci_db->_reset_write();
        //PerformanceLogger::end_query();
        if ($error) {
            $this->handle_error();
        }
        return $result;
    }

    /**
     * Try to insert, if the id already exists then it will update instead of insert
     * @param $table
     * @param mixed $set
     * @param mixed $update_set if this is null, it is default to set. ex: array('id' => array('value' => 100, 'escape' => true), 'version' => array('value' => 'version + 1', 'escape' => false));
     * @return mixed
     */
    public function insert_or_update($table, $set = NULL, $update_set = NULL) {
        //PerformanceLogger::start_query("insert_or_update", $this->get_db_name(), $table);
        try {
            $result = $this->ci_db->insert_or_update($table, $set, $update_set);
        } catch(DbException $exception) {
            error(__FILE__, 'DB wrapper caught exception: ' . $exception->getMessage());
        }
        $this->log_query("insert_or_update", $table, "");
        $this->handle_error();
        //PerformanceLogger::end_query();
        return $result;
    }

    public function insert_id() {
        if (!DB_FAILOVER) {
            return $this->ci_db->insert_id();
        } else {
            return $this->insert_id;
        }
    }

    public function update($table = '', $set = NULL, $where = NULL, $limit = NULL) {
        if (!DB_FAILOVER) {
            //PerformanceLogger::start_query("update", $this->get_db_name(), $table);
            try {
                $result = $this->ci_db->update($table, $set, $where, $limit);
            } catch(DbException $exception) {
                error(__FILE__, 'DB wrapper caught exception: ' . $exception->getMessage());
            }
            $this->log_query("update", $table, $this->get_where_str($where));
            $this->handle_error();
            //PerformanceLogger::end_query();
            return $result;
        }
        //PerformanceLogger::start_query("update", $this->get_db_name(), $table);
        $db_stat = DbConnStat::get_instance();
        $result = false;
        try {
            if (!$db_stat->is_db_down($this->get_db_name())) {
                $result = $this->ci_db->update($table, $set, $where, $limit);
            }
        } catch(DbException $exception) {
            error(__FILE__, 'DB wrapper caught exception: ' . $exception->getMessage());
        }

        $this->error_tracking();
        if (!$this->ci_db->conn_id || $db_stat->is_db_down($this->get_db_name())) {
            try {
                //try to update, the record probably is not there
                $backup_db = $this->get_backup_conn();
                if ($backup_db) {
                    //update might not succeed due to where condition like version check
                    $result = $backup_db->ci_db->update($table, $set, $where, $limit);
                    debug(__FILE__, sprintf("attempt to update backup db: %s table: %s count: %s", $this->get_db_name(), $table, $backup_db->ci_db->affected_rows()));
                    //attempt to insert, ignore insert failure on duplicate key
                    if ($result !== true || $backup_db->ci_db->affected_rows() == 0) {
                        $result = $backup_db->ci_db->insert($table, $set);
                        debug(__FILE__, sprintf("attempt to insert into backup db: %s table: %s count: %s", $this->get_db_name(), $table, $backup_db->ci_db->affected_rows()));
                    }
                    $this->handle_error_message($backup_db->ci_db->_error_message());
                }
            }
            catch (Exception $exception) {
                warn(__FILE__, 'error attempting to update backup db: ' . $this->get_db_name() . ' table: ' . $table . ' error' . $exception->getMessage(), WarnTypes::DB_FRAMEWORK_WARN);
                $this->handle_error_message($exception->getMessage());
            }
        }

        $this->log_query("update", $table, $this->get_where_str($where));
        $this->ci_db->_reset_write();
        //PerformanceLogger::end_query();
        return $result;
    }

    public function set($key, $value = '', $escape = TRUE) {
        $this->ci_db->set($key, $value, $escape);
    }

    public function delete($table = '', $where = '', $limit = NULL, $reset_data = TRUE) {
        //PerformanceLogger::start_query("delete", $this->get_db_name(), $table, $where);
        //$result = NULL;
        try {
            $result = $this->ci_db->delete($table, $where, $limit, $reset_data);
        } catch(DbException $exception) {
            error(__FILE__, 'DB wrapper caught exception: ' . $exception->getMessage());
            $this->handle_error_message($exception->getMessage());
        }
        $this->log_query("delete", $table, $this->get_where_str($where));
        $this->handle_error();
        //PerformanceLogger::end_query();
        return $result;
    }

    public function get_where($table = '', $where = null, $limit = null, $offset = null) {
        if (!DB_FAILOVER) {
            //PerformanceLogger::start_query("get_where", $this->get_db_name(), $table, $where);
            try {
                $result = $this->ci_db->get_where($table, $where, $limit, $offset);
            } catch(DbException $exception) {
                error(__FILE__, 'DB wrapper caught exception: ' . $exception->getMessage());
            }
            $this->log_query("get_where", $table, $this->get_where_str($where));
            $this->handle_error();
            //PerformanceLogger::end_query();
            return $result;
        }

        $db_stat = DbConnStat::get_instance();
        $result = false;
        $error = false;
        $db_state = $this->get_db_state();
        //PerformanceLogger::start_query("get_where", $this->get_db_name(), $table, $where);
        try {
            if (!$db_stat->is_db_down($this->get_db_name())) {
                $result = $this->ci_db->get_where($table, $where, $limit, $offset);
            }
        } catch(DbException $exception) {
            warn(__FILE__, 'DB wrapper caught exception: ' . $exception->getMessage(), WarnTypes::DB_FRAMEWORK_WARN);
            $error = true;
        }

        $this->error_tracking();
        if (!$this->ci_db->conn_id || $db_stat->is_db_down($this->get_db_name())) {
            try {
                $slave_db = DbConnManager::get_slave_conn($this->ci_db->db_group);
                if ($slave_db) {
                    $this->initialize_db_connection($slave_db->ci_db, $db_state);
                    $result = $slave_db->ci_db->get_where($table, $where, $limit, $offset);
                    $this->handle_error_message($slave_db->ci_db->_error_message());
                }
                if (!$result || $result->num_rows() == 0) {
                    if (!$result && $slave_db) { debug(__FILE__, 'slave result is null: ' . print_r($slave_db->ci_db, true)); }
                    $backup_db = $this->get_backup_conn($db_state);
                    if ($backup_db) {
                        $result = $backup_db->ci_db->get_where($table, $where, $limit, $offset);
                        $this->handle_error_message($backup_db->ci_db->_error_message());
                    }
                }
                $error = false;
            } catch (Exception $exception) {
                warn(__FILE__, 'error getting to data from backup db: ' . $this->get_db_name() . ' table: ' . $table . ' error' . $exception->getMessage(), WarnTypes::DB_FRAMEWORK_WARN);
                $this->handle_error_message($exception->getMessage());
            }
        }

        $this->log_query("get_where", $table, $this->get_where_str($where));
        $this->ci_db->_reset_select();
        if ($error) {
            $this->handle_error();
        }
        //PerformanceLogger::end_query();
        if (!$result) {
            debug(__FILE__, "null result conn_id: " . $this->ci_db->conn_id . " db_name: " . $this->get_db_name()  . ' db_state: ' . print_r($db_state, true));
            if ($backup_db) {
                debug(__FILE__, 'backup result is null: ' . print_r($backup_db->ci_db, true));
            }
        }

        return $result;
    }

    private function get_where_str($where) {
        if (is_null($where)) {
            return "";
        }
        return implode(":", array_keys($where));
    }

    public function get_where_in() {

    }

    public function get_backup_conn($state = null) {
        require_once(APPPATH . 'config/database.php');

        $db_name = $this->ci_db->db_group;
        $backup_name = '';
        if (strpos($db_name, 'backup') !== FALSE) {
            return null;
        } else {
            $backup_name = $db_name . '_backup';
        }

        if (isset($GLOBALS['db_config'][$backup_name])) {
            $backup_conn = DbConnManager::get_db_conn($backup_name);
            if ($state) {
                $this->initialize_db_connection($backup_conn->ci_db, $state);
            }
            debug(__FILE__, "getting backup connection: " . $backup_name);
            return $backup_conn;
        } else {
            debug(__FILE__, 'invalid backup db config for: ' . $db_name);
        }

        return null;
    }

    private function error_tracking() {
        $error_message = $this->ci_db->_error_message();
        $error_no = $this->ci_db->_error_number();
        $database_name = $this->get_db_name();
        $db_stat = DbConnStat::get_instance();
        if (!$this->ci_db->conn_id && !$db_stat->is_db_down($database_name)) {
            debug(__FILE__, "database connection error no: " . $error_no . " error msg: $error_message database: " . $database_name);
            $db_stat->increment_host_error($database_name, 1);
        } else if ($error_message) {
            warn(__FILE__, "database error no: " . $error_no . " error msg: " . $error_message . " on database: " . $database_name, WarnTypes::DB_FRAMEWORK_WARN);
            $db_stat->update_db_error($error_no, $database_name);
            if (!$db_stat->is_db_down($database_name)) {
                //throw normal database errors
                $this->ci_db->_reset_select();
                throw new DbException($error_message);
            }
        }
    }


    private function handle_error() {
        if ($error_message = $this->ci_db->_error_message()) {
            warn(__FILE__, "DATABASE ERROR: $error_message on database: " . $this->get_db_name(), WarnTypes::DB_FRAMEWORK_WARN);
            $db_stat = DbConnStat::get_instance();
            $db_stat->update_db_error($this->ci_db->_error_number(), $this->get_db_name());
            throw new DbException($error_message);
        }
    }

    private function handle_error_message($error_message){
        if ($error_message) {
            warn(__FILE__, "DATABASE failure error_message: $error_message on database: " . $this->get_db_name(), WarnTypes::DB_FRAMEWORK_WARN);
            throw new DbException($error_message);
        }
    }

    private function get_db_state() {
        $state = array();
        foreach (self::$filter_fields as $field => $value) {
            if (isset($this->ci_db->$field)) {
                $state[$field] = $this->ci_db->$field;
            }
        }
        return $state;
    }

    private function initialize_db_connection($ci_db, $state) {

        foreach (self::$filter_fields as $field => $value) {
            if (isset($state[$field])) {
                $ci_db->$field = $state[$field];
            }
        }

        return $ci_db;
    }
}


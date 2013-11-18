<?php
/**
 * Keeps track of the state of the database connections, resets the connection error counts after certain time
 *
 *
 */
require_once(COREPATH . 'cache/ApcCacheDataStore.php');


class DbConnStat {

    private $db_conn_stat = array();
    private $last_update_date = null;

    private static $instance = null;
    private static $cache_key = 'db_connection_stat';
    const CACHE_LIFE_TIME = 120;
    private static $failed_dbs = array(); //current failed dbs

    private static $db_errors = array(
        '1040'  => 'Too many connections',
        '1041'  => 'Out of memory',
        '1042'  => 'bad host error',
        '1049'  => 'bad database error',
        '1053'  => 'server shutdown in progress',
        '1129'  => 'Host is blocked because of too many connection errors',
        '1203'  => 'user has more than max_user_connections active connections',
        '1205'  => 'Lock wait timeout exceeded; try restarting transaction',
        '1226'  => 'user has exceeded the max resources',
        '2002'  => 'Unable to connect to the database',
        '2003'  => 'Cannot connect to MySQL server',
        '2005'  => 'Unknown MySQL server host',
        '2006'  => 'MySQL server has gone away',
        '2008'  => 'MySQL client ran out of memory',
        '2013'  => 'Lost connection to MySQL server at reading initial communication packet',
    );

    private function __construct() {
        $this->last_update_date = time();
    }

    /**
     * @static
     * @return DbConnStat
     */
    public static function get_instance() {
        if (!is_null(self::$instance)) {
            return self::$instance;
        }
        $cache = ApcCacheDataStore::get_cache();
        $stat = $cache->get(self::$cache_key);
        if (is_null($stat)) {
            $stat = new DbConnStat();
        } else {
            if ($stat->last_update_date < time() - self::CACHE_LIFE_TIME) {
                $stat->reset();
            }
        }
        self::$instance = $stat;
        return self::$instance;
    }

    public function increment_host_error($db_name, $value) {
        if (empty($this->db_conn_stat[$db_name]) || $this->db_conn_stat[$db_name] < DB_ERROR_COUNT_LIMIT) {
            $cache = ApcCacheDataStore::get_cache();
            $old_stat = $cache->get(self::$cache_key);
            if ($old_stat) {
                //copy the db stat
                $old_stat = $old_stat->get_stats();
                foreach ($old_stat as $name => $count) {
                    if (empty($this->db_conn_stat[$name]) || $this->db_conn_stat[$name] < $count) {
                        $this->db_conn_stat[$name] = $count;
                    }
                }

            }
            if (empty($this->db_conn_stat[$db_name])) {
                $this->db_conn_stat[$db_name] = $value;
            } else {
                $this->db_conn_stat[$db_name] += $value;
            }
            $this->save();
            if ($this->db_conn_stat[$db_name] == 1) {
                warn(__FILE__, "initially marking_database_host as down: " . $db_name . " error count: " . $this->db_conn_stat[$db_name], WarnTypes::DB_FRAMEWORK_WARN);
            } else {
                if ($this->db_conn_stat[$db_name] >= DB_ERROR_COUNT_LIMIT) {
                    error(__FILE__, "marking_database_host as completely down: " . $db_name . " error count: " . $this->db_conn_stat[$db_name], WarnTypes::DB_FRAMEWORK_WARN);
                } else {
                    debug(__FILE__, "incrementing database error count, database: " . $db_name  . " error count: " . $this->db_conn_stat[$db_name]);
                }
            }
        }
        self::$failed_dbs[$db_name] = true;

    }

    /**
     * Check if the error_code corresponds to a serious db connection error
     * Only marks the db host down if the error_code is a serious error
     * @param $error_code
     * @param $db_name
     * @return void
     */
    public function update_db_error($error_code, $db_name, $value = 1) {
        if (isset(self::$db_errors[$error_code])) {
            $this->increment_host_error($db_name, $value);
            $error_message = self::$db_errors[$error_code];
            debug(__FILE__, "updating database stats due to error_code: " . $error_code . " error_message: " . $error_message . " database: " . $db_name);
        }
    }

    /**
     * returns an array of good db shards
     * @param $shard_group
     * @return array
     */
    public function filter_db_shards($shard_group) {
        $result = array();
        $CI = get_instance();
        $CI->load->model('ShardMapModel');
        $shards = $CI->ShardMapModel->get_all_shards($shard_group);
        foreach($shards as $shard) {
            if ($shard && $shard->is_alive) {
                $db_name = $shard->db_name;
                if (!$this->is_db_down($db_name)) {
                    $result[] = $shard;
                }
            }
        }
        return $result;
    }

    /**
     * check if the database is down
     * @param $db_name
     * @return bool
     */
    public function is_db_down($db_name) {
        if (isset($this->db_conn_stat[$db_name]) && $this->db_conn_stat[$db_name] >= DB_ERROR_COUNT_LIMIT) {
            return true;
        } else if(DB_FAILOVER && isset(self::$failed_dbs[$db_name])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * check if the database is completely down, a database is completely down if the slave or backup db is down
     * @param $db_name
     * @return bool
     */
    public function is_db_completely_down($db_name) {
        if ($this->is_db_down($db_name) && ($this->is_db_down($db_name . '_slave') || $this->is_db_down($db_name . '_backup'))) {
            return true;
        } else {
            return false;
        }
    }
    public function get_stats() {
        return $this->db_conn_stat;
    }

    public function get_last_reset_time() {
        return $this->last_update_date;
    }

    private function reset() {
        $this->db_conn_stat = array();
        $this->last_update_date = time();
    }

    private function save() {
        $cache = ApcCacheDataStore::get_cache();
        $cache->set(self::$cache_key, $this, self::CACHE_LIFE_TIME);
    }
}


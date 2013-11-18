<?php
//require_once(COREPATH . 'analytics/PerformanceLogger.php');
//require_once(APPPATH . 'libraries/SimpleLogger.php');

class LocalCacheDataStore {

    private static $_CACHE_POOL = array();
    private $_local_cache;

    public static function get_cache($id) {
        if (array_key_exists($id, self::$_CACHE_POOL)) {
            return self::$_CACHE_POOL[$id];
        }

        $cache = new LocalCacheDataStore();
        self::$_CACHE_POOL[$id] = $cache;
        return $cache;
    }

    private function LocalCacheDataStore() {
        $this->_local_cache = array();
    }

    public function get($key) {
        $data = NULL;
        //PerformanceLogger::start_local_cache_call("get", $key);
        if (array_key_exists($key, $this->_local_cache)) {
            $data = $this->_local_cache[$key];
        }
        //PerformanceLogger::end_local_cache_call();
        return $data;
    }

    public function put($key, $value, $expiration=0) {
        //PerformanceLogger::start_local_cache_call("put", $key);
        $this->_local_cache[$key] = $value;
        //PerformanceLogger::end_local_cache_call();
        return true;
    }

    public function delete($key) {
        //PerformanceLogger::start_local_cache_call("delete", $key);
        if (array_key_exists($key, $this->_local_cache)) {
            unset($this->_local_cache[$key]);
        }
        //PerformanceLogger::end_local_cache_call();
    }

}


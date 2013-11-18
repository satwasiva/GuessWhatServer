<?php
require_once(APPPATH . 'config/game_config.php');


/**
 * Store data on the server's APC cache so the data will persist across requests.
 * Great for data that doesn't change, keeping track of stat on the server and etc.
 * DO NOT store player data on APC.
 *
 */
class ApcCacheDataStore {

    private static $_apc_cache = NULL;

    /**
     * @static
     * @return ApcCacheDataStore
     */
    public static function get_cache() {
        if (is_null(self::$_apc_cache)) {
            self::$_apc_cache = new ApcCacheDataStore();
        }
        return self::$_apc_cache;
    }

    private function ApcCacheDataStore() {
    }

    public function get($key) {
        if (APC_CACHE) {
            $success = false;
            $key = ApcCacheDataStore::format_key($key);
            //debug(__FILE__, "Fetching value for key: " . $key);
            $data = apc_fetch($key, $success);
            if (!$success) {
                $data = NULL;
            }
            return $data;
        } else {
            return NULL;
        }
    }

    public function put($key, $value, $ttl = 0) {
        if (APC_CACHE) {
            $success = false;
            // This isn't perfect, but it helps reduce the APC Cache slam warnings
            $data = $this->get($key);
            if (is_null($data)) {
                $key = ApcCacheDataStore::format_key($key);
                //debug(__FILE__, "Storing value for key: " . $key);
                $success = apc_add($key, $value, $ttl);
            }
            return $success;
        } else {
            return true;
        }
    }

    public function set($key, $value, $ttl = 0) {
        if (APC_CACHE) {
            $key = ApcCacheDataStore::format_key($key);
            apc_store($key, $value, $ttl);
        }
    }

    public function remove($key)
    {
        if(APC_CACHE)
        {
            $key = ApcCacheDataStore::format_key($key);
            apc_delete($key);
        }
    }

    public function increment($key, $step = 1) {
        if (APC_CACHE) {
            $key = ApcCacheDataStore::format_key($key);
            return apc_inc($key, $step);
        }
    }

    private static function format_key($key) {
        return substr(APPLICATION_NAME, 0, 3) . ':' . $key;
    }
}


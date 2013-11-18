<?php
require_once(COREPATH . '/models/basemodel.php');
require_once(APPPATH . 'config/game_config.php');
require_once(COREPATH . 'database/DbConnManager.php');
require_once(COREPATH . '/cache/ApcCacheDataStore.php');
require_once(COREPATH . '/cache/LocalCacheDataStore.php');

class StaticModel extends BaseModel {

    private $database_config_name;
    private $apc_cache;
    public $md5;

    function StaticModel($db_config_name) {
        parent::BaseModel();
        $this->database_config_name = $db_config_name;
        $this->apc_cache = ApcCacheDataStore::get_cache();
        $this->local_cache = LocalCacheDataStore::get_cache('static');
        $this->md5 = $this->compute_md5();
    }

    protected function get_db($obj) {
        return DbConnManager::get_db_conn($this->database_config_name);
    }

    private function get_default_db() {
        return $this->get_db(NULL);
    }

    private function id_cache_key($table, $id) {
        return $table . ":id:" . $id;
    }

    private function static_cache_key($table, $where_params, $orderby_params) {
        $key = $table;
        if (! is_null($where_params)) {
            ksort($where_params);
            foreach ($where_params as $k => $v) {
                $key = $key . ":" . $k . ":" . $v;
            }
        }
        if (! is_null($orderby_params)) {
            ksort($orderby_params);
            $key = $key . ":ob";
            foreach ($orderby_params as $k => $v) {
                $key = $key . ":" . $k . ":" . $v;
            }
        }
        return $key;
    }


    public function get($id) {
        $key = $this->id_cache_key($this->tbl_name, $id);
        $obj = $this->cache_get($key);
        if (is_null($obj)) {
            $obj = parent::get($this->get_default_db(), $id);
            $this->cache_put($key, $obj);
        }
        return $obj;
    }

    public function get_where($query_params, $ttl = 0) {
        $key = $this->static_cache_key($this->tbl_name, $query_params, NULL);
        $results =  $this->cache_get($key);
        if (is_null($results)) {

            $dbw = DbConnManager::get_db_conn($this->database_config_name);
            $query = $dbw->get_where($this->tbl_name, $query_params);
            $results = $this->_map_to_objlist($query->result());
			error_log(print_r($results, true));
            $this->cache_put($key, $results, $ttl);
        }
        return $results;
    }

    public function get_all($ttl = 0) {
        $key = $this->tbl_name . ":all";
        $results =  $this->cache_get($key);
        if (is_null($results)) {
            $dbw = DbConnManager::get_db_conn($this->database_config_name);
            $query = $dbw->get($this->tbl_name);
            $results = $this->_map_to_objlist($query->result());
            $this->cache_put($key, $results, $ttl);
        }
        return $results;
    }
    
    public function get_where_in($where_in, $query_params=NULL)
    {
    	$to_return = array();
    	
    	$results = NULL;
    	if(is_null($query_params))
    	{
    		$results = $this->get_all();
    	}
    	else
    	{
    		$results = $this->get_where($query_params);
    	}
    	foreach($results as $result)
    	{
    		foreach($where_in as $k => $v)
    		{
    			if(in_array($result->$k, $v))
    			$to_return[] = $result;
    		}
    	}
    	return $to_return;
    }


    public function get_where_orderby($where_params, $orderby) {
        $success = false;
        $key = $this->static_cache_key($this->tbl_name, $where_params, $orderby);
        $results = $this->cache_get($key);
        if (is_null($results)) {
            $dbw = $this->get_default_db();
            $dbw->from($this->tbl_name);
            if (! is_null($where_params)) {
                $dbw->where($where_params);
            }
            foreach ($orderby as $k => $v) {
                //log_message('info', "ORDER " . $k . "  " . $v);
       	        $dbw->order_by($k, $v);
            }
            $query = $dbw->get();
            $results = $this->_map_to_objlist($query->result());

            // TODO - alk - log error if still no results
            $this->cache_put($key, $results);
        }
        return $results;
    }

    /**
     * Set the data to both local cache and APC cache
     * @param $key
     * @param $data
     * @param $ttl
     * @return void
     */
    protected function cache_put($key, $data, $ttl = 0) {
        $this->apc_cache->put($key, $data, $ttl);
        $this->local_cache->put($key, $data, $ttl);
    }

    protected function apc_cache_put($key, $data, $ttl = 0) {
    	$this->apc_cache->put($key, $data, $ttl);
    }

    /**
     * Try to get the data from the local cache first, if not there then try to get the data from APC cache.
     * If the data is in APC but not in local cache, put the data in local cache.
     * Otherwise, return null
     * @param $key
     * @return null
     */
    protected function cache_get($key) {
        $data = $this->local_cache->get($key);
        if (!is_null($data)) {
            return $data;
        }
        $data = $this->apc_cache->get($key);
        if (!is_null($data)) {
            $this->local_cache->put($key, $data);
        }
        return $data;
    }

    protected function apc_cache_get($key) {
    	$data = $this->apc_cache->get($key);
    	return $data;
    }

    protected function get_where_nocache($query_params) {
        $query = $this->get_default_db()->get_where($this->tbl_name, $query_params);
        $results = $this->_map_to_objlist($query->result());
        return $results;
    }

    protected function delete($where_params) {
        $dbw = $this->get_default_db();
        $dbw->delete($this->tbl_name, $where_params);
    }

    protected function compute_md5()
    {
    	$key = $this->tbl_name . ":md5";
        $result =  $this->cache_get($key);
        if(is_null($result))
        {
    		$table = $this->tbl_name . "";
    		$all = $this->get_where_orderby(array(), array("id" => "asc"));
    		foreach($all as $row)
    		{
    			foreach($row as $k => $v)
    			{
    				$table .= $v;
    			}
    		}
    		$sig = md5($table);
    		//debug(__FILE__, $this->tbl_name . ' md5=' . $sig);
    		$this->cache_put($key, $sig);
    		return md5($table);
        }
    	return $result;
    }
}
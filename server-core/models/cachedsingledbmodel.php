<?php
require_once(COREPATH . '/models/basemodel.php');
require_once(COREPATH . 'database/DbConnManager.php');
require_once(COREPATH . '/cache/CacheDataStore.php');
require_once(COREPATH . '/cache/LocalCacheDataStore.php');
require_once(COREPATH . '/database/NodeWrapper.php');
require_once(APPPATH . 'config/game_config.php');

class CachedSingleDbModel extends BaseModel
{
	protected $local_cache;
    protected $cache;
    protected $write_through_cache;
	protected $variable_query_keys;
	protected $cache_keys;
    protected $expiry; //default to 0, which means no expiration, number of seconds to cache
    
    function __construct($db_config_name, $variable_query_keys=NULL, $write_through = FALSE, $memcached_pool = "player", $expiry = 0)
    {
    	if(!is_null($variable_query_keys))
    	{
	        $this->variable_query_keys = $variable_query_keys;
	        $this->cache_keys = $this->generate_keys();
            $this->cache = new CacheDataStore($memcached_pool);
            $this->local_cache = LocalCacheDataStore::get_cache($memcached_pool);
	        $this->write_through_cache = $write_through;
            $this->expiry = $expiry;
    	}
        else
        {
        	parent::__construct();
        }
        $this->database_config_name = $db_config_name;
    }
    
	private function generate_keys()
	{
        $keys = array();

        foreach($this->variable_query_keys as $variable_query_key)
        {
        	$keys[] = $this->static_cache_key($variable_query_key);
        }
        return $keys;
    }

    private function static_cache_key($variable_query_key)
    {
        $key = $this->tbl_name . ":" . $variable_query_key . ":%s";
        return $key;
    }

    private function invalidate_cached_keys($variable_query_key, $variable_query_value)
    {
        foreach ($this->cache_keys as $key)
        {
            //debug(__FILE__, "Removing object from memcached: " . str_replace("%s", $variable_query_value, $key));
            // if the key is for the given variable_query_key delete keys for the given value
            if(strpos($key, $variable_query_key) !== FALSE)
            {
            	$this->cache->delete(str_replace("%s", $variable_query_value, $key));
            	$this->local_cache->delete(str_replace("%s", $variable_query_value, $key));
            }
        }
    }

    protected function invalidate($obj)
    {
        foreach($this->variable_query_keys as $query_key)
        {
            if(isset($obj->$query_key))
            {
                $this->invalidate_cached_keys($query_key, $obj->$query_key);
            }
        }
    }

    protected function post_process_list_save($key, $list)
    {
        return $list;
    }

    private function update_cached_objects($variable_query_key, $variable_query_value, $updated_obj)
    {
        foreach ($this->cache_keys as $key)
        {
        	// if this key does not pertain to this variable query key
        	if(strpos($key, $variable_query_key) === FALSE)
        	{
        		continue;
        	}
            $full_key = str_replace("%s", $variable_query_value, $key);

            // Remove local cache version
            $this->local_cache->delete($full_key);

            // Check if the object is stored within an array, if so, then look for it in the array and update accordingly
            $old_cached_data = $this->cache->get($full_key);

            // Only update the cache if the object was already in the cache.
            // If it isn't, then it's safer to let it get lazy loaded the next time it is read
            if (! is_null($old_cached_data))
            {
                if (is_array($old_cached_data))
                {
                    $new_data = array();
                    $found_data_in_array = false;

                    // We are assuming that the object only exists in the list once
                    $old_obj_in_array = NULL;
                    foreach($old_cached_data as $d)
                    {
                        if ($d->id == $updated_obj->id)
                        {
                            $new_data[] = $updated_obj;
                            $found_data_in_array = true;
                            $old_obj_in_array = $d;
                        }
                        else
                        {
                            $new_data[] = $d;
                        }
                    }

                    // Only update if the version is greater than previous version
                    if ($found_data_in_array)
                    {
                        if (isset($old_obj_in_array->version))
                        {
                            if ($old_obj_in_array->version < $updated_obj->version)
                            {
                                // This is to allow models to filter out objects from the list based on query parameters
                                $new_data = $this->post_process_list_save($key, $new_data);

                                $this->cache->put($full_key, $new_data, $this->expiry);
                                $this->local_cache->put($full_key, $new_data);
                                //debug(__FILE__, "Updated object in cached array: " . $full_key .  ",  update version = " . $updated_obj->version);
                            }
                            else
                            {
                                warn(__FILE__, "Did not update object because version is stale: " . $full_key .  ",  cached version = " . $old_obj_in_array->version . ",  update version = " . $updated_obj->version, WarnTypes::OBJECT_STALE_SINGLEDB);
                            }
                        }
                        else
                        {
                            // This is to allow models to filter out objects from the list based on query parameters
                            $new_data = $this->post_process_list_save($key, $new_data);

                            //debug(__FILE__, "Writing new object into list for table " . $this->tbl_name . ":  " . json_encode($updated_obj));
                            $this->cache->put($full_key, $new_data, $this->expiry);
                            $this->local_cache->put($full_key, $new_data);
                        }
                    }
                    else
                    {
                        // This is a new insert being saved.  Add to list and populate the cache.
                        $new_data[] = $updated_obj;

                        // This is to allow models to filter out objects from the list based on query parameters
                        $new_data = $this->post_process_list_save($key, $new_data);

                        //debug(__FILE__, "Adding new object into list for table " . $this->tbl_name . ":  " . json_encode($updated_obj));
                        $this->cache->put($full_key, $new_data, $this->expiry);
                        $this->local_cache->put($full_key, $new_data);
                    }
                }
                else
                {
                    // Only update if the version is greater than previous version
                    if ($old_cached_data->version < $updated_obj->version)
                    {
                        $this->cache->put($full_key, $updated_obj, $this->expiry);
                        $this->local_cache->put($full_key, $updated_obj);

                        // 2011-03-12 - alk - commenting out to reduce logging output
                        //if (isset($updated_obj->money)) {
                            //debug(__FILE__, "PLAYERFINALIZER Old player: m = " . $old_cached_data->money . ", v = " . $old_cached_data->version . "  New player: m = " . $updated_obj->money . ", v = " . $updated_obj->version);
                        //}

                        //debug(__FILE__, "Updated object in cache: " . $full_key .  ",  update version = " . $updated_obj->version);
                    }
                    else
                    {
                        warn(__FILE__, "Did not update object because version is stale: " . $full_key .  ",  cached version = " . $old_cached_data->version . ",  update version = " . $updated_obj->version, WarnTypes::OBJECT_STALE_SINGLEDB);
                    }
                }
            }
        }
    }

    private function is_query_supported($key)
    {
        return in_array($key, $this->cache_keys);
    }
    
    protected function get_db($obj) {
        return DbConnManager::get_db_conn($this->database_config_name);
    }

    protected function get_default_db() {
        return $this->get_db(NULL);
    }

    public function get_where($query_params)
    {
        $key = NULL;
        if(count($query_params) == 1)
        {
        	foreach($query_params as $q_key => $q_value)
        	{
        		$key_check = $this->static_cache_key($q_key);
        		$key = str_replace("%s", $q_value, $key_check);
        	}
        }
        
    	if (!$this->is_query_supported($key_check))
    	{
            throw new ModelException("Unsupported query!");
        }

        $results = NULL;
        if (MEMCACHE_ON)
        {
           	$results = $this->local_cache->get($key);
           	if (is_null($results))
           	{
               	$results = $this->cache->get($key);
               	if (is_null($results))
               	{
                    $dbw = $this->get_default_db();
               	    $query = $dbw->get_where($this->tbl_name, $query_params);
        			$results = $this->_map_to_objlist($query->result());
               	    $this->cache->put($key, $results, $this->expiry);
               	}
               	$this->local_cache->put($key, $results);
           	}
		}
        else
		{
            $dbw = $this->get_default_db();
			$query = $dbw->get_where($this->tbl_name, $query_params);
			$results = $this->_map_to_objlist($query->result());
        }
        return $results;
    }
    
	public function get_where_nocache($query_params)
	{
        $dbw = $this->get_default_db();

        $query = $dbw->get_where($this->tbl_name, $query_params);
        $results = $this->_map_to_objlist($query->result());
        return $results;
    }
    
    public function save(&$obj, $force_insert = False)
    {
        $is_success = parent::save($obj, $force_insert);
    	
    	if ($this->write_through_cache)
    	{
    		foreach($this->variable_query_keys as $d_key)
    		{
           		$this->update_cached_objects($d_key, $obj->$d_key, $obj);
    		}
        }
        else
        {
    		//invalidate all cache key entries for this object
    		foreach($this->variable_query_keys as $query_key)
    		{
    			if(isset($obj->$query_key))
    			{
    				$this->invalidate_cached_keys($query_key, $obj->$query_key);
    			}
    		}
    	}

        return $is_success ? true : false;
    }
    
    protected function save_as_update_where($obj, $extra_where_params)
    {
    	parent::save_as_update_where($obj, $extra_where_params);
    	
    	if ($this->write_through_cache)
    	{
    		foreach($this->variable_query_keys as $d_key)
    		{
           		$this->update_cached_objects($d_key, $obj->$d_key, $obj);
    		}
        }
        else
        {
        	//invalidate all cache key entries for this object
    		foreach($this->variable_query_keys as $query_key)
    		{
    			if(isset($obj->$query_key))
    			{
    				$this->invalidate_cached_keys($query_key, $obj->$query_key);
    			}
    		}
        }
    }
}
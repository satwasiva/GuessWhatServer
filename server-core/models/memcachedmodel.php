<?php
//require_once(COREPATH . '/cache/CacheDataStore.php');
require_once(COREPATH . '/cache/LocalCacheDataStore.php');
require_once(COREPATH . 'database/DbConnWrapper.php');
//require_once(COREPATH . '/database/NodeWrapper.php');
require_once(COREPATH . '/models/shardedmodel.php');
require_once(APPPATH . 'config/game_config.php');

class ModelException extends Exception { }

class MemcachedModel extends ShardedModel {

    protected $local_cache;
    protected $cache;
    protected $write_through_cache;
    protected $use_node_mysql;
    protected $node_transport_type;
    protected $node_command;

    /* shard_group:  The database shard group
     * variable_query_key:  The table column that will be the primary key that the data will be cached on.  Most of the time it should be player_id
     * supported_extra_params:  Extra 'where' clause parameters that may be used to cache lists of data for a player
     * write_through:  flag to determine if writes to update the cache directly instead of invalidating a cached item
     *
     * NOTE:  All cacheable tables must have an id field as their primary key, and a version field if they want to use write-through caching.
     * TODO:  Enforce that somewhere.
     */
    function MemcachedModel($shard_group, $variable_query_key, $supported_extra_params, $write_through = TRUE, $memcached_pool = NULL, $use_node_mysql = TRUE, $node_transport_type = "tcp") {
        parent::ShardedModel($shard_group, $variable_query_key);
        $this->variable_query_key = $variable_query_key;
        $this->supported_extra_params = $supported_extra_params;
        $this->cache_keys = $this->generate_keys($this->supported_extra_params);
        if (is_null($memcached_pool)) {
            //$this->cache = new CacheDataStore("player");
            $this->local_cache = LocalCacheDataStore::get_cache("player");
        } else {
            //$this->cache = new CacheDataStore($memcached_pool);
            $this->local_cache = LocalCacheDataStore::get_cache($memcached_pool);
        }
        $this->write_through_cache = $write_through;
        $this->use_node_mysql = $use_node_mysql;
        $this->node_transport_type = $node_transport_type;

        // initialize backtrace and debuginfo arrays
        $class_name = get_class($this);
        $GLOBALS['backtraces'][$class_name] = array();
        $GLOBALS['debuginfo'][$class_name] = array();
        $GLOBALS['updatetimes'][$class_name] = array();
    }

    private function generate_keys($query_params_list) {
        $keys = array();

        // Add the default (always supported) query by the variable_query_key
        $keys[] = $this->static_cache_key(array($this->variable_query_key => "%s"));

        // Add the additional supported queries
        foreach ($query_params_list as $params) {
            $p = $params;
            $p[$this->variable_query_key] = "%s";
            $keys[] = $this->static_cache_key($p);
        }
        return $keys;
    }

    protected function static_cache_key($query_params) {
        $qp = $query_params;

        if (array_key_exists($this->variable_query_key, $qp)) {
            unset($qp[$this->variable_query_key]);
        }

        ksort($qp);
        $key = $this->tbl_name . ":" . $this->variable_query_key . ":%s";
        foreach ($qp as $k => $v) {
            $key = $key . ":" . $k . ":" . $v;
        }
        return $key;
    }

    protected function invalidate_cached_keys($variable_query_value) {
        foreach ($this->cache_keys as $key) {
            //debug(__FILE__, "Removing object from memcached: " . str_replace("%s", $variable_query_value, $key));
            //$this->cache->delete(str_replace("%s", $variable_query_value, $key));
            $this->local_cache->delete(str_replace("%s", $variable_query_value, $key));
        }
    }

    protected function post_process_list_save($key, $list) {
        return $list;
    }

    private function handle_stale_object($full_key, $old_obj, $updated_obj) {
        $class_name = get_class($this);
        warn(__FILE__, "Did not update object because version is stale: " . $full_key .  ",  cached version = " . $old_obj->version . ",  update version = " . $updated_obj->version, CoreWarnTypes::STALE_ERROR);
        debug(__FILE__, print_r($GLOBALS['updatetimes'][$class_name], true));
        debug(__FILE__, $old_obj->time_updated);
        if(in_array($old_obj->time_updated, $GLOBALS['updatetimes'][$class_name])) {
            $GLOBALS['STALE_CHECK'][$class_name] = true;
        }

    }

    private function update_cached_objects($variable_query_value, $updated_obj) {
        $is_success = true;
        foreach ($this->cache_keys as $key) {
            $full_key = str_replace("%s", $variable_query_value, $key);

            // Remove local cache version
            $this->local_cache->delete($full_key);

            // Check if the object is stored within an array, if so, then look for it in the array and update accordingly
            //$old_cached_data = $this->cache->get($full_key);

            // Only update the cache if the object was already in the cache.
            // If it isn't, then it's safer to let it get lazy loaded the next time it is read
            if (! is_null($old_cached_data)) {
                if (is_array($old_cached_data)) {
                    $new_data = array();
                    $found_data_in_array = false;

                    // We are assuming that the object only exists in the list once
                    $old_obj_in_array = NULL;
                    foreach($old_cached_data as $d) {
                        if ($d->id == $updated_obj->id) {
                            $new_data[] = $updated_obj;
                            $found_data_in_array = true;
                            $old_obj_in_array = $d;
                        } else {
                            $new_data[] = $d;
                        }
                    }

                    // Only update if the version is greater than previous version
                    if ($found_data_in_array) {
                        if (isset($old_obj_in_array->version)) {
                            if ($old_obj_in_array->version < $updated_obj->version) {
                                // This is to allow models to filter out objects from the list based on query parameters
                                $new_data = $this->post_process_list_save($key, $new_data);

                                //$is_success = $this->cache->put($full_key, $new_data);
                                $this->local_cache->put($full_key, $new_data);
                                //debug(__FILE__, "Updated object in cached array: " . $full_key .  ",  update version = " . $updated_obj->version);
                            } else {
                                $this->handle_stale_object($full_key, $old_obj_in_array, $updated_obj);
                                $is_success = false;
                            }
                        } else {
                            // This is to allow models to filter out objects from the list based on query parameters
                            $new_data = $this->post_process_list_save($key, $new_data);

                            //debug(__FILE__, "Writing new object into list for table " . $this->tbl_name . ":  " . json_encode($updated_obj));
                            //$is_success = $this->cache->put($full_key, $new_data);
                            $this->local_cache->put($full_key, $new_data);
                        }
                    } else {
                        // This is a new insert being saved.  Add to list and populate the cache.
                        $new_data[] = $updated_obj;

                        // This is to allow models to filter out objects from the list based on query parameters
                        $new_data = $this->post_process_list_save($key, $new_data);

                        //debug(__FILE__, "Adding new object into list for table " . $this->tbl_name . ":  " . json_encode($updated_obj));
                        //$is_success = $this->cache->put($full_key, $new_data);
                        $this->local_cache->put($full_key, $new_data);
                    }
                } else {
                    // Only update if the version is greater than previous version
                    if ($old_cached_data->version < $updated_obj->version) {
                        //$is_success = $this->cache->put($full_key, $updated_obj);
                        $this->local_cache->put($full_key, $updated_obj);

                        // 2011-03-12 - alk - commenting out to reduce logging output
                        //if (isset($updated_obj->money)) {
                            //debug(__FILE__, "PLAYERFINALIZER Old player: m = " . $old_cached_data->money . ", v = " . $old_cached_data->version . "  New player: m = " . $updated_obj->money . ", v = " . $updated_obj->version);
                        //}

                        //debug(__FILE__, "Updated object in cache: " . $full_key .  ",  update version = " . $updated_obj->version);
                    } else {
                        $this->handle_stale_object($full_key, $old_cached_data, $updated_obj);
                        $is_success = false;
                    }
                }
            }
        }
        return $is_success;
    }

    private function is_query_supported($key) {
        return in_array($key, $this->cache_keys);
    }

    public function get_object($query_params) {
        //debug(__FILE__, get_class($this) . ":  MCMODEL get_object called: " . json_encode($query_params));
        $success = false;
        $key = $this->static_cache_key($query_params);
        if (! $this->is_query_supported($key)) {
            throw new ModelException("Unsupported query!");
        }

        $val = $query_params[$this->variable_query_key];
        $key = str_replace("%s", $val, $key);
        $obj = NULL;

        $results = NULL;
        if (MEMCACHE_ON) {
            $obj = $this->local_cache->get($key);
            if (is_null($obj)) {
                //$obj = $this->cache->get($key);
                if (is_null($obj)) {
                    $results = parent::get_where($query_params);
                    if (sizeof($results) > 1) {
                        error(__FILE__, "Multiple rows returned for single get query: " . $this->tbl_name . "  " . json_encode($query_params));
                        //throw new ModelException("Multiple rows returned for single get query: " . $this->tbl_name . "  " . json_encode($query_params));
                    }
                    if (sizeof($results) > 0) {
                        $obj = $results[0];
                    }

                    //$this->cache->put($key, $obj);
                }

                $this->local_cache->put($key, $obj);
            }
        } else {
            $results = parent::get_where($query_params);
            if (sizeof($results) > 0) {
                $obj = $results[0];
            }
        }

        return $obj;
    }

   /**
    * Try to get the data from the local cache first, if not there then try to get the data from APC cache.
    * If the data is in APC but not in local cache, put the data in local cache.
    * Otherwise, return null
    * @param $key
    * @return null
    */
    protected function cache_get($key) {
    	$obj = $this->local_cache->get($key);
    	if (!is_null($obj)) {
    		return $obj;
    	}
    	//$obj = $this->cache->get($key);
    	if (!is_null($obj)) {
    		$this->local_cache->put($key, $obj);
    	}
    	return $obj;
    }

    public function get_where($query_params, $limit=NULL) {
        //debug(__FILE__, get_class($this) . ":  MCMODEL get_where called: " . json_encode($query_params));
        $key = $this->static_cache_key($query_params);
        if (! $this->is_query_supported($key)) {
            throw new ModelException("Unsupported query!");
        }

        $val = $query_params[$this->variable_query_key];
        $key = str_replace("%s", $val, $key);

        $results = NULL;
        if (MEMCACHE_ON) {
            $results = $this->local_cache->get($key);
            if (is_null($results)) {
                //$results = $this->cache->get($key);
                if (is_null($results)) {
                    $results = parent::get_where($query_params, $limit);
                    //debug(__FILE__, get_class($this) . ":  MC MODEL GET_WHERE 1: table = " . get_class($this) . "  data = " . json_encode($results));
                    //$this->cache->put($key, $results);
                }
                $this->local_cache->put($key, $results);
            }
        } else {
            $results = parent::get_where($query_params, $limit);
            //debug(__FILE__, get_class($this) . ":  MC MODEL GET_WHERE 2: table = " . get_class($this) . "  data = " . json_encode($results));
        }

        return $results;
    }

	public function get_where_orderby($where_params, $orderby_params, $limit=NULL) {
        //debug(__FILE__, get_class($this) . ":  MC MODEL get_where_orderby called: " . json_encode($query_params));
		$key = $this->static_cache_key($where_params);
        if (! $this->is_query_supported($key)) {
            throw new ModelException("Unsupported query!");
        }

        $val = $where_params[$this->variable_query_key];
        $key = str_replace("%s", $val, $key);

        $results = NULL;

        if (MEMCACHE_ON) {
        	$results = $this->local_cache->get($key);
        	if(is_null($results))
        	{
        		$results = parent::get_where_orderby($where_params, $orderby_params, $limit);
        		//$this->cache->put($key, $results);
        	}
        	$this->local_cache->put($key, $results);
        }
        else
        {
        	$results = parent::get_where_orderby($where_params, $orderby_params, $limit);
        }
        return $results;
    }

    public function get_where_nocache($query_params) {
        //debug(__FILE__, get_class($this) . ":  MC MODEL get_where_nocache called: " . json_encode($query_params));
        return parent::get_where($query_params);
    }

    public function get_where_orderby_nocache($where_params, $orderby_params, $limit=NULL)
    {
        //debug(__FILE__, get_class($this) . ":  MC MODEL get_where_orderby_nocache called: " . json_encode($query_params));
    	return parent::get_where_orderby($where_params, $orderby_params, $limit);
    }

    public function supports_broadcast() {
        return false;
    }

    public function get_where_broadcast($query_params) {
        //debug(__FILE__, get_class($this) . ":  MC MODEL get_where_broadcast called: " . json_encode($query_params));
        return parent::get_where_broadcast($query_params);
    }

    private function create_get_multi_keys($partition_values) {
        $keys = array();
        foreach ($partition_values as $pv) {
            $k = str_replace("%s", $pv, $this->static_cache_key(array()));
            $keys[$k] = $pv;
        }
        return $keys;
    }

    public function get_where_in_cached($partition_values) {
        //debug(__FILE__, get_class($this) . ":  MC MODEL get_where_in_cached called: " . json_encode($partition_values));
        $shards_to_ids = $this->split_into_db_shards($partition_values);
        $filtered_ids = array();
        foreach($shards_to_ids as $shard_pos => $ids) {
            foreach($ids as $id) {
                $filtered_ids[] = $id;
            }
        }
        $multi_keys_map = $this->create_get_multi_keys($filtered_ids);
        $multi_keys = array_keys($multi_keys_map);
        //$data = $this->cache->get($multi_keys);
        $found_array = array();
        $pk = $this->variable_query_key;

        // Create a map of found partition values to their stored cache object
        if (sizeof($data) > 0) {
	        foreach ($multi_keys as $key) {
	            if (array_key_exists($key, $data)) {
	                $pv = $multi_keys_map[$key];
	                $found_array[$pv] = $data[$key];
	            }
	        }
        }

        $not_found_pvs = array();
        foreach ($filtered_ids as $pv) {
            if (! array_key_exists($pv, $found_array)) {
                $not_found_pvs[] = $pv;
            }
        }

        debug(__FILE__, "GETMULTI CALLED Found: " . sizeof($found_array) . "  NotFound: " . sizeof($not_found_pvs));
        // Query the database for all partition values that were not found in cache
        if (sizeof($not_found_pvs) > 0) {
            $static_cache_key = $this->static_cache_key(array());
            $query_data = parent::get_where_in_new($not_found_pvs);
            foreach ($query_data as $qkey => $qdata) {
                $found_array[$qkey] = $qdata;
                // TODO:  Add data to the cache
                $cache_key = str_replace('%s', $qkey, $static_cache_key);
                //$this->cache->put($cache_key, $qdata);
            }
        }

        $return_array = array();
        //order the data by the original input keys
        foreach ($partition_values as $pv) {
            if (array_key_exists($pv, $found_array)) {
                $return_array[$pv] = $found_array[$pv];
            }
        }
        return $return_array;
    }

    public function get_where_in_cached_single_obj($partition_values) {
        //debug(__FILE__, get_class($this) . ":  MC MODEL get_where_in_cached_single_obj called: " . json_encode($partition_values));
        $multi_keys_map = $this->create_get_multi_keys($partition_values);
        $multi_keys = array_keys($multi_keys_map);
        //$data = $this->cache->get($multi_keys);
        $found_array = array();
        $pk = $this->variable_query_key;

        // Create a map of found partition values to their stored cache object
        if (sizeof($data) > 0) {
	        foreach ($multi_keys as $key) {
	            if (array_key_exists($key, $data)) {
	                $pv = $multi_keys_map[$key];
	                $found_array[$pv] = $data[$key];
	            }
	        }
        }

        $not_found_pvs = array();
        foreach ($partition_values as $pv) {
            if (! array_key_exists($pv, $found_array)) {
                $not_found_pvs[] = $pv;
            }
        }

        debug(__FILE__, "SO GETMULTI  Found: " . sizeof($found_array) . "  NotFound: " . sizeof($not_found_pvs));
        // Query the database for all partition values that were not found in cache
        if (sizeof($not_found_pvs) > 0) {
            $query_data = parent::get_where_in($not_found_pvs);
            foreach ($query_data as $qkey => $qdata) {
                $found_array[$qkey] = $qdata;
                // TODO:  Add data to the cache
                // $cache_key = str_replace("%s", $qd->$pv, $this->static_cache_key(array()));
                // $this->cache->put($cache_key, $qd);
            }
        }

        $return_array = array();
        foreach ($partition_values as $pv) {
            if (array_key_exists($pv, $found_array)) {
                $return_array[] = $found_array[$pv];
            }
        }
        return $return_array;
    }

    public function save(&$obj, $force_insert=False, $async_write = FALSE) {
//        debug(__FILE__, "Calling save for table " . $this->tbl_name . ":  " . json_encode($obj));

        // add current backtrace and debuginfo into arrays
        $class_name = get_class($this);
        $GLOBALS['backtraces'][$class_name][] = debug_backtrace();
        $debug_save = array();
        if(property_exists($obj, 'player_id')) {
            $debug_save['player_id'] = $obj->player_id;
        }
        if(property_exists($obj, 'version')) {
            $debug_save['version'] = $obj->version;
        }
        $GLOBALS['debuginfo'][$class_name][] = $debug_save;

        $duplicate_key_error = false;

        // force id to be equal to variable_query_key for player shard tables
        if (APPLICATION_NAME !== 'modernwar' && APPLICATION_NAME !== 'crimecity') {
            if (!isset($obj->id) && $this->shard_group == 'player' && isset($obj->{$this->variable_query_key})) {
                $obj->id = $obj->{$this->variable_query_key};
                $force_insert = true;
            }
        }

        $skip_cache = false;
        $vkname = $this->variable_query_key;

        try {
            if (isset($obj->id) && !$force_insert && $async_write) {
                //debug(__FILE__, get_class($this) . ":  MC MODEL - MC Async write for object: " . $this->tbl_name);
                $is_success = true;
            } else {
                //debug(__FILE__,  get_class($this) . ":  MC MODEL - MC save() DB Write happening for: " . json_encode($obj));
                if (isset($obj->id) && !$force_insert && $this->write_through_cache && $this->use_node_mysql) {
                    //only update cache if id is set and user_node is true
                    $variable_key_val = $obj->$vkname;
                    $is_success = $this->update_cached_objects($variable_key_val, $obj);
                    $skip_cache = true;

                    // only write to node if memcache works
                    if ($is_success) {
                        //$is_success = NodeWrapper::save($obj, $this->tbl_name, $this->get_db_name($variable_key_val), $this->node_transport_type, $this->node_command, $this->shard_group);
                    }

                    // write to db if memcache or node fail
                    if (!$is_success) {
                        $is_success = parent::save($obj, false);
                    }
                } else {
                    $is_success = parent::save($obj, $force_insert);
                }
            }
        } catch (DbException $dbe) {
            $is_success = false;
            if ($dbe->error_type == 'duplicate') {
                warn(__FILE__, "FIXING DUPLICATE KEY INSERT ISSUE: " . $dbe->message, CoreWarnTypes::MEMCACHE_ERROR);
                $duplicate_key_error = true;
            } else {
                throw $dbe;
            }
        }

        $variable_key_val = $obj->$vkname;

        if (!$skip_cache) {
            // For now we will still write to memcached on db errors, will revisit if this turns out to be bad in production
            if ($this->write_through_cache && !$duplicate_key_error) {
                //debug(__FILE__, get_class($this) .  ":  MD MODEL - MC save() Write through cache happening for: " . json_encode($obj));
                $is_success = $is_success && $this->update_cached_objects($variable_key_val, $obj);
            } else {
                //debug(__FILE__, get_class($this) .  ":  MD MODEL - MC save() Invalidate cache happening for: " . $variable_key_val);
                $this->invalidate_cached_keys($variable_key_val);
            }
        }

        if(property_exists($obj, 'time_updated')) {
            $GLOBALS['updatetimes'][$class_name][] = $obj->time_updated;
        }
        return $is_success;
    }

    protected function save_as_update_where($obj, $extra_where_params, $async_write = FALSE, $skip_cache = FALSE) {

        // add current backtrace and debuginfo into arrays
        $class_name = get_class($this);
        $GLOBALS['backtraces'][$class_name][] = debug_backtrace();
        $debug_save = array();
        if(property_exists($obj, 'player_id')) {
            $debug_save['player_id'] = $obj->player_id;
        }
        if(property_exists($obj, 'version')) {
            $debug_save['version'] = $obj->version;
        }
        $GLOBALS['debuginfo'][$class_name][] = $debug_save;

        $vkname = $this->variable_query_key;
        $variable_key_val = $obj->$vkname;

        if ($async_write) {
//            debug(__FILE__, "Async write for object: " . $this->tbl_name);
        //            parent::save_as_update_where($obj, $extra_where_params);
            $is_success = true;
        } else {
            //parent::save_as_update_where($obj, $extra_where_params);
            if(!$skip_cache && $this->write_through_cache && $this->use_node_mysql) {
                //debug(__FILE__, get_class($this) .  ":  MC MODEL - MC node_save_as_update_where() DB Write happening for: " . json_encode($obj));
                $is_success = $this->update_cached_objects($variable_key_val, $obj);
                $skip_cache = true;

                // only write to node if memcache works
                if ($is_success) {
                    //$is_success = NodeWrapper::save($obj,$this->tbl_name,$this->get_db($obj)->get_db_name(), $this->node_transport_type, $this->node_command, $this->shard_group);
                }

                // write to db if memcache or node fail
                if(!$is_success) {
                    //debug(__FILE__, get_class($this) .  ":  MC MODEL - MC node_fail_save_as_update_where() DB Write happening for: " . json_encode($obj));
                    $is_success = parent::save_as_update_where($obj, $extra_where_params);
                }
            } else {
                //debug(__FILE__, get_class($this) .  ":  MC MODEL - MC save_as_update_where() DB Write happening for: " . json_encode($obj));
                $is_success = parent::save_as_update_where($obj, $extra_where_params);
            }
        }

        if (!$skip_cache) {
            if ($this->write_through_cache) {
                //debug(__FILE__,  get_class($this) . ":  MC MODEL - MC save_as_update_where() Write through cache happening for: " . json_encode($obj));
                $is_success = $this->update_cached_objects($variable_key_val, $obj);
            } else {
                //debug(__FILE__,  get_class($this) . ":  MC MODEL - MC save_as_update_where() Invalidate cache happening for: " . $variable_key_val);
                $this->invalidate_cached_keys($variable_key_val);
            }
        }

        if(property_exists($obj, 'time_updated')) {
            $GLOBALS['updatetimes'][$class_name][] = $obj->time_updated;
        }

        return $is_success;
    }

}

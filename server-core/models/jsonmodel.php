<?php
//require_once(COREPATH . '/cache/CacheDataStore.php');
require_once(COREPATH . '/cache/LocalCacheDataStore.php');
require_once(COREPATH . 'database/DbConnWrapper.php');
require_once(COREPATH . '/models/memcachedmodel.php');
require_once(dirname(__FILE__) .'/JsonPlayerSchema.php');

class JsonModel extends ShardedModel
{
    protected $variable_query_key;
    protected $local_cache;
    protected $cache;
    protected $node_transport_type;
    protected $node_command;

    /* shard_group:  The database shard group
     * variable_query_key:  The table column that will be the primary key that the data will be cached on.  Most of the time it should be id
     *
     * NOTE:  All cacheable tables must have an id field as their primary key, and a version field if they want to use write-through caching.
     * TODO:  Enforce that somewhere.
     */
    function __construct($shard_group, $variable_query_key)
    {
        parent::__construct($shard_group, $variable_query_key);
        $this->variable_query_key = $variable_query_key;
        //$this->cache = new CacheDataStore('player_obj');
        $this->local_cache = LocalCacheDataStore::get_cache('player_obj');
        $this->node_command = '';
        $this->node_transport_type = 'udp';
    }

    /**
     * Create and initialize the fields of the object
     * @return mixed
     */
    public function create() {
        $class_name = get_class($this);
        $object_name = substr($class_name, 0, -5);
        $obj = new $object_name();
        $this->initialize_object($obj, $obj::$_json_fields);
        $this->initialize_object($obj, $obj::$_db_fields);
        return $obj;
    }


    /**
     * optimized call to get multiple objects by ids at a time
     * First try to get the objects from memcached using getmulti, then fetch the object from db by partition
     * @param unknown_type $partition_values
     */
    public function get_where_in_cached($partition_values) {
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
                $object = $this->expand_db_json_to_object($qdata);
                $cache_key = str_replace('%s', $qkey, $static_cache_key);
                //$this->cache->put($cache_key, $object);
                $found_array[$qkey] = $object;
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

    /**
     * Try to get the object from cache, if not found get the object from database then caches the object
     * @param unknown_type $query_params
     * @return unknown
     */
    public function get_object($query_params) {

        $success = false;
        $key = $this->static_cache_key($query_params);

        $val = $query_params[$this->variable_query_key];
        $key = str_replace("%s", $val, $key);
        $obj = NULL;

        $results = NULL;
        $obj = $this->cache_get($key);
        //debug(__FILE__, get_class($this) . ":  jsonmodel get_object called: " . json_encode($query_params) . ' key: ' . $key);
        if (is_null($obj)) {
            $results = parent::get_where($query_params);
            if (sizeof($results) > 1) {
                error(__FILE__, "Multiple rows returned for single get query: " . $this->tbl_name . "  " . json_encode($query_params));
            } else if (count($results) === 0){
                return null;
            }
            if (sizeof($results) > 0) {
                $obj = $this->expand_db_json_to_object($results[0]);
            }
            $this->cache_put($key, $obj);
        } else if (isset($obj->payload) && $obj->payload) {
            $obj = $this->expand_db_json_to_object($obj);
            $this->cache_put($key, $obj);
        }

        return $obj;
    }


    /**
     * returns an array of object
     * @param array $query_params
     * @param int $limit
     * @return array|multitype:unknown
     */
    public function get_where($query_params, $limit=NULL) {
        $key = $this->static_cache_key($query_params);
        $val = $query_params[$this->variable_query_key];
        $key = str_replace("%s", $val, $key);
        $obj = NULL;

        $results = NULL;
        $obj = $this->cache_get($key);
        if (is_null($obj)) {
            $json_db_record_in_list = parent::get_where($query_params, $limit);
            // If the record doesn't exist yet, return an empty list.
            if (sizeof($json_db_record_in_list) === 0) {
                return $json_db_record_in_list;
            } else if (sizeof($json_db_record_in_list) > 1) {
                error(__FILE__, "Multiple rows returned for single get query: " . $this->tbl_name . "  " . json_encode($query_params));
            } else {
                // Expand the payload field to an object
                $json_db_record = $json_db_record_in_list[0];
                $expanded_object = $this->expand_db_json_to_object($json_db_record);
                $this->cache_put($key, $expanded_object);
                return array($expanded_object);
            }
        } else {
            return array($obj);
        }
    }


    public function save(&$obj, $force_insert=False, $async_write = FALSE)
    {
        $schema = $this->get_json_schema($obj);
        $schema->version += 1;
        $schema->time_updated = date('Y-m-d H:i:s');
        $obj->version = $schema->version;

		error_log("var shard:". $this->variable_query_key ."saving jason model".print_r($schema, true));
        // add current backtrace and debuginfo into arrays
        $class_name = get_class($this);

        // force id to be equal to variable_query_key for player shard tables
            if (!isset($obj->id) && $this->shard_group == 'player' && isset($obj->{$this->variable_query_key})) {
                $obj->id = $obj->{$this->variable_query_key};
                $schema->id = $obj->id;
                $force_insert = true;
            }

        $skip_cache = false;
        $vkname = $this->variable_query_key;
        $variable_key_val = $obj->$vkname;

        try {
            if (isset($obj->id) && !$force_insert && $async_write) {
                $is_success = true;
            } else {
                //debug(__FILE__,  get_class($this) . ":  JSON MODEL - MC save() DB Write happening for: " . json_encode($obj));
                if (isset($obj->id) && !$force_insert) {
                    //only update cache if id is set and user_node is true

                    $is_success = $this->update_cached_objects($variable_key_val, $obj);
                    $skip_cache = true;

                    // only write to node if memcache works
                    if ($is_success) {
                        //$is_success = NodeWrapper::save($schema, $this->tbl_name, $this->get_db_name($variable_key_val), $this->node_transport_type, $this->node_command, $this->shard_group);
                    }

                    // write to db if memcache or node fail
                    if (!$is_success) {
                        $is_success = parent::save($schema, false);
                    }
                } else {
                    $is_success = parent::save($schema, $force_insert);
                }
            }
        } catch (DbException $dbe) {
            $is_success = false;
            if ($dbe->error_type == 'duplicate') {
                warn(__FILE__, "FIXING DUPLICATE KEY INSERT ISSUE: " . $dbe->message, CoreWarnTypes::MEMCACHE_ERROR);
                $duplicate_key_error = true;
                $this->invalidate_cached_keys($variable_key_val);
            } else {
                throw $dbe;
            }
        }

        // if this is the first time we're saving the object make sure to update the object's id
        if (!isset($obj->id))
        {
            $obj->id = $schema->id;
        }

        if (!$skip_cache) {
            // For now we will still write to memcached on db errors, will revisit if this turns out to be bad in production
            //debug(__FILE__, get_class($this) .  ":  MD MODEL - MC save() Write through cache happening for: " . json_encode($obj));
            $is_success = $is_success && $this->update_cached_objects($variable_key_val, $obj);
        }

        return $is_success;
    }

    // used by player model, version is preupdated before the call to this function
    protected function save_as_update_where(&$obj, $extra_where_params, $async_write = FALSE, $skip_cache = FALSE)
    {
        $schema = $this->get_json_schema($obj);
        $schema->time_updated = date('Y-m-d H:i:s');

        $vkname = $this->variable_query_key;
        $variable_key_val = $obj->$vkname;

        if ($async_write) {
            //debug(__FILE__, "Async write for object: " . $this->tbl_name);
            $is_success = true;
        } else {
            if (!$skip_cache && NODEJS_MYSQL) {
                //debug(__FILE__, get_class($this) .  ":  JSON MODEL - MC node_save_as_update_where() DB Write happening for: " . json_encode($obj));
                $is_success = $this->update_cached_objects($variable_key_val, $obj);
                $skip_cache = true;

                // only write to node if memcache works
                if ($is_success) {
                    //$is_success = NodeWrapper::save($schema,$this->tbl_name,$this->get_db($obj)->get_db_name(), $this->node_transport_type, $this->node_command, $this->shard_group);
                }

                // write to db if memcache or node fail
                if (!$is_success) {
                    //debug(__FILE__, get_class($this) .  ":  JSON MODEL - MC node_fail_save_as_update_where() DB Write happening for: " . json_encode($obj));
                    $is_success = parent::save_as_update_where($schema, $extra_where_params);
                }
            } else {
                //debug(__FILE__, get_class($this) .  ":  JSON MODEL - MC save_as_update_where() DB Write happening for: " . json_encode($obj));
                $is_success = parent::save_as_update_where($schema, $extra_where_params);
            }
        }

        if (!$skip_cache) {
            $is_success = $this->update_cached_objects($variable_key_val, $obj);
        }

        return $is_success;
    }

    protected function invalidate_cached_keys($variable_query_value) {
        $key = $this->static_cache_key(array($this->variable_query_key => $variable_query_value));
        $key = str_replace("%s", $variable_query_value, $key);
        if (MEMCACHE_ON) {
            $this->cache->delete(str_replace("%s", $variable_query_value, $key));
        }
        $this->local_cache->delete(str_replace("%s", $variable_query_value, $key));
    }

    public function remove($obj) {
        if (ENVIRONMENT == "prod" && get_class($obj) == "Player") {
            error(__FILE__, "remove not allowed on player in production");
            return false;
        }

        $schema = $this->get_json_schema($obj);
        $vkname = $this->variable_query_key;
        $variable_key_val = $obj->$vkname;
        $this->invalidate_cached_keys($variable_key_val);
        return parent::remove($schema);
    }

    public function force_remove_row_for_obj($obj)
    {
        if (ENVIRONMENT !== "prod") {
            $schema = $this->get_json_schema($obj);
            $vkname = $this->variable_query_key;
            $variable_key_val = $obj->$vkname;
            $this->invalidate_cached_keys($variable_key_val);
            return parent::remove($schema);
        } else {
            error(__FILE__, "not allowed to call force_remove_row_for_obj() in production");
            return false;
        }
    }

    protected function cache_put($key, $data, $ttl = 0) {
        $this->local_cache->put($key, $data, $ttl);
        if (MEMCACHE_ON) {
            $is_success = $this->cache->put($key, $data, $ttl);
            return $is_success;
        }
        return true;
    }

    protected function cache_get($key) {
        $data = $this->local_cache->get($key);
        if (!is_null($data)) {
            return $data;
        }
        if (MEMCACHE_ON) {
            $data = $this->cache->get($key);
            if (!is_null($data)) {
                $this->local_cache->put($key, $data);
            }
        }
        return $data;
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

    private function create_get_multi_keys($partition_values) {
        $keys = array();
        foreach ($partition_values as $pv) {
            $k = str_replace("%s", $pv, $this->static_cache_key(array()));
            $keys[$k] = $pv;
        }
        return $keys;
    }


    private function update_cached_objects($variable_query_value, $updated_obj) {

        if (!MEMCACHE_ON) {
            return true;
        }

        $is_success = false;
        $key = $this->static_cache_key(array($this->variable_query_key => $variable_query_value));

        $full_key = str_replace("%s", $variable_query_value, $key);

        // Remove local cache version
        $this->local_cache->delete($full_key);

        // Check if the object is stored within an array, if so, then look for it in the array and update accordingly
        $old_cached_data = $this->cache->get($full_key);

        // Only update the cache if the object was already in the cache.
        // If it isn't, then it's safer to let it get lazy loaded the next time it is read
        if (! is_null($old_cached_data)) {
            // Only update if the version is greater than previous version
            if ($old_cached_data->version < $updated_obj->version) {
                $is_success = $this->cache->put($full_key, $updated_obj);
                $this->local_cache->put($full_key, $updated_obj);
            } else {
                $this->handle_stale_object($full_key, $old_cached_data, $updated_obj);
                $is_success = false;
            }

        }

        return $is_success;
    }


    private function handle_stale_object($full_key, $old_obj, $updated_obj) {
        $class_name = get_class($this);
        warn(__FILE__, "Did not update object because version is stale: " . $full_key .  ",  cached version: " . $old_obj->version .
                ",  update version: " . $updated_obj->version . " cache time: " . $old_obj->time_updated . " update time:" . $updated_obj->time_updated . " classname: " . $class_name, CoreWarnTypes::STALE_ERROR);
    }


    private function expand_db_json_to_object($data)
    {
        $obj = $this->create();

        $db_fields = $obj::$_db_fields;
        $json_fields = $obj::$_json_fields;

        // Set all the fields from the json table record (id, player_id, time_created, etc) on the object
        //  except for the payload field which is exempted
        foreach($data as $dkey => $dval)
        {
            if (array_key_exists($dkey, $db_fields))
            {
                $field_type = $db_fields[$dkey][0];
                $this->set_field($obj, $field_type, $dkey, $dval);
            }
        }

        // Now expand out the payload column and set all of its sub-data on the object we are expanding into
        $payload = json_decode($data->payload);

        foreach($payload as $dkey => $dval)
        {
            if (array_key_exists($dkey, $json_fields))
            {
                $field_type = $json_fields[$dkey][0];
                $this->set_field($obj, $field_type, $dkey, $dval);
            }
            else
            {
                warn(__FILE__, get_class($this) . ':  FORGOT TO ADD TO json_fields: ' . $dkey, CoreWarnTypes::JSON_MEMCACHE_ERROR);
                $obj->$dkey = $dval;
            }
        }
        return $obj;
    }

    /**
     *
     * @param unknown_type $obj
     */
    private function get_json_schema(&$obj)
    {
        if (is_null($obj))
        {
            return NULL;
        }
        $db_fields = $obj::$_db_fields;
        $json_fields = $obj::$_json_fields;

        $schema = new JsonPlayerSchema($db_fields);

        // Set the default fields for all models
        foreach($obj as $dkey => $dval)
        {
            if (array_key_exists($dkey, $db_fields))
            {
                $field_type = $db_fields[$dkey][0];
                $this->set_field($schema, $field_type, $dkey, $dval);
            }
        }

        // create the obj that we will json encode, ignoring the fields we don't want to encode
        $payload_obj = array();
        foreach($obj as $field => $value)
        {
            if (array_key_exists($field, $json_fields))
            {
                $payload_obj[$field] = $value;
            }
        }
        // json encode the fields we want and store it as the payload
        $schema->payload = json_encode($payload_obj);

        return $schema;
    }


    protected function _map_to_obj($data) {
        $obj = $this->create();
        if (! property_exists($obj, '_db_fields')) {
            error(__FILE__, get_class($this) . ":   MASSIVE ERROR, _db_fields DOESN'T EXIST ON " . get_class($obj));
        }

        $db_fields = $obj::$_db_fields;

        foreach(get_object_vars($data) as $dkey => $dval) {
            if (array_key_exists($dkey, $db_fields)) {
                $field_type = $db_fields[$dkey];
                if (!is_null($dval) && $field_type[0] == 'int') {
                    $obj->$dkey = (int) $dval;
                } else if (!is_null($dval) && $field_type[0] == 'float') {
                    $obj->$dkey = (float) $dval;
                } else if (!is_null($dval) && $field_type[0] == 'string') {
                    $obj->$dkey = (string) $dval;
                } else if (!is_null($dval) && $field_type[0] == 'array') {
                    $obj->$dkey = (array) $dval;
                } else {
                    $obj->$dkey = $dval;
                }
            } else {
                //debug(__FILE__, get_class($this) . ':  mapping a field: (' . $dkey .') that is not in db_fields');
                $obj->$dkey = $dval;
            }
        }
        return $obj;
    }


    private function set_field($object, $field_type, $field_key, $field_value) {
        if ($field_key == 'payload') {
            // Never set payload as a field on the object (instead its expanded and it's sub-data are set on the object)
        } else if (is_null($field_value)) {
            $object->$field_key = $field_value;
        } else if ($field_type == 'int') {
            $object->$field_key = (int) $field_value;
        } else if ($field_type == 'float') {
            $object->$field_key = (float) $field_value;
        } else if ($field_type == 'string') {
            $object->$field_key = (string) $field_value;
        } else if ($field_type == 'array') {
            $temp = array();
            foreach($field_value as $key =>  $value) {
                $temp[$key] = $value;
            }
            $object->$field_key = $temp;
        }  else {
            $object->$field_key = $field_value;
        }
    }

}
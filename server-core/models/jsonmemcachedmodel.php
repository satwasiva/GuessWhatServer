<?php
require_once(COREPATH . '/cache/CacheDataStore.php');
require_once(COREPATH . '/cache/LocalCacheDataStore.php');
require_once(COREPATH . 'database/DbConnWrapper.php');
require_once(COREPATH . '/models/memcachedmodel.php');
require_once(dirname(__FILE__) .'/JsonPlayerSchema.php');
require_once(APPPATH . 'config/game_config.php');

class JsonMemcachedModel extends MemcachedModel
{
	protected $unique_field;

    /* shard_group:  The database shard group
     * variable_query_key:  The table column that will be the primary key that the data will be cached on.  Most of the time it should be player_id
     * supported_extra_params:  Extra 'where' clause parameters that may be used to cache lists of data for a player
     * unique_field: the key used to check if the object is unique
     * write_through:  flag to determine if writes to update the cache directly instead of invalidating a cached item
     *
     * NOTE:  All cacheable tables must have an id field as their primary key, and a version field if they want to use write-through caching.
     * TODO:  Enforce that somewhere.
     */
    function __construct($shard_group, $variable_query_key, $supported_extra_params, $unique_field=NULL)
    {
        parent::__construct($shard_group, $variable_query_key, $supported_extra_params);
        $this->unique_field = $unique_field;
    }

    public function create() {
        $class_name = get_class($this);
        $object_name = substr($class_name, 0, -5);
        $obj = new $object_name();
        $this->initialize_object($obj, $obj::$_json_fields);
        $this->initialize_object($obj, $obj::$_db_fields);
        return $obj;
    }

    protected function _map_to_obj($data) {
        $obj = new JsonPlayerSchema();

        if (! property_exists($obj, '_db_fields')) {
            error(__FILE__, get_class($this) . ":   MASSIVE ERROR, _db_fields DOESN'T EXIST ON " . get_class($obj));
        }

        $db_fields = $obj::$_db_fields;
        //debug(__FILE__, get_class($this) . ":  JSON MODEL _map_to_obj data = " . json_encode($data) . "  db_fields = " . json_encode($obj::$_db_fields));

        foreach(get_object_vars($data) as $dkey => $dval) {
            if (array_key_exists($dkey, $db_fields)) {
                $field_type = $db_fields[$dkey];
                if (!is_null($dval) && $field_type[0] == "int") {
                    $obj->$dkey = (int) $dval;
                } else if (!is_null($dval) && $field_type[0] == "float") {
                    $obj->$dkey = (float) $dval;
                } else if (!is_null($dval) && $field_type[0] == "string") {
                    $obj->$dkey = (string) $dval;
                } else if (!is_null($dval) && $field_type[0] == "array") {
                    $obj->$dkey = (array) $dval;
                } else {
                    $obj->$dkey = $dval;
                }
            } else {
            	//debug(__FILE__, get_class($this) . ":  mapping a field: (" . $dkey .") that is not in db_fields");
                $obj->$dkey = $dval;
            }
        }
        return $obj;
    }

    protected function _map_to_objlist($query_results) {
        $obj_list = array();
        foreach($query_results as $row) {
        	$map_to_obj = $this->_map_to_obj($row);
            $obj_list[] = $map_to_obj;
        }

        //debug(__FILE__, get_class($this) . ":  JSON MODEL _map_to_objlist data = " . json_encode($obj_list));
        return $obj_list;
    }


    protected function expand_db_json($data)
    {
    	if(isset($this->unique_field))
    	{
            //debug(__FILE__, "MAP_TO_OBJECT_LIST: " . json_encode($data));
    		return $this->expand_db_json_to_object_list($data);
    	}
    	else
    	{
            //debug(__FILE__, "MAP_TO_OBJECT: " . json_encode($data));
    		return $this->expand_db_json_to_object($data);
    	}
    }


    private function set_field($object, $field_type, $field_key, $field_value) {
        if($field_key == "payload") {
            // Never set payload as a field on the object (instead its expanded and it's sub-data are set on the object)
        } else if(!is_null($field_value) && $field_type == "int") {
            $object->$field_key = (int) $field_value;
        } else if(!is_null($field_value) && $field_type == "float") {
            $object->$field_key = (float) $field_value;
        } else if(!is_null($field_value) && $field_type == "string") {
            $object->$field_key = (string) $field_value;
        } else if(!is_null($field_value) && $field_type == "array") {
            $object->$field_key = (array) $field_value;
        } else {
            $object->$field_key = $field_value;
        }
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
        	if(array_key_exists($dkey, $db_fields))
            {
                $field_type = $db_fields[$dkey][0];
                $this->set_field($obj, $field_type, $dkey, $dval);
            }
        }

        // Now expand out the payload column and set all of its sub-data on the object we are expanding into
        $payload = json_decode($data->payload);

        foreach($payload as $dkey => $dval)
        {
			if(array_key_exists($dkey, $json_fields))
            {
                $field_type = $json_fields[$dkey][0];
                $this->set_field($obj, $field_type, $dkey, $dval);
            }
            else
            {
                warn(__FILE__, get_class($this) . ":  FORGOT TO ADD TO json_fields: " . $dkey, CoreWarnTypes::JSON_MEMCACHE_ERROR);
                $obj->$dkey = $dval;
            }
        }
        return $obj;
    }

	private function expand_db_json_to_object_list($json_db_record)
	{
        $obj = $this->create();
        $db_fields = $obj::$_db_fields;
        $json_fields = $obj::$_json_fields;

        $object_list = array();

        $payload = json_decode($json_db_record->payload);

        foreach($payload as $entry)
        {
        	$obj = $this->create();

            // We use database_id as a field for all objects so that if we perform a save on an individual object in
            //  a json-encoded model, we know how to look up the json-encoded record from the db
        	$obj->database_id = $json_db_record->id;

            // Set all the fields from the json table record (id, player_id, time_created, etc) on the object
            //  except for the payload field which is exempted
	        foreach($json_db_record as $dkey => $dval)
	        {
	        	if(array_key_exists($dkey, $db_fields))
	            {
                    $field_type = $db_fields[$dkey][0];
                    $this->set_field($obj, $field_type, $dkey, $dval);
	            }
	        }

            // Now expand out the payload column and set all of its sub-data on the object we are expanding into
        	foreach($entry as $dkey => $dval)
        	{
	        	if(array_key_exists($dkey, $json_fields))
	            {
	                $field_type = $json_fields[$dkey][0];
                    $this->set_field($obj, $field_type, $dkey, $dval);

	                // swap in the unique ID for the ID field so objects will have different id fields when sent to the client
                    //  this allows us to identify a specific object within the json-encoded payload set of objects
	                if($dkey == $this->unique_field)
	                {
	                	$obj->id = $dval;
	                	$obj->$dkey = $dval;
	                }
	            }
	            else
	            {
                    warn(__FILE__, get_class($this) .  ":  FORGOT TO ADD TO json_fields: " . $dkey, CoreWarnTypes::JSON_MEMCACHE_ERROR);
	                $obj->$dkey = $dval;
	            }
        	}
        	$object_list[] = $obj;
        }
        return $object_list;
    }


    private function get_json_schema(&$obj, $delete = false)
    {
    	if(isset($this->unique_field))
    	{
    		$schema = $this->get_json_schema_from_object_list($obj, $delete);
    	}
    	else
    	{
    		$schema = $this->get_json_schema_from_object($obj, $delete);
    	}

        $schema->time_updated = date('Y-m-d H:i:s').substr((string)microtime(), 1, 7);
        return $schema;
    }

    private function get_json_schema_from_object(&$obj, $delete = false)
    {
        if($delete)
        {
            throw new ShardException(get_class($this) . ": get_json_schema_from_object() not supported for deletes");
        }
    	if(is_null($obj))
    	{
    		return NULL;
    	}
    	$db_fields = $obj::$_db_fields;
    	$json_fields = $obj::$_json_fields;

    	$schema = new JsonPlayerSchema($db_fields);

    	// Set the default fields for all models
        foreach($obj as $dkey => $dval)
        {
        	if(array_key_exists($dkey, $db_fields))
            {
                $field_type = $db_fields[$dkey][0];
                $this->set_field($schema, $field_type, $dkey, $dval);
            }
        }

        if(!$delete)
        {
	    	// create the obj that we will json encode, ignoring the fields we don't want to encode
	    	$payload_obj = array();
	    	foreach($obj as $field => $value)
	    	{
	    		if(array_key_exists($field, $json_fields))
	    		{
	    			$payload_obj[$field] = $value;
	    		}
	    	}
	    	// json encode the fields we want and store it as the payload
	    	$schema->payload = $this->json_encode($payload_obj);
        }

    	return $schema;
    }

	private function get_json_schema_from_object_list_arr(&$arr, $delete = false)
    {
    	if(is_null($arr) || !is_array($arr) || empty($arr))
    	{
    		return NULL;
    	}

        // re-keys the array to start first element at index 0
        $arr = array_values($arr);

    	$db_fields = $arr[0]::$_db_fields;
    	$json_fields = $arr[0]::$_json_fields;

    	$unique_field = $this->unique_field;

    	$schema = new JsonPlayerSchema($db_fields);
        $schema->version = 0; // Initialized but it's replaced later with the version from the json db record

	    // Set the json-schema fields first (id, player_id, etc)
        foreach($arr[0] as $dkey => $dval)
        {
            // do not set id field of schema (it is meant to be the id of the entire row, NOT the first element's unique id)
        	if(array_key_exists($dkey, $db_fields) && $dkey != 'id')
            {
                $field_type = $db_fields[$dkey][0];
                $this->set_field($schema, $field_type, $dkey, $dval);
            }
        }

    	// update the json db record's ID field if database_id is set
        // this undoes the swapping of unique ID into the ID field for list-based json tables
        if(isset($arr[0]->database_id))
        {
        	$schema->id = $arr[0]->database_id;
        }

        // create the obj that will be the payload field of the json db record, ignoring the fields we don't want to encode
    	$payload_full_object_list = array();

        $obj_list_and_schema_props = $this->get_object_list_and_schema_props(array($this->variable_query_key => $schema->{$this->variable_query_key}));
        // grab player's full json db record so we can add/update it with the object being saved
        $full_object_list = $obj_list_and_schema_props['object_list'];

        // if there is an id value returned, set it to schema->id
        if(!is_null($obj_list_and_schema_props['id']))
        {
            $schema->id = $obj_list_and_schema_props['id'];
            $schema->version = $obj_list_and_schema_props['version'];
        }

   		$unique_field_to_obj = array();
   		$obj_to_is_update = array();
        $unique_payload = array();

   		foreach($arr as $obj)
   		{
   			$unique_field_to_obj[$obj->$unique_field] = $obj;
   		}

    	// go through payload of the json db record we just queried looking to see if the object we are saving is there for updating
    	$is_update = false;
    	$max_id = 0;
    	foreach($full_object_list as $db_data_obj)
    	{
    		$max_id = max($max_id, $db_data_obj->$unique_field);

            // If schema->id is not set, that means this is a new object in the list we are trying to save, and
            //  thus the database_id will not be assigned within the object.  So instead, get the database_id from
            //  the one of the other objects in the list from the json db record
    		if(!isset($schema->id))
    		{
    			$schema->id = $db_data_obj->database_id;
    		}

            // Really important, extract the version field from the existing json db record so that updates work properly
            if (isset($db_data_obj->version) && $db_data_obj->version > $schema->version) {
                $schema->version = $db_data_obj->version;
            }

            // If we find an object inside the list that has the same unique_field value as the object we're saving,
            //  that means this is an update to an existing object (or a delete)
    		//if($db_data_obj->$unique_field == $obj->$unique_field)
    		if(array_key_exists($db_data_obj->$unique_field, $unique_field_to_obj))
            {
    			// if delete, continue to next element without adding it to payload_full_object_list
    			if($delete) continue;

    			$obj_to_is_update[serialize($unique_field_to_obj[$db_data_obj->$unique_field])] = 1;

                // Now just update the fields of the existing object with the values from the one we are saving
    			//foreach($obj as $field => $value)
    			foreach($unique_field_to_obj[$db_data_obj->$unique_field] as $field => $value)
    			{
    				// do not update unique field
    				if(array_key_exists($field, $json_fields) && ($field != $unique_field))
    				{
    					$db_data_obj->$field = $value;
    				}
    			}
            }

            if (isset($unique_payload[$db_data_obj->$unique_field])) {
                continue;
            }
            $unique_payload[$db_data_obj->$unique_field] = 1;

            // Make a copy of db_data_obj that we will later include in the object list stored in the payload field
            //  of the json db record.  We are copying it over so that we can support removal of json fields.
            $payload_obj = array();
            foreach($db_data_obj as $field => $value)
            {
                if(array_key_exists($field, $json_fields))
                {
                    $payload_obj[$field] = $value;
                }
            }

            $payload_full_object_list[] = $payload_obj;
    	}

    	// If this is a new object being added, we need to assign it a unique id.  Our current algorithm is to
        //  give it a unique id that is 1 more than the max unique id already seen
    	if(!$delete)
    	{
    		$payload_obj = array();

    		foreach($arr as $obj)
    		{
    			if(!array_key_exists(serialize($obj), $obj_to_is_update))
    			{
		    		foreach($obj as $field => $value)
		    		{
		    			if(array_key_exists($field, $json_fields))
		    			{
		    				$payload_obj[$field] = $value;
		    			}
		    		}
		    		if(!isset($payload_obj[$this->unique_field]))
		    		{
		    			$payload_obj[$this->unique_field] = $max_id+1;
		    			$max_id++;

		    			// make sure to update the obj->id to be the unique id for list based json encoded tables
		    			$obj->id = $payload_obj[$this->unique_field];

                        // set the unique field of the obj to be consistent with what has been stored in the DB
                        $obj->$unique_field = $payload_obj[$unique_field];
		    		}
		    		$payload_full_object_list[] = $payload_obj;
    			}
    		}
		}

    	// json encode the updated object list and store it as the payload
    	$schema->payload = $this->json_encode($payload_full_object_list);
        $schema->time_updated = date('Y-m-d H:i:s').substr((string)microtime(), 1, 7);

    	return $schema;
    }

    // This function will build the entire json db record from a single object contained within the payload
    //  This is so we don't need to change any of the existing code which used the memcachedmodel
	private function get_json_schema_from_object_list(&$obj, $delete = false)
    {
    	if(is_null($obj))
    	{
    		return NULL;
    	}

    	$db_fields = $obj::$_db_fields;
    	$json_fields = $obj::$_json_fields;
        $unique_payload = array();

    	$unique_field = $this->unique_field;

    	$schema = new JsonPlayerSchema($db_fields);
        $schema->version = 0; // Initialized but it's replaced later with the version from the json db record

	    // Set the json-schema fields first (id, player_id, etc)
        foreach($obj as $dkey => $dval)
        {
            // do not set id field of schema (it is meant to be the id of the entire row, NOT the obj's unique id)
        	if(array_key_exists($dkey, $db_fields) && $dkey != 'id')
            {
                $field_type = $db_fields[$dkey][0];
                $this->set_field($schema, $field_type, $dkey, $dval);
            }
        }

        // update the json db record's ID field if database_id is set
        // this undoes the swapping of unique ID into the ID field for list-based json tables
        if(isset($obj->database_id))
        {
        	$schema->id = $obj->database_id;
        }

    	// create the obj that will be the payload field of the json db record, ignoring the fields we don't want to encode
    	$payload_full_object_list = array();

   		$obj_list_and_schema_props = $this->get_object_list_and_schema_props(array($this->variable_query_key => $schema->{$this->variable_query_key}));
        // grab player's full json db record so we can add/update it with the object being saved
        $full_object_list = $obj_list_and_schema_props['object_list'];
        // we need to add id and version to schema in case full_object_list is empty
        if(!is_null($obj_list_and_schema_props['id']))
        {
            $schema->id = $obj_list_and_schema_props['id'];
            $schema->version = $obj_list_and_schema_props['version'];
        }

        //debug(__FILE__, "JSON MODEL GET_WHERE: table = " . get_class($this) . "  data = " . json_encode($full_object_list));

    	// go through payload of the json db record we just queried looking to see if the object we are saving is there for updating
    	$is_update = false;
    	$max_id = 0;
    	foreach($full_object_list as $db_data_obj)
    	{
    		$max_id = max($max_id, $db_data_obj->$unique_field);

            // If schema->id is not set, that means this is a new object in the list we are trying to save, and
            //  thus the database_id will not be assigned within the object.  So instead, get the database_id from
            //  the one of the other objects in the list from the json db record
    		if(!isset($schema->id))
    		{
    			$schema->id = $db_data_obj->database_id;
    		}

            // Really important, extract the version field from the existing json db record so that updates work properly
            if (isset($db_data_obj->version) && $db_data_obj->version > $schema->version) {
                $schema->version = $db_data_obj->version;
            }

            // If we find an object inside the list that has the same unique_field value as the object we're saving,
            //  that means this is an update to an existing object (or a delete)
    		if($db_data_obj->$unique_field == $obj->$unique_field)
    		{
    			// if delete, continue to next element without adding it to payload_full_object_list
    			if($delete) continue;

    			$is_update = true;

                // Now just update the fields of the existing object with the values from the one we are saving
    			foreach($obj as $field => $value)
    			{
    				// do not update unique field
    				if(array_key_exists($field, $json_fields) && ($field != $unique_field))
    				{
    					$db_data_obj->$field = $value;
    				}
    			}
    		}

            if (isset($unique_payload[$db_data_obj->$unique_field])) {
                continue;
            }
            $unique_payload[$db_data_obj->$unique_field] = 1;

            // Make a copy of db_data_obj that we will later include in the object list stored in the payload field
            //  of the json db record.  We are copying it over so that we can support removal of json fields.
    		$payload_obj = array();
    		foreach($db_data_obj as $field => $value)
    		{
    			if($field == $unique_field || array_key_exists($field, $json_fields))
    			{
    				$payload_obj[$field] = $value;
    			}
    		}

    		$payload_full_object_list[] = $payload_obj;
    	}

        // If this is a new object being added, we need to assign it a unique id.  Our current algorithm is to
        //  give it a unique id that is 1 more than the max unique id already seen
    	if(!$is_update && !$delete)
    	{
    		//debug(__FILE__, 'adding new object');
    		$payload_obj = array();
    		foreach($obj as $field => $value)
    		{
    			if(array_key_exists($field, $json_fields))
    			{
    				$payload_obj[$field] = $value;
    			}
    		}
    		if(!isset($payload_obj[$unique_field]))
    		{
    			$payload_obj[$unique_field] = $max_id+1;

    			// make sure to update the obj->id to be the unique id for list based json encoded tables
    			$obj->id = $payload_obj[$unique_field];

                // set the unique field of the obj to be consistent with what has been stored in the DB
                $obj->$unique_field = $payload_obj[$unique_field];
    		}
    		$payload_full_object_list[] = $payload_obj;
		}

    	// json encode the updated object list and store it as the payload
    	$schema->payload = $this->json_encode($payload_full_object_list);

    	return $schema;
    }

    public function get_all($id) {
        return $this->get_where(array($this->variable_query_key => $id));
    }

    public function get_by_id($variable_key, $id) {
        $all = $this->get_where(array($this->variable_query_key => $variable_key));
        if (!is_null($all) && !empty($all)) {
            foreach ($all as $one) {
                if ($one->id == $id) {
                    return $one;
                }
            }
        }
        return NULL;
    }

    // Use get_where instead
//    protected function get_by_id($id, $partition_value) {
//        throw new ShardException(get_class($this) . ": get_by_id() not supported for json models");
//    }

    public function get_where_orderby($where_params, $orderby_params, $limit=NULL) {
        throw new ShardException(get_class($this) . ": get_where_orderby() not supported for json models");
    }

    public function get_where_in($partition_values) {
        $results = parent::get_where_in($partition_values);
        //debug(__FILE__,  get_class($this) . ":  JSON get_where_in - BEFORE expand: " . json_encode($results));
        if (sizeof($results) == 0) {
            return $results;
        } else {
            $retval = array();
            $player_values = array_values($results);
            foreach ($player_values as $json_db_record) {
                $expanded_object = $this->expand_db_json($json_db_record);
                $retval[$expanded_object->id] = $expanded_object;
            }
            //debug(__FILE__,  get_class($this) . ":  JSON get_where_in - AFTER expand: " . json_encode($retval));
            return $retval;
        }
    }

    public function get_where_in_new($partition_values) {
        throw new ShardException(get_class($this) . ": get_where_in_new() not supported for json models");
    }

    public function get_where_broadcast($query_params) {
        throw new ShardException(get_class($this) . ": get_where_broadcast() not supported for json models");
    }

    public function get_where_nocache_test($query_params) {
        $json_db_record_in_list = parent::get_where_nocache($query_params);
        return $json_db_record_in_list;
    }

    public function get_where_nocache($query_params) {
        $json_db_record_in_list = parent::get_where_nocache($query_params);

        // If the record doesn't exist yet, return an empty list.  It will get created by the save method.
        if (sizeof($json_db_record_in_list) == 0) {
            return $json_db_record_in_list;
        } else {
            // Expand the payload field and construct into a list of objects
            $json_db_record = $json_db_record_in_list[0];
            $expanded_object_list = $this->expand_db_json($json_db_record);
            if (! is_array($expanded_object_list)) {
                // The get_where functions are expected to return arrays always (even if it's a single result)
                $retval = array();
                $retval[] = $expanded_object_list;
                return $retval;
            }
            return $expanded_object_list;
        }
    }

    public function get_where_orderby_nocache($where_params, $orderby_params, $limit=NULL) {
        throw new ShardException(get_class($this) . ": get_where_orderby_nocache() not supported for json models");
    }

    public function get_where_in_cached($partition_values) {
        $results = parent::get_where_in_cached($partition_values);
        //debug(__FILE__,  get_class($this) . ":  JSON get_where_in_cached - BEFORE expand: " . json_encode($results));
        if (sizeof($results) == 0) {
            return $results;
        } else {
            $return_values = array();
            foreach ($results as $key => $json_db_record_in_list) {
                if (is_array($json_db_record_in_list) && count($json_db_record_in_list) > 0) {
                    $json_db_record = $json_db_record_in_list[0];
                } else {
                    $json_db_record = $json_db_record_in_list;
                }
                $return_values[$key] = $this->expand_db_json($json_db_record);
            }
            //debug(__FILE__,  get_class($this) . ":  JSON get_where_in_cached - AFTER expand: " . json_encode($return_values));
            return $return_values;
        }
    }

    public function get_where_in_cached_single_obj($partition_values) {
        throw new ShardException(get_class($this) . ": get_where_in_cached() not supported for json models");
    }


    public function get_object($query_params) {
        $json_db_object = parent::get_object($query_params);
        if (is_null($json_db_object)) {
            return null;
        }

        $expanded_object = $this->expand_db_json($json_db_object);
        return $expanded_object;
    }

    /*
     * this function wraps get_where()'s response with id and version in case the
     * object_list_ret is empty, so that we still know which row id to update
     */
    private function get_object_list_and_schema_props($query_params, $limit=NULL)
    {
        $id = null;
        $version = null;
        $object_list_ret = array();

        $object_list_ret = $this->get_where($query_params, $limit);
        // we're going to need to return the id if payload (object_list_ret) is empty
        if(empty($object_list_ret))
        {
            // calling parent::get_where again, but shouldn't be a problem due to memcache
            $json_db_record_in_list = parent::get_where($query_params, $limit);

            if (sizeof($json_db_record_in_list) != 0) {
                $json_db_record = $json_db_record_in_list[0];
                $id = $json_db_record->id;
                $version = $json_db_record->version;
            }
        }
    	else
        {
            $version = $object_list_ret[0]->version;
            $id = $object_list_ret[0]->database_id;
        }

        return array('id'=>$id, 'version'=>$version, 'object_list'=>$object_list_ret);
    }

    public function get_where($query_params, $limit=NULL) {
        $json_db_record_in_list = parent::get_where($query_params, $limit);
        //debug(__FILE__, get_class($this) . ":  JSON get_where db_record_in_list = " . json_encode($json_db_record_in_list));

        // If the record doesn't exist yet, return an empty list.  It will get created by the save method.
        if (sizeof($json_db_record_in_list) == 0) {
            return $json_db_record_in_list;
        } else {
            // Expand the payload field and construct into a list of objects
            $json_db_record = $json_db_record_in_list[0];
            $expanded_object_list = $this->expand_db_json($json_db_record);
            if (! is_array($expanded_object_list)) {
                // The get_where functions are expected to return arrays always (even if it's a single result)
                $retval = array();
                $retval[] = $expanded_object_list;
                return $retval;
            }
            return $expanded_object_list;
        }
    }

    // Used to fix a data issue with migration to sharded received_neighbor_request
    public function get_where_test($query_params, $limit=NULL) {
        $json_db_record_in_list = parent::get_where($query_params, $limit);
        return $json_db_record_in_list;
    }

	/**
     * Save array of payloads. If array is empty and player_id is specified, insert an empty payload
     */
    public function save_arr(&$arr, $player_id=null, $force_insert=False, $async_write = FALSE)
    {
    	//debug(__FILE__, "save array...player_id:".$player_id);
        if(empty($arr) && !is_null($player_id))
        {
            $obj_list_and_schema_props = $this->get_object_list_and_schema_props(array($this->variable_query_key => $player_id));
            //debug(__FILE__, json_encode($obj_list_and_schema_props));
            $obj = $this->create();
            $db_fields = $obj::$_db_fields;
            $schema = new JsonPlayerSchema($db_fields);
            if(is_null($obj_list_and_schema_props))
            {
            	//debug(__FILE__, "null for player_id: ".$player_id);
                $schema->version = 1;
                $schema->time_created = date('Y-m-d H:i:s');
                $schema->player_id = $player_id;
            }
            else
            {
            	//debug(__FILE__, "id:".$obj_list_and_schema_props['id']);
                if(is_null($obj_list_and_schema_props['version']))
                {
                    $schema->version = 1;
                    $schema->time_created = date('Y-m-d H:i:s');
                }
                else
                {
                    $schema->version = $obj_list_and_schema_props['version'] + 1;
                }
                // TODO: jjh fix time_created
                $schema->time_created = date('Y-m-d H:i:s');
                $schema->payload = $this->json_encode(array());
                $schema->id = $obj_list_and_schema_props['id'];
                $schema->player_id = $player_id; // variable query key?
            }

            if (!$schema->id) {
                $schema->id = $player_id;
                $force_insert = true;
            }

            $schema->time_updated = date('Y-m-d H:i:s').substr((string)microtime(), 1, 7);
            return parent::save($schema, $force_insert, $async_write);
        }

    	if(!isset($this->unique_field))
    	{
    		error(__FILE__, 'Trying to save array without unique field');
    		return false;
    	}

    	$schema = $this->get_json_schema_from_object_list_arr($arr);
    	$schema->version += 1;
        $schema->time_updated = date('Y-m-d H:i:s').substr((string)microtime(), 1, 7);

        foreach($arr as $obj)
        {
	        $obj->version = $schema->version;
        }

        $is_success = parent::save($schema, $force_insert, $async_write);

        foreach($arr as $obj)
        {
	        if(!isset($obj->id))
	        {
	        	$obj->id = $schema->id;
	        }
        }

        return $is_success;
    }

    public function delete_arr($arr, $force_insert=False, $async_write = FALSE)
    {
    	if(!isset($this->unique_field) || is_null($arr) || empty($arr))
    	{
            warn(__FILE__, "delete_arr() not supported for empty arrays", CoreWarnTypes::JSON_MEMCACHE_ERROR);
    		return;
    	}

        $schema = $this->get_json_schema_from_object_list_arr($arr, true);
        $this->perform_delete($schema, $force_insert, $async_write);
    }

	public function delete($obj, $force_insert=False, $async_write = FALSE)
    {
        $schema = $this->get_json_schema($obj, true);
        return $this->perform_delete($schema, $force_insert, $async_write);
    }

    private function perform_delete($schema, $force_insert, $async_write)
    {
        $schema->version += 1;
        $schema->time_updated = date('Y-m-d H:i:s').substr((string)microtime(), 1, 7);
        return parent::save($schema, $force_insert, $async_write);
    }

	public function save(&$obj, $force_insert=False, $async_write = FALSE)
    {
        $schema = $this->get_json_schema($obj);
        $schema->version += 1;
        $obj->version = $schema->version;

        //set the id to player id and force insert
        if (!$schema->id && isset($obj->player_id) && $this->shard_group == 'player') {
            $schema->id = $obj->player_id;
            $force_insert = true;
         }

        $is_success = parent::save($schema, $force_insert, $async_write);

        // if this is the first time we're saving the object make sure to update the object's id
        if(!isset($obj->id))
        {
        	$obj->id = $schema->id;
        }
        if(isset($this->unique_field))
        {
        	//debug_obj(__FILE__, $obj);
        }
        return $is_success;
    }

    public function force_remove_row_for_obj($obj)
    {
        if (ENVIRONMENT != "prod") {
            $schema = $this->get_json_schema($obj);
            $vkname = $this->variable_query_key;
            $variable_key_val = $obj->$vkname;
            parent::invalidate_cached_keys($variable_key_val);
            return parent::remove($schema);
        } else {
            return FALSE;
        }
    }

    // used by player model, version is preupdated before the call to this function
    protected function save_as_update_where(&$obj, $extra_where_params, $async_write = FALSE, $skip_cache = FALSE)
    {
    	$schema = $this->get_json_schema($obj);
        $schema->time_updated = date('Y-m-d H:i:s').substr((string)microtime(), 1, 7);

        //debug(__FILE__,  get_class($this) . ":  JSON parent::save_as_update_where(): " . json_encode($schema));
        $is_success = parent::save_as_update_where($schema, $extra_where_params, $async_write, $skip_cache);

        // if this is the first time we're saving the object make sure to update the object's id
    	if(!isset($obj->id))
        {
        	$obj->id = $schema->id;
        }


        return $is_success;
    }

    protected function json_encode ($obj) {
        return json_encode($obj);
    }

}

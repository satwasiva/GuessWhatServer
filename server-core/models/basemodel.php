<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once(COREPATH . '/models/baseentity.php');
require_once(BASEPATH . 'core/Model.php');

abstract class BaseModel extends CI_Model {
    protected $file_name = __FILE__;
    var $_parent_name = '';
	
    function BaseModel() {
        parent::__construct();
    }

    public function create() {
        $class_name = get_class($this);
        $object_name = substr($class_name, 0, -5);
        $obj = new $object_name();
        $this->initialize_object($obj, $obj::$_db_fields);
        return $obj;
    }

    /**
   	 * Assign Libraries
   	 *
   	 * Creates local references to all currently instantiated objects
   	 * so that any syntax that can be legally used in a controller
   	 * can be used within models.
   	 *
   	 * @access private
   	 */
   	function _assign_libraries($use_reference = TRUE)
   	{
   		$CI =& get_instance();
   		foreach (array_keys(get_object_vars($CI)) as $key)
   		{
   			if ( ! isset($this->$key) AND $key != $this->_parent_name)
   			{
   				// In some cases using references can cause
   				// problems so we'll conditionally use them
   				if ($use_reference == TRUE)
   				{
   					$this->$key = NULL; // Needed to prevent reference errors with some configurations
   					$this->$key =& $CI->$key;
   				}
   				else
   				{
   					$this->$key = $CI->$key;
   				}
   			}
   		}
   	}
	
    protected function _map_to_obj($data) {
        $obj = $this->create();

        $db_fields = $obj::$_db_fields;
        //debug(__FILE__, get_class($this) . ":  BASE MODEL _map_to_obj data = " . json_encode($data) . "  db_fields = " . json_encode($obj::$_db_fields));

        foreach(get_object_vars($data) as $dkey => $dval) {
            if (array_key_exists($dkey, $db_fields)) {
                $field_type = $db_fields[$dkey];
                if (!is_null($dval) && $field_type[0] == "int") {
                    $obj->$dkey = (int) $dval;
                } else if (!is_null($dval) && $field_type[0] == "float") {
                    $obj->$dkey = (float) $dval;
                } else if (!is_null($dval) && $field_type[0] == "string") {
                    $obj->$dkey = (string) $dval;
                } else if (!is_null($dval) && $field_type[0] == "json") {
                    $obj->$dkey = json_decode((string) $dval, true);
                } else {
                    $obj->$dkey = $dval;
                }
            } else {
            	//debug(__FILE__, "mapping a field: (" . $dkey .") that is not in db_fields for table:" . $this->tbl_name . ' and of class:' . get_class($obj));
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

        debug(__FILE__, get_class($this) . ":  BASE MODEL _map_to_objlist data = " . json_encode($obj_list));
        return $obj_list;
    }

    public function get($dbw, $id) {
        debug(__FILE__, "Model.get() called.  id = " . $id);
        $query = $dbw->get_where($this->tbl_name, array('id' => $id));
        if ($query->num_rows() == 1) {
            return $this->_map_to_obj($query->row());
        } else {
            return NULL;
        }
    }

    private function get_db_column_values($obj) {
        $data = array();
        foreach ($obj::$_db_fields as $col => $def) {
            $data[$col] = $obj->$col;
        }
        return $data;
    }

    abstract protected function get_db($obj);

    public function remove($obj) {
        if (! $obj instanceof BaseEntity) {
            throw new Exception("Invalid Object Type");
        }

        $dbw = $this->get_db($obj);
        return $dbw->delete($this->tbl_name, array('id' => $obj->id));
    }

    public function save(&$obj, $force_insert = False) {
        if (! $obj instanceof BaseEntity) {
            throw new Exception("Invalid Object Type");
        }

		error_log('hello'.print_r($obj, true));
        $result = NULL;

        $data = $this->get_db_column_values($obj);
        if (! isset($obj->id) || $force_insert) {

		error_log("Sexy data:".print_r($data, true));
		error_log("Sexy obj:".print_r($obj, true));
            $dbw = $this->get_db($obj);
		error_log("Sexy dbw:".print_r($dbw, true));
            $result = $dbw->insert($this->tbl_name, $data);
		error_log("result:".print_r($result, true));
            if ($result && ! isset($obj->id)) {
                $obj->id = $dbw->insert_id();
		error_log("new insert id".$obj->id);
            }
        } else {
		error_log("forec insert".$force_insert);
		error_log("data".print_r($data, true));
            $dbw = $this->get_db($obj);
            $result = $dbw->update($this->tbl_name, $data, array('id' => $obj->id));
        }

        $is_success = $result ? true : false;

        return $is_success;
    }

    protected function save_as_update_where($obj, $extra_where_params) {
        if (! $obj instanceof BaseEntity) {
            throw new Exception("Invalid Object Type");
        }

		error_log("update Sexy obj:".print_r($obj, true));
        $params = array('id' => $obj->id);
        foreach ($extra_where_params as $key => $val) {
            $params[$key] = $val;
        }

        $data = $this->get_db_column_values($obj);
        $dbw = $this->get_db($obj);
        return $dbw->update($this->tbl_name, $data, $params);
    }


    /**
     * initialize the object to the default value specified by the fields, the second parameter of the array is the default value
     * "none" is set to null or not initalize
     *
     * @param unknown_type $obj
     * @param unknown_type $fields
     */
    protected function initialize_object($obj, $fields = null) {
        if ($fields) {
            foreach ($fields as $name => $field) {
                if (isset($field[1]) && $field[1] !== 'none') {
                    if ($field[0] === 'datetime' && $field[1] === 'now') {
                        $obj->$name = get_date_stamp();
                    } else {
                        $obj->$name = $field[1];
                    }
                }
            }
        }
        return $obj;
    }

}

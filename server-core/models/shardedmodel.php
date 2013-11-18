<?php
require_once(COREPATH . '/models/basemodel.php');
require_once(COREPATH . 'database/DbConnManager.php');

class ShardException extends Exception { }

class ShardedModel extends BaseModel {

    function ShardedModel($shard_group, $partition_db_field) {
        parent::BaseModel();
        $this->partition_db_field = $partition_db_field;
        $this->shard_group = $shard_group;
    }

    protected function get_db($obj) {
        $pf = $this->partition_db_field;
        return $this->db($obj->$pf);
    }

    private function db($partition_value) {
        if (is_null($partition_value)) {
            throw new ShardException("Partition value should not be null, shard group: " . $this->shard_group);
        }

        $CI = & get_instance();
        $CI->load->model('ShardMapModel');
        $shard = $CI->ShardMapModel->get_db_shard($this->shard_group, $partition_value);

        if (is_null($shard)) {
            throw new ShardException("Shard not found for partition value " . $partition_value);
        }

        $dbw = $this->get_db_by_shard($shard);

        return $dbw;
    }

    public function get_db_name($partition_value) {
    	$shard = $this->get_shard($partition_value);
    	return $shard->db_name;
    }
    
    private function first_db() {
        $CI = & get_instance();
        $CI->load->model('ShardMapModel');
        $shards = $CI->ShardMapModel->get_all_shards($this->shard_group);

        if (sizeof($shards) == 0) {
            throw new ShardException("Shard not found for first db in shard group: " . $this->shard_group);
        }

        $dbw = $this->get_db_by_shard($shards[0]);

        return $dbw;
    }

    private function get_db_by_shard($shard) {
        return $this->get_db_by_shard_pos($shard->position);
    }

    private function get_db_by_shard_pos($shard_pos) {
        $dbw = DbConnManager::get_db_conn('shard_' . $this->shard_group . $shard_pos);
        if (is_null($dbw)) {
            throw new ShardException("Database not found for shard " . $shard_pos);
        }

        return $dbw;
    }


    private function get_all_dbs_in_group($shard_group) {
        $CI = & get_instance();
        $CI->load->model('ShardMapModel');
        $shards = $CI->ShardMapModel->get_all_shards($shard_group);
        $dbws = array();
        foreach ($shards as $shard) {
            $dbws[] = $this->get_db_by_shard($shard);
        }

        return $dbws;
    }

    private function get_shard($partition_value) {
        $CI = & get_instance();
        $CI->load->model('ShardMapModel');
        $shard = $CI->ShardMapModel->get_db_shard($this->shard_group, $partition_value);
        if (is_null($shard)) {
            throw new ShardException("Shard not found for partition value " . $partition_value);
        }

        return $shard;
    }

    public function get($id) {
        throw new ShardException("get() not supported without partition value");
    }

    protected function get_by_id($id, $partition_value) {
        $dbw = $this->db($partition_value);
        $query = $dbw->get_where($this->tbl_name, array('id' => $id));
        if ($query->num_rows() == 1) {
            return $this->_map_to_obj($query->row());
        } else {
            return NULL;
        }

    }

    public function get_where($query_params, $limit=NULL) {
        $pv = $this->partition_db_field;
        if (! array_key_exists($pv, $query_params)) {
            throw new ShardException("Partition value " . $pv . " not in query parameters");
        }
        $dbw = $this->db($query_params[$pv]);

        $query = $dbw->get_where($this->tbl_name, $query_params, $limit);
        //debug(__FILE__, get_class($this) . ":  SHARDED MODEL GET_WHERE 1: table = " . get_class($this) . "  data = " . json_encode($query->result()));
        $results = $this->_map_to_objlist($query->result());
        //debug(__FILE__, get_class($this) . ":  SHARDED MODEL GET_WHERE 2: table = " . get_class($this) . "  data = " . json_encode($results));


        return $results;
    }
    
    public function get_where_orderby($where_params, $orderby_params, $limit=NULL)
    {
    	$pv = $this->partition_db_field;
        if (! array_key_exists($pv, $where_params))
        {
            throw new ShardException("Partition value " . $pv . " not in query parameters");
        }
        $dbw = $this->db($where_params[$pv]);

        $dbw->from($this->tbl_name);
        if(!is_null($where_params))
        {
        	$dbw->where($where_params);
        }
        if(!is_null($orderby_params))
        {
        	foreach ($orderby_params as $k => $v)
        	{
       	        $dbw->order_by($k, $v);
            }
        }
        $query = $dbw->get('', $limit);
        $results = $this->_map_to_objlist($query->result());
        return $results;
    }

    /**
     * Filter for bad db shards
     * @param $partition_values
     * @return array
     */
    protected function split_into_db_shards($partition_values) {
        $shard_to_pvs = array();
        require_once(COREPATH . 'database/DbConnStat.php');
        $shards = DbConnStat::get_instance()->filter_db_shards($this->shard_group);
        $count = count($shards);
        foreach ($partition_values as $pv) {
            $found = false;
            for($i = 0; $i < $count; ++$i) {
                if ($shards[$i]->contains($pv)) {
                    $shard = $shards[$i];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                continue;
            }
            if (array_key_exists($shard->position, $shard_to_pvs)) {
                $shard_to_pvs[$shard->position][] = $pv;
            } else {
                $shard_to_pvs[$shard->position] = array($pv);
            }
        }
        return $shard_to_pvs;
    }
    
    public function get_where_in($partition_values) {
        if (! is_array($partition_values)) {
            throw new ModelException("Invalid input for get_where_in query");
        }

        if (sizeof($partition_values) == 0) {
            return array();
        }

        $results_map = array();
        $pfield = $this->partition_db_field;

        $shards_to_pvs = $this->split_into_db_shards($partition_values);
        foreach ($shards_to_pvs as $shard_pos => $pv_subset) {

            $dbw = $this->get_db_by_shard_pos($shard_pos);
            $dbw->where_in($this->partition_db_field, $pv_subset);
            $query = $dbw->get($this->tbl_name);
            foreach ($query->result() as $row) {
                $obj = $this->_map_to_obj($row);
                if (array_key_exists($obj->$pfield, $results_map)) {
                    $subresult = $results_map[$obj->$pfield];
                    if (is_array($subresult)) {
                        $subresult[] = $obj;
                    } else {
                        $sr_array = array();
                        $sr_array[] = $subresult;
                        $sr_array[] = $obj;
                        $results_map[$obj->$pfield] = $sr_array;
                    }
                } else {
                    $results_map[$obj->$pfield] = $obj;
                }
            }
        }
        return $results_map;

    }
    
    public function get_where_in_new($partition_values) {
    	if (! is_array($partition_values) || sizeof($partition_values) == 0) {
    		throw new ModelException("Invalid input for get_where_in query");
    	}
    
    	$results_map = array();
    	$pfield = $this->partition_db_field;
    
    	$shards_to_pvs = $this->split_into_db_shards($partition_values);
    	foreach ($shards_to_pvs as $shard_pos => $pv_subset) {
    
    		$dbw = $this->get_db_by_shard_pos($shard_pos);
    		$dbw->where_in($this->partition_db_field, $pv_subset);
    		$query = $dbw->get($this->tbl_name);
    		foreach ($query->result() as $row) {
    			$obj = $this->_map_to_obj($row);
    			$results_map[$obj->$pfield] = $obj;
    		}
    	}
    	return $results_map;
    }

    /*
     * Executes a where query on all shards.  Be VERY CAREFUL!
     */
    public function get_where_broadcast($query_params) {
        // rng - TODO:  Can this be done in parallel?
        $dbws = $this->get_all_dbs_in_group($this->shard_group);

        // rng - TODO:  Should we change this to return a mapping of db to results?
        $results = array();
        foreach ($dbws as $dbw) {
            $query = $dbw->get_where($this->tbl_name, $query_params);
            $query_results = $this->_map_to_objlist($query->result());
            foreach ($query_results as $obj) {
                $results[] = $obj;
            }
        }
        return $results;
    }

}
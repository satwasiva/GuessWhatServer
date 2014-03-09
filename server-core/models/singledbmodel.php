<?php
require_once(COREPATH . '/database/DbConnManager.php');
require_once(COREPATH . '/models/basemodel.php');

class SingleDbModel extends BaseModel
{
    function __construct($db_config_name)
	{
        parent::__construct();
        $this->database_config_name = $db_config_name;
    }

    protected function get_db($obj)
	{
		error_log("config name:" . $this->database_config_name);
        return DbConnManager::get_db_conn($this->database_config_name);
    }

    protected function get_default_db()
	{
        return $this->get_db(NULL);
    }

    public function get_where($query_params, $limit = NULL, $offset = NULL)
	{
		error_log("query" . print_r($query_params, true));
        $dbw = $this->get_default_db();

        $query = $dbw->get_where($this->tbl_name, $query_params, $limit, $offset);
        $results = $this->_map_to_objlist($query->result());
        return $results;
    }

    /**
     * get_where_orderby
     * @param array $query_params
     * @param array $orderby
     * @param string $limit
     * @param string $offset
     * @return array of objects order by the orderby field
     */
    public function get_where_orderby($query_params, $orderby, $limit = null, $offset = null)
	{
        $dbw = $this->get_default_db();
        $dbw->from($this->tbl_name);

        if (!is_null($query_params) || !empty($query_params))
		{
            $dbw->where($query_params);
        }

        if (!is_null($limit))
		{
            $dbw->limit($limit, $offset);
        }

        foreach ($orderby as $k => $v)
		{
            $dbw->order_by($k, $v);
        }

        $query = $dbw->get();

        $results = $this->_map_to_objlist($query->result());

        return $results;
    }

    /**
     * @param $set
     * @param mixed $update_set if this is null, it is default to set. ex: array('id' => array('value' => 100, 'escape' => true), 'version' => array('value' => 'version + 1', 'escape' => false));
     * @return mixed
     */
    public function insert_or_update($set, $update_set = null)
	{
        $dbw = $this->get_default_db();
        $result = $dbw->insert_or_update($this->tbl_name, $set, $update_set);
        return $result;
    }

    protected function delete($where_params)
	{
        $dbw = $this->get_default_db();
        $dbw->delete($this->tbl_name, $where_params);
    }
}

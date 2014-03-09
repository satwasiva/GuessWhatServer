<?php
require_once(COREPATH . '/models/DbShard.php');
require_once(COREPATH . '/models/staticmodel.php');

class ShardMapModel extends StaticModel
{
    protected $tbl_name = 'db_shard';

    function __construct()
	{
        parent::__construct("default");
    }

    public function create()
	{
        $obj = new DbShard();
        return $obj;
    }

    public function get_db_shard($shard_group, $partition_value)
	{
        $shards = parent::get_where_orderby(array("shard_group" => $shard_group), array("position" => "asc"));
        foreach ($shards as $shard)
		{
            //log_message('info', "Checking shard " . $shard->position . " " . $shard->start_id . " " . $shard->end_id);
            if ($shard->contains($partition_value))
			{
				error_log("Shard info:".print_r($shard, true));
                return $shard;
            }
        }

        return NULL;
    }

    public function get_all_shards($shard_group)
	{
        return parent::get_where_orderby(array("shard_group" => $shard_group), array("position" => "asc"));
    }
}

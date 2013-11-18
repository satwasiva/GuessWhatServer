<?php
require_once(COREPATH . '/models/baseentity.php');

class DbShard extends BaseEntity {

    public function db_fields() {
        return self::$_db_fields;
    }

    public static $_db_fields = array(
        "id"           => array("int", "none", false),
        "db_name"      => array("string", "none", false),
        "shard_group"  => array("string", "none", false),
        "position"     => array("bigint", "none", false),
        "start_id"     => array("bigint", "none", false),
        "end_id"       => array("bigint", "none", false),
        "is_alive"     => array("int", "none", false),
        "weight"       => array("int", "none", false),
    );

    public $id;
    public $db_name;
    public $shard_group;
    public $position;
    public $start_id;
    public $end_id;
    public $is_alive;
    public $weight;

    function DbShard() {
        parent::BaseEntity();
    }

    public function contains($partition_value) {
        return ($partition_value >= $this->start_id && $partition_value < $this->end_id);
    }

}


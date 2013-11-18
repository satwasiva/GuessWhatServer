<?php

/**
 * This manager allows us to only query for a map once per request. This helps in cases where static or player data is
 * needed in a child function that runs in a loop of a parent function (or maybe even grandparent function).
 */
class CachedMapManager
{
    private static $instance;
    private $maps;

    private function CachedMapManager() {
        $this->maps = array();
    }

    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new CachedMapManager();
        }
        return self::$instance;
    }

    /**
     * @param $path
     * @param $model
     * @param null $player_id
     * @return mixed
     *
     * 3 parameters: path to model, model name, and (optional) player_id [if it is a player model]
     * returns the map back -- either cached or gets it for the first time
     */
    public function get_map($path, $model, $player_id = NULL) {
        $key = $this->_get_key($model, $player_id);
        if (array_key_exists($key, $this->maps)) {
            return $this->maps[$key];
        } else {
            $CI = & get_instance();
            $CI->load->model($path . "/" . $model);
            $map = $CI->$model->load_map($player_id);
            $this->maps[$key] = $map;
            return $map;
        }
    }

    private function _get_key($model, $player_id) {
        return $model . ":" . $player_id;
    }
}

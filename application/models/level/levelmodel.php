<?php

require_once(COREPATH . 'models/staticmodel.php');
require_once(APPPATH . 'models/level/Level.php');

class LevelModel extends StaticModel {

    protected $tbl_name = 'level';

    function __construct() {
        parent::__construct("default");
    }

    public function get_level_id_to_level_map($use_ab_test_game_data)
    {
        $levels = $this->load_all($use_ab_test_game_data);
        $level_id_to_level_map = array();
        foreach ($levels as $level)
        {
            $level_id_to_level_map[$level->id] = $level;
        }
        return $level_id_to_level_map;
    }

    public function load_all($use_ab_test_game_data = true)
    {
        $levels = parent::get_all();
        //if($use_ab_test_game_data){
        //    $CI = & get_instance();
        //    $CI->load->model('ab_test/ABTestGameDataModel');
        //    $levels = $CI->ABTestGameDataModel->update_data_with_changes($levels, "level");
        //}
        return $levels;
    }

    public function get_level($level)
    {
        $results = parent::get_where(array('level' => $level));
        if (sizeof($results) > 0)
        {
            return $results[0];
        }
        else
        {
            //If the level details do not exist. Then we use the capped off level properties.
            $levels = $this->load_all();
            usort($levels, array("Level",'sort_desc'));
            return $levels[0];
        }
    }

    public function get_level_for_experience($xp_amt)
    {
        $levels = $this->load_all();
        usort($levels, array("Level",'sort_asc'));
        if ($xp_amt < 0)
        {
            //Return the lowest Level available
            return $levels[0];
        }
        foreach ($levels as $level)
        {
            if ($xp_amt >= $level->exp_required && $xp_amt < ($level->exp_required + $level->exp_increment))
            {
                //debug(__FILE__, "Level: " .  $level->level . " for Experience: " . $xp_amt);
                return $level;
            }
        }
        //In case experience is more than max level experience return the max level.
        return $levels[count($levels) - 1];
    }
    
    public function get_max_level()
    {
        $levels = $this->load_all();
        usort($levels, array("Level",'sort_desc'));
        $max_level = $levels[count($levels) - 1];;
        return $max_level;
    }
}

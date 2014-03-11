<?php

require_once(APPPATH . 'models/bonus/SpecialBonus.php');

class PlayerSpecialBonus {

    public $id;
    public $last_collect_time;
    public $current_level;
    public $collect_time_secs;
    public $total_collection;
    public $payout;

    public function __construct($special_bonus, $total_collection_count, $collectable, $last_payout) {
        $sDate = date('Y-m-d H:i:s');
        $this->id = $special_bonus->id;
        $this->last_collect_time = $sDate;
        $this->current_level = $special_bonus->bonus_level;
        if ($collectable)
        {
            $collect_in_secs = 0;
        } else {
            $collect_in_secs = $special_bonus->bonus_collect_in_mins * 60;
        }
        $this->collect_time_secs = $collect_in_secs;
        $this->total_collection = $total_collection_count;
        $this->payout = $last_payout;
    }
}

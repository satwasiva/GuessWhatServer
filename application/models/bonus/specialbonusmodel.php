<?php
require_once(dirname(__FILE__) . '/SpecialBonus.php');
require_once(APPPATH . 'models/player/bonus/PlayerSpecialBonus.php');
require_once(APPPATH . 'models/SharedGameProperties.php');
require_once(COREPATH . 'models/staticmodel.php');

class SpecialBonusModel extends StaticModel {

    protected $tbl_name = 'special_bonus';
    public $special_bonus_key = 'special_bonus';
    public $default_level = 0;

    function SpecialBonusModel()
    {
        parent::StaticModel("default");
    }

    public function create()
    {
        $obj = new SpecialBonus();
        return $obj;
    }

    public function load_all()
    {
        return parent::get_all();
    }

    public function get_special_bonus_for_level($level)
    {
        $special_bonus = $this->load_all();
        foreach ($special_bonus as $bonus)
        {
            if ($level == $bonus->bonus_level)
            {
                return $bonus;
            }
        }
        return NULL;
    }

    private function get_all_special_bonus_levels()
    {
        $levels = array();
        $special_bonus = $this->load_all();
        foreach ($special_bonus as $bonus)
        {
            $levels[] = $bonus->bonus_level;
        }
        sort($levels);
        return $levels;
    }

    private function get_next_special_bonus_level($current_level)
    {
        $all_levels = $this->get_all_special_bonus_levels();
        // If player is at the max level. rest him to the first level.
        if (count($all_levels) > 0 && $all_levels[count($all_levels) - 1] <= $current_level)
        {
            $current_level = $this->default_level;
        }
        $next_level = $current_level + 1;
        debug(__FILE__, "special_BONUS: NextLevel: $next_level CurrLevel: $current_level");
        if (in_array($next_level, $all_levels))
        {
            return $this->get_special_bonus_for_level($next_level);
        } else
        {
            debug(__FILE__, "special_Bonus: Something is wrong in get_next_special_bonus_level routine or in static data!");
            return null;
        }
    }

    private function update_player_special_bonus($player, $special_bonus, $total_special_bonus_collection_so_far, $collectable = false, $last_payout = 0)
    {
        if ($player != null && $special_bonus!= null)
        {
            $CI = & get_instance();
            $CI->load->model('player/game_payload/PlayerGamePayloadModel');
            $player_special_bonus = new PlayerSpecialBonus($special_bonus, $total_special_bonus_collection_so_far, $collectable, $last_payout);
            $CI->PlayerGamePayloadModel->set_special_bonus($player->id, $player_special_bonus);
            debug(__FILE__, "special_BONUS: Updated!" . json_encode($player_special_bonus));
            $result = array('success' => true);
        } else
        {
            debug(__FILE__, "special_BONUS: Either player or special_Bonus object is null! PlayerBonusObj: " . json_encode($special_bonus));
            $result = array('success' => false, 'reason' => 'INVALID_DATA');
        }
        return $result;
    }

    private function check_valid_special_bonus_collection($player_special_bonus)
    {
        $last_collect_time = strtotime($player_special_bonus->last_collect_time);
        $collect_in_secs = $player_special_bonus->collect_time_secs;

        if ($last_collect_time && (time() - ($last_collect_time + $collect_in_secs) >= 0) )
        {
            $valid_collection = true;
        } else
        {
            $valid_collection = false;
            debug(__FILE__, "special_BONUS: EarlyCollect Attempt! Trying to collect before " . time() - ($last_collect_time + $collect_in_secs) . " secs!");
        }
        return $valid_collection;
    }

    private function do_payout($player, $player_special_bonus)
    {
        $payout = $this->calculate_payout($player_special_bonus);

        $player->increase_coins($payout, true);
        return $payout;
    }

    private function calculate_payout($player_special_bonus) {
        $curr_special_bonus = $this->get_special_bonus_for_level($player_special_bonus->current_level);
        if ($curr_special_bonus) {
            return round(($curr_special_bonus->bonus_collect_coins_payout +
                (($curr_special_bonus->bonus_collect_coins_payout * $curr_special_bonus->bonus_collect_coins_percent_payout)/100)));
        } else {
            return 0;
        }
    }

    private function get_base_payout($special_bonus, $player_level)
    {
        $base_payout = 0;
        $base_payout += $special_bonus->bonus_collect_coins_payout;
        if ($player_level > 10) $base_payout += 50;
        if ($player_level > 20) $base_payout += 50;
        if ($player_level > 30)
        {
            $multiplier = ceil(floatval($player_level - 30) / 10.0);
            $base_payout += $multiplier*100;
        }

        return $base_payout;
    }

    public function promote_player_to_next_bonus_level($player, $collectable = false, $last_payout = 0)
    {
        $CI = & get_instance();
        $CI->load->model('player/game_payload/PlayerGamePayloadModel');

        $player_special_bonus = $CI->PlayerGamePayloadModel->get_special_bonus($player->id);

        $current_level = $player_special_bonus->current_level;
        $total_special_bonus_collection_so_far = $player_special_bonus->total_collection + 1;
        $curr_special_bonus = $this->get_special_bonus_for_level($current_level);
        
        /* AnalyticsLogger::log('special_bonus',array (
                'session_id' => $player->session_id,
                'player_level' => $player->level,
                'player_id'=> $player->id,
                'player_is_spender'=> $player->is_spender,
                'player_ab_test' => $player->ab_test,
                'player_sc1_balance' => $player->get_coins(),
                'player_filter' => $player->is_test_account,
                'player_num_game_loads' => $player->num_game_loads,
                'player_percent_level_complete' => $player->percent_level_complete,
                'player_time_created' => $player->time_created,
                'player_total_sc1_earned' => $player->total_coins_earned,
                'player_country_code' => $player->get_country_code(),
                'time_bonus_start' => $player_ap_bonus->last_collect_time,
                'bonus_clock' => ($player_ap_bonus->collect_time_secs / 60),
                'player_special_level' => $player_ap_bonus->current_level,
                'bonus_type' => $ap_bonus_reward_type,
                'sc1_rewarded' => $last_payout)
        );*/

        $special_bonus = $this->get_next_special_bonus_level($current_level);
		
        return $this->update_player_special_bonus($player, $special_bonus, $total_special_bonus_collection_so_far, $collectable, $last_payout);
    }

    public function award_special_bonus($player)
    {
        $CI = & get_instance();
        $CI->load->model('player/game_payload/PlayerGamePayloadModel');
        $player_special_bonus = $CI->PlayerGamePayloadModel->get_special_bonus($player->id);

        if ($this->check_valid_special_bonus_collection($player_special_bonus))
        {
            $payout = $this->do_payout($player, $player_special_bonus);
            $result = $this->promote_player_to_next_bonus_level($player, false, $payout);
            if ($result['success'] === true)
            {
                $this->PlayerModel->save($player);
                $result['payout'] = $payout;
                debug(__FILE__, "special_BONUS: Collected: $payout");
            }
        } else
        {
            $result = array('success' => false, 'reason' => 'INVALID_COLLECT');
        }
        return $result;
    }

}
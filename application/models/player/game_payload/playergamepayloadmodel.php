<?php
require_once(COREPATH . 'models/jsonmodel.php');
require_once(dirname(__FILE__) .'/PlayerGamePayload.php');

class PlayerGamePayloadModel extends JsonModel
{
    protected $tbl_name = 'player_game_payload';

    function PlayerGamePayloadModel()
    {
        parent::JsonModel("player", "id");
    }

    public function init($player_id)
    {
        $player_game_payload = parent::get_object(array("id" => $player_id));
        if (!$player_game_payload)
        {
            $player_game_payload = $this->create();
            $player_game_payload->id = $player_id;
            $player_game_payload->player_id = $player_id;
            $player_game_payload->special_bonus = null;
            $player_game_payload->puzzles_created = json_encode(array());
            $player_game_payload->puzzles_solved = json_encode(array());
            $player_game_payload->puzzles_pending = json_encode(array());

            parent::save($player_game_payload, true);
        }
    }

    public function set_game_payload_field($player_id, $field, $payload)
    {
        $player_game_payload = parent::get_object(array("id" => $player_id));
        if ($player_game_payload && property_exists($player_game_payload, $field))
        {
            $player_game_payload->$field = json_encode($payload);
            $this->save($player_game_payload);
        }
        else
        {
            warn(__FILE__, "GamePayload: No such field exist OR PlayerGame Payload is null! Field: $field");
        }
    }

    public function get_game_payload_by_field($player_id, $field, $return_as_associateve_array = false)
    {
        $player_game_payload = parent::get_object(array("id" => $player_id));
        if ($player_game_payload && property_exists($player_game_payload, $field))
        {
            return json_decode($player_game_payload->$field, $return_as_associateve_array);
        }
        else
        {
            return array();
        }
    }

    public function get_client_game_payload($player_id) {
        $player_game_payload = array();
        $player_game_payload['special_bonus'] = $this->get_adjusted_special_bonus($player_id);
        $player_game_payload['puzzles_created'] = $this->get_or_create_puzzles_created($player_id);
        $player_game_payload['puzzles_solved'] = $this->get_or_create_puzzles_solved($player_id);
        $player_game_payload['puzzles_pending'] = $this->get_or_create_puzzles_pending($player_id);

        return json_encode($player_game_payload);
    }

    private function set_puzzles_created($player_id, $puzzles_created)
    {
        $this->set_game_payload_field($player_id, 'puzzles_created', $puzzles_created);
    }

    private function get_or_create_puzzles_created($player_id)
    {
        $puzzles_created = $this->get_game_payload_by_field($player_id, 'puzzles_created');

        if (is_null($puzzles_created)) {
            $puzzles_created = array();
            $this->set_puzzles_created($player_id, $puzzles_created);
        }

        return $puzzles_created;
    }

    private function add_puzzle_created($player_id, $puzzle_created_to_set)
    {
        $puzzles_created = $this->get_or_create_puzzles_created($player_id);
        $puzzles_created[] = $puzzles_created_to_set;
        $this->set_puzzles_created($player_id, $puzzles_created);
    }

    private function set_puzzles_solved($player_id, $puzzles_solved)
    {
        $this->set_game_payload_field($player_id, 'puzzles_solved', $puzzles_solved);
    }

    private function get_or_create_puzzles_solved($player_id)
    {
        $puzzles_solved = $this->get_game_payload_by_field($player_id, 'puzzles_solved');

        if (is_null($puzzles_solved)) {
            $puzzles_solved = array();
            $this->set_puzzles_created($player_id, $puzzles_solved);
        }

        return $puzzles_solved;
    }

    private function add_puzzle_solved($player_id, $puzzle_solved_to_set)
    {
        $puzzles_solved = $this->get_or_create_puzzles_solved($player_id);
        $puzzles_solved[] = $puzzle_solved_to_set;
        $this->set_puzzles_created($player_id, $puzzles_solved);
    }
	
    private function set_puzzles_pending($player_id, $puzzles_pending)
    {
        $this->set_game_payload_field($player_id, 'puzzles_pending', $puzzles_pending);
    }

    private function get_or_create_puzzles_pending($player_id)
    {
        $puzzles_pending = $this->get_game_payload_by_field($player_id, 'puzzles_pending');

        if (is_null($puzzles_pending)) {
            $puzzles_pending = array();
            $this->set_puzzles_pending($player_id, $puzzles_pending);
        }

        return $puzzles_pending;
    }

    private function add_puzzle_pending($player_id, $puzzle_pending_to_set)
    {
        $puzzles_pending = $this->get_or_create_puzzles_pending($player_id);
        $puzzles_pending[] = $puzzles_created_to_set;
        $this->set_puzzles_pending($player_id, $puzzles_pending);
    }
	
    private function remove_puzzle_pending($player_id, $puzzle_pending_to_remove)
    {
        $puzzles_pending = $this->get_or_create_puzzles_pending($player_id);
		$puzzles_pending = array_splice($puzzles_pending, array_search($puzzle_pending_to_remove), 1);
        $this->set_puzzles_pending($player_id, $puzzles_pending);
    }
	
    private function get_adjusted_special_bonus($player_id) {

        $CI = & get_instance();
        $CI->load->model('player/PlayerModel');

        $player_special_bonus = $this->get_special_bonus($player_id);
        $player = $CI->PlayerModel->get($player_id);  // Get the player again in case it changed during command processing

        $updated_unix_ts = strtotime($player_special_bonus->last_collect_time) - ((int)date('Z') - $player->seconds_from_gmt);
        $local_collect_time = date('Y-m-d H:i:s', $updated_unix_ts);
        //debug(__FILE__, "Special_BONUS: LastCollectTime Server: " . $player_special_bonus->last_collect_time . " Local: $local_collect_time");
        $player_special_bonus->last_collect_time = $local_collect_time;
        return $player_special_bonus;
    }

    public function set_special_bonus($player_id, $special_bonus) {
        $this->set_game_payload_field($player_id, 'special_bonus', $special_bonus);
    }

    public function get_special_bonus($player_id) {

        $ap_bonus = $this->get_game_payload_by_field($player_id, 'special_bonus');

        if (json_encode($ap_bonus) == '{}' || is_null($ap_bonus) || is_int($ap_bonus)) {

            // give player level 1 special bonus
            $CI =& get_instance();
            $CI->load->model('bonus/SpecialBonusModel');
            $CI->load->model('player/game_payload/PlayerGamePayloadModel');

            $special_bonus_level = 1;
            $special_bonus = $CI->SpecialBonusModel->get_special_bonus_for_level($special_bonus_level);

            $collectable = true;
            $total_special_bonus_collection_so_far = 0;
            $payout = $special_bonus->bonus_collect_coins_payout;

            $special_bonus = new PlayerSpecialBonus($special_bonus, $total_special_bonus_collection_so_far, $collectable, $payout);
            $CI->PlayerGamePayloadModel->set_special_bonus($player_id, $special_bonus);
        }

        return $special_bonus;
    }
}
?>
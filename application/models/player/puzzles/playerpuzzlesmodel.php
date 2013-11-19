<?php
require_once(COREPATH . 'models/jsonmodel.php');
require_once(dirname(__FILE__) .'/PlayerPuzzles.php');

class PlayerGamePayloadModel extends JsonModel
{
    protected $tbl_name = 'player_puzzles';

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

            parent::save($player_game_payload, true);
        }
    }

    private function load_by_id($player_id, $unique_id)
    {
        $player_achievements = parent::get_where(array('player_id' => $player_id));
        foreach ($player_achievements as $player_achievement)
        {
            if ($player_achievement->achievement_id == $unique_id)
            {
                return $player_achievement;
            }
        }
        return null;
    }

    public function load_all($player_id, $only_unsent = true)
    {
        $client_player_achievement_objs = array();
        $player_achievements = parent::get_where(array('player_id' => $player_id));
        foreach ($player_achievements as $player_achievement)
        {
            $add_this_achievement = $only_unsent ? !$player_achievement->sent_to_apple : true;
            if ($add_this_achievement)
            {
                $client_player_achievement_objs[] = $this->client_player_achievement_object($player_achievement);
            }
        }
        return $client_player_achievement_objs;
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

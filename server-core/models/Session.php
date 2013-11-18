<?php
require_once(COREPATH . '/models/baseentity.php');

/**
 * This class stores the information needed to reference a player's currently active session on the server.
 *
 * @package DataModel
 *
 *
 */
class Session extends BaseEntity {

    public $_explicitType = 'Session';

    function Session() {
        parent::BaseEntity();
    }

    public $transaction_time;
    public $ios_idfv;
    public $player_id;
    public $invite_code;
    public $session_id;
    public $session_key;
    public $api_version;
    public $client_version;
    public $client_build;
    public $game_name;
    public $game_data_version;
    public $seconds_from_gmt;
}

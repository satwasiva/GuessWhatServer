<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
| -------------------------------------------------------------------
| DATABASE CONNECTIVITY SETTINGS
| -------------------------------------------------------------------
| This file will contain the settings needed to access your database.
|
| For complete instructions please consult the "Database Connection"
| page of the User Guide.
|
| -------------------------------------------------------------------
| EXPLANATION OF VARIABLES
| -------------------------------------------------------------------
|
|   ['hostname'] The hostname of your database server.
|   ['username'] The username used to connect to the database
|   ['password'] The password used to connect to the database
|   ['database'] The name of the database you want to connect to
|   ['dbdriver'] The database type. ie: mysql.  Currently supported:
                 mysql, mysqli, postgre, odbc, mssql, sqlite, oci8
|   ['dbprefix'] You can add an optional prefix, which will be added
|                to the table name when using the  Active Record class
|   ['pconnect'] TRUE/FALSE - Whether to use a persistent connection
|   ['db_debug'] TRUE/FALSE - Whether database errors should be displayed.
|   ['cache_on'] TRUE/FALSE - Enables/disables query caching
|   ['cachedir'] The path to the folder where cache files should be stored
|   ['char_set'] The character set used in communicating with the database
|   ['dbcollat'] The character collation used in communicating with the database
|
| The $active_group variable lets you choose which connection group to
| make active.  By default there is only one group (the "default" group).
|
| The $active_record variables lets you determine whether or not to load
| the active record class
*/

$active_group = "default";
$active_record = TRUE;


if (! function_exists('configure_database')) {
    function configure_database(&$ci_db_configuration, $db_config_name, $db_config) {
        $username = "vamsi";
        $password = "vamrina1";

        $hostname = $db_config[0];
        $database_name = $db_config[1];
        if(isset($db_config[2]) && isset($db_config[3])) {
            $username = $db_config[2];
            $password = $db_config[3];
        }

        $ci_db_configuration[$db_config_name]['hostname'] = $hostname;
        $ci_db_configuration[$db_config_name]['username'] = $username;
        $ci_db_configuration[$db_config_name]['password'] = $password;
        $ci_db_configuration[$db_config_name]['database'] = $database_name;
        $ci_db_configuration[$db_config_name]['dbdriver'] = "mysql";
        $ci_db_configuration[$db_config_name]['dbprefix'] = "";
        $ci_db_configuration[$db_config_name]['pconnect'] = FALSE;
        $ci_db_configuration[$db_config_name]['db_debug'] = TRUE;
        $ci_db_configuration[$db_config_name]['cache_on'] = FALSE;
        $ci_db_configuration[$db_config_name]['autoinit'] = TRUE;
        $ci_db_configuration[$db_config_name]['cachedir'] = "";
        $ci_db_configuration[$db_config_name]['char_set'] = "utf8";
        $ci_db_configuration[$db_config_name]['dbcollat'] = "utf8_general_ci";
    }
}


if (isset($GLOBALS['db_config'])) {
    $db = $GLOBALS['db_config'];
    return ;
}

$unsharded_dbs = array(
    "default" => array("localhost", "guesswhat_static_ios"),
    "default_slave" => array("localhost", "guesswhat_static_ios"),
    "user" => array("localhost", "guesswhat_user_1"),
    "user_slave" => array("localhost", "guesswhat_user_1"),
    "sharded_db" => array("localhost","sharded_db"),
    "sharded_db_slave" => array("localhost","sharded_db"),
    "player_request" => array("localhost", "guesswhat_request"),
    "player_request_slave" => array("localhost", "guesswhat_request")
);


foreach ($unsharded_dbs as $db_cfg_name => $single_db) {
    configure_database($db, $db_cfg_name, $single_db);
}

$unsharded_backup_dbs = array(
    "user_backup" => array("localhost", "guesswhat_user_backup"),
);

foreach ($unsharded_backup_dbs as $db_cfg_name => $single_db) {
    configure_database($db, $db_cfg_name, $single_db);
}

$shard_groups = array(
    "player" => array(
        array("localhost", "guesswhat_player_1"),
        array("localhost", "guesswhat_player_2")
    ),
    "notification" => array(
        array("localhost", "guesswhat_notifications_1")
    )
);

$shard_slave_groups = array(
    "player" => array(
        array("localhost", "guesswhat_player_1"),
        array("localhost", "guesswhat_player_2")
    ),
    "notification" => array(
        array("localhost", "guesswhat_notifications_1")
    )
);

$shard_backup_groups = array(
    "player" => array(
        array("localhost", "guesswhat_player_1_backup")
    )
);

foreach ($shard_groups as $group_name => $group_dbs) {
    for ($i = 0; $i < sizeof($group_dbs); $i++) {
        configure_database($db, 'shard_' . $group_name . $i, $group_dbs[$i]);
    }
}

foreach ($shard_slave_groups as $group_name => $group_dbs) {
    for ($i = 0; $i < sizeof($group_dbs); $i++) {
        configure_database($db, 'shard_slave_' . $group_name . $i, $group_dbs[$i]);
    }
}

foreach ($shard_backup_groups as $group_name => $group_dbs) {
    for ($i = 0; $i < sizeof($group_dbs); $i++) {
        configure_database($db, 'shard_' . $group_name . $i . '_backup', $group_dbs[$i]);
    }
}

$GLOBALS['db_config'] = $db;
$GLOBALS['UNSHARDED_DBS'] = $unsharded_dbs;
$GLOBALS['UNSHARDED_BACKUP_DBS'] = $unsharded_backup_dbs;
$GLOBALS['SHARD_GROUPS'] = $shard_groups;
$GLOBALS['SHARD_SLAVE_GROUPS'] = $shard_slave_groups;
$GLOBALS['SHARD_BACKUP_GROUPS'] = $shard_backup_groups;

/* End of file database.php */
/* Location: ./system/application/config/database.php */

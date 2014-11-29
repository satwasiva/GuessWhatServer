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
        $username = "sagar";
        $password = "\$agar)(*";

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
    "default" => array("localhost", "guesswhat"),
);


foreach ($unsharded_dbs as $db_cfg_name => $single_db) {
    configure_database($db, $db_cfg_name, $single_db);
}


$GLOBALS['db_config'] = $db;
$GLOBALS['UNSHARDED_DBS'] = $unsharded_dbs;

/* End of file database.php */
/* Location: ./system/application/config/database.php */

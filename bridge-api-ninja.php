<?php
/*
Plugin Name: Puente entre sitio y plataforma de prueba
Plugin URI:
Description: Puente entre sitio y plataforma de prueba.
Version: 1.0.0
Author: Desarrollo Bombero Ninja
Author URI:
License: GPLv2 or later
Text Domain:
*/
global $wpdb;

define('PLUGIN_BRIDGE_API_NINJA', __FILE__);
define('PLUGIN_NAME_BRIDGE_API_NINJA', str_replace(".php", "", basename(PLUGIN_BRIDGE_API_NINJA)));
define("ROOT_BRIDGE_API_NINJA", __DIR__ . '/');
define("SRC_BRIDGE_API_NINJA", ROOT_BRIDGE_API_NINJA . 'src/');
define('PREFIX_DB_BRIDGE_API_NINJA', $wpdb->prefix . "bridge_api_ninja_");

require_once SRC_BRIDGE_API_NINJA . 'setting.php';

function bridge_api_ninja_install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $content = file_get_contents(SRC_BRIDGE_API_NINJA . "install.mysql.sql");
    $queries = preg_split("/;/", $content);
    $charset_collate = $wpdb->get_charset_collate();;
    foreach($queries as $query) {
        if(!empty($query)) {
            $query_aux = preg_replace(array(
                "/prefix__/",
                "/CHARACTERSET_COLLATE/"
            ), array(
                PREFIX_DB_BRIDGE_API_NINJA,
                $charset_collate
            ), $query);
            dbDelta($query_aux);
        }
    }
}

function bridge_api_ninja_uninstall() {

}

function bridge_api_ninja_parse_request(&$wp) {
    if ( isset($wp->query_vars["name"]) &&  $wp->query_vars["name"] == "bridge_api_ninja_ws_api") {
        require_once  ROOT_BRIDGE_API_NINJA . '/ws.php';
        wp_die("");
    }
    return $wp;
}

function bridge_api_ninja_create_token( $user_login, $user ) {
    global $wpdb;
    $uniqueid = uniqid();
    require_once SRC_BRIDGE_API_NINJA . "jwt-master/JWT.php";
    $token = array(
        "iss" => $user->ID,
        "exp" => (time() + (3600 * 24)) * 1000
    );
    $token_encode = JWT::encode($token, BridgeApiNinjaSetting::$secret);
    $wpdb->query("INSERT INTO ".PREFIX_DB_BRIDGE_API_NINJA."user_tokens (user_id, reference, token) VALUES ('{$user->ID}', '{$uniqueid}', '{$token_encode}')");
    $_SESSION["api_reference"]= $uniqueid;
}

function bridge_api_ninja_logout( $user_id ) {
    global $wpdb;
    $reference = $_SESSION["api_reference"];
    unset($_SESSION["api_reference"]);
    $wpdb->query("DELETE FROM ".PREFIX_DB_BRIDGE_API_NINJA."user_tokens WHERE user_id = '{$user_id}' AND reference = '{$reference}'");
}

function bridge_api_ninja_start_my_session()
{
    if( !session_id() ) {
        session_start([
        	"read_and_close" => true
        ]);
    }
    bridge_api_ninja_verify();
}

function bridge_api_ninja_verify() {
    if (is_user_logged_in() && !isset($_SESSION["api_reference"])) {
        $user = wp_get_current_user();
        if ($user) {
            _bridge_api_ninja_create_token($user->ID, $user);
        }
    }
}

function _bridge_api_ninja_create_token() {
	$user = wp_get_current_user();
        if ($user) {
            bridge_api_ninja_create_token($user->ID, $user);
        }
}

function bridge_api_ninja_router() {
    $controller = $_REQUEST["controller"];
    $clase = "bridge_api_ninja_ajax_" . $controller;
    if (is_callable($clase)) {  
        call_user_func($clase);
    }
    die;
}

function bridge_api_ninja_ajax_token() {
    global $wpdb;
    _bridge_api_ninja_create_token();
    $result = $wpdb->get_results("SELECT * FROM " . PREFIX_DB_BRIDGE_API_NINJA ."user_tokens WHERE reference = '{$_SESSION["api_reference"]}' LIMIT 1");   
    if ($result && sizeof($result) == 1) {
        $token = $result[0]->token;
    } else {
    }
    echo $token;
}

add_action('init', 'bridge_api_ninja_start_my_session');

add_action( 'parse_request', 'bridge_api_ninja_parse_request' );

add_action('wp_ajax_no_priv_bridge_api_ninja_router', 'bridge_api_ninja_router');
add_action('wp_ajax_bridge_api_ninja_router', 'bridge_api_ninja_router');

function bridge_api_ninja_load_scripts() {
    $url=plugin_dir_url(__FILE__);
    wp_enqueue_script('bridge_api_ninja', $url .'/assets/js/frontend.js', array(), '1.0', false);
    $script = 'var bridge_api_ninja_setting = { "url": "'.admin_url( 'admin-ajax.php' ).'", "nonce": "'.wp_create_nonce( 'bridge_api_ninja-nonce' ).'" };';
    wp_add_inline_script( 'bridge_api_ninja', $script, 'before');
}
add_action( 'wp_enqueue_scripts', 'bridge_api_ninja_load_scripts' );

add_action('wp_login', 'bridge_api_ninja_create_token', 10, 2);
add_action('wp_logout', 'bridge_api_ninja_logout', 10, 1);

register_activation_hook(PLUGIN_BRIDGE_API_NINJA, 'bridge_api_ninja_install');
register_deactivation_hook(PLUGIN_BRIDGE_API_NINJA, 'bridge_api_ninja_uninstall');

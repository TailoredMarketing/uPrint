<?php
/**
* Plugin Name:       uPrint Management Plugin
* Plugin URI:        http://www.tailoredmarketing.co.uk
* Description:       This plugin provides the management functions for the uPrint website
* Version:           1.0.0
* Author:            @DanTaylorSEO
* Author URI:        http://www.tailoredmarketing.co.uk
* License:           GPL-2.0+
* License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
**/
 
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'PLUGIN_DIR', dirname(__FILE__).'/' );
define( 'PLUGIN_URL', plugin_dir_url( __FILE__ ).'/');

require( PLUGIN_DIR.'classes/uprint-management-plugin.class.php' );

uPrintManagementPlugin::getInstance();
register_activation_hook( __FILE__, array( 'uPrintManagementPlugin', 'plugin_activation' ) );
<?php
/**
 * uninstall file for WP-FFPC; uninstall hook does not remove the databse options
 */

/* get the worker file */
include_once ( 'wp-ffpc.php' );

/* run uninstall function */
$wp_ffpc->plugin_uninstall();

?>

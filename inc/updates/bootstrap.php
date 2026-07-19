<?php
/**
 * Mumega Motion update system bootstrap.
 *
 * @package Mumega_Motion
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

$mumega_motion_update_files = array(
	'class-mumega-motion-release-client.php',
	'class-mumega-motion-package-validator.php',
	'class-mumega-motion-backup-store.php',
	'class-mumega-motion-updater.php',
	'class-mumega-motion-dashboard-package-verifier.php',
	'class-mumega-motion-update-api.php',
);

foreach ( $mumega_motion_update_files as $mumega_motion_update_file ) {
	require_once __DIR__ . '/' . $mumega_motion_update_file;
}

$mumega_motion_mcpwp_callback_support = defined( 'MCPWP_SUPPORTS_CUSTOM_TOOL_SCOPE_FILTER' )
	&& true === MCPWP_SUPPORTS_CUSTOM_TOOL_SCOPE_FILTER
	&& defined( 'MCPWP_SUPPORTS_CUSTOM_TOOL_CALLBACKS' )
	&& true === MCPWP_SUPPORTS_CUSTOM_TOOL_CALLBACKS;
$mumega_motion_update_api             = new Mumega_Motion_Update_Api(
	new Mumega_Motion_Updater(),
	new Mumega_Motion_Release_Client(),
	$mumega_motion_mcpwp_callback_support
);
$mumega_motion_update_api->register();

unset( $mumega_motion_update_api, $mumega_motion_mcpwp_callback_support, $mumega_motion_update_file, $mumega_motion_update_files );

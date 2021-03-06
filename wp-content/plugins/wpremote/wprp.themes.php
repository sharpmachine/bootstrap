<?php

/**
 * Return an array of installed themes
 *
 * @return array
 */
function _wprp_get_themes() {

	require_once( ABSPATH . '/wp-admin/includes/theme.php' );

	_wpr_add_non_extend_theme_support_filter();

	// Get all themes
	if ( function_exists( 'wp_get_themes' ) )
		$themes = wp_get_themes();
	else
		$themes = get_themes();

	// Get the active theme
	$active  = get_option( 'current_theme' );

	// Delete the transient so wp_update_themes can get fresh data
	if ( function_exists( 'get_site_transient' ) )
		delete_site_transient( 'update_themes' );

	else
		delete_transient( 'update_themes' );

	// Force a theme update check
	wp_update_themes();

	// Different versions of wp store the updates in different places
	// TODO can we depreciate
	if ( function_exists( 'get_site_transient' ) && $transient = get_site_transient( 'update_themes' ) )
		$current = $transient;

	elseif ( $transient = get_transient( 'update_themes' ) )
		$current = $transient;

	else
		$current = get_option( 'update_themes' );

	foreach ( (array) $themes as $key => $theme ) {

		// WordPress 3.4+
		if ( is_object( $theme ) && is_a( $theme, 'WP_Theme' ) ) {

			$new_version = isset( $current->response[$theme['Template']] ) ? $current->response[$theme['Template']]['new_version'] : null;

			$theme_array = array(
				'Name'           => $theme->get( 'Name' ),
				'active'         => $active == $theme->get( 'Name' ),
				'Template'       => $theme->get_template(),
				'Stylesheet'     => $theme->get_stylesheet(),
				'Screenshot'     => $theme->get_screenshot(),
				'AuthorURI'      => $theme->get( 'AuthorURI' ),
				'Author'         => $theme->get( 'Author' ),
				'latest_version' => $new_version ? $new_version : $theme->get( 'Version' ),
				'Version'        => $theme->get( 'Version' ),
				'ThemeURI'       => $theme->get( 'ThemeURI' )
			);

			$themes[$key] = $theme_array;

		} else {

			$new_version = isset( $current->response[$theme['Template']] ) ? $current->response[$theme['Template']]['new_version'] : null;

			if ( $active == $theme['Name'] )
				$themes[$key]['active'] = true;

			else
				$themes[$key]['active'] = false;

			if ( $new_version ) {

				$themes[$key]['latest_version'] = $new_version;
				$themes[$key]['latest_package'] = $current->response[$theme['Template']]['package'];

			} else {

				$themes[$key]['latest_version'] = $theme['Version'];

			}
		}
	}

	return $themes;
}

/**
 * Install a theme
 *
 * @param  mixed $theme
 * @param array $args
 * @return array|bool
 */
function _wprp_install_theme( $theme, $args = array() ) {

	if ( wp_get_theme( $theme )->exists() )
		return array( 'status' => 'error', 'error' => 'Theme is already installed.' );

	include_once ABSPATH . 'wp-admin/includes/admin.php';
	include_once ABSPATH . 'wp-admin/includes/upgrade.php';
	include_once ABSPATH . 'wp-includes/update.php';

	// Access the themes_api() helper function
	include_once ABSPATH . 'wp-admin/includes/theme-install.php';
	$api_args = array(
		'slug' => $theme,
		'fields' => array( 'sections' => false )
		);
	$api = themes_api( 'theme_information', $api_args );

	if ( is_wp_error( $api ) )
		return array( 'status' => 'error', 'error' => $api->get_error_code() );

	$skin = new WPRP_Theme_Upgrader_Skin();
	$upgrader = new Theme_Upgrader( $skin );

	// The best way to get a download link for a specific version :(
	// Fortunately, we can depend on a relatively consistent naming pattern
	if ( ! empty( $args['version'] ) && 'stable' != $args['version'] )
		$api->download_link = str_replace( $api->version . '.zip', $args['version'] . '.zip', $api->download_link );

	$result = $upgrader->install( $api->download_link );
	if ( is_wp_error( $result ) )
		return $result;
	else if ( ! $result )
		return array( 'status' => 'error', 'error' => 'Unknown error installing theme.' );

	return array( 'status' => 'success' );
}

/**
 * Activate a theme
 *
 * @param mixed $theme
 * @return array
 */
function _wprp_activate_theme( $theme ) {

	if ( ! wp_get_theme( $theme )->exists() )
		return array( 'status' => 'error', 'error' => 'Theme is not installed.' );

	switch_theme( $theme );
	return array( 'status' => 'success' );
}

/**
 * Update a theme
 *
 * @param mixed $theme
 * @return array
 */
function _wprp_update_theme( $theme ) {

	include_once ( ABSPATH . 'wp-admin/includes/admin.php' );

	if ( ! _wprp_supports_theme_upgrade() )
		return array( 'status' => 'error', 'error' => 'WordPress version too old for theme upgrades' );

	_wpr_add_non_extend_theme_support_filter();

	// check for filesystem access
	if ( ! _wpr_check_filesystem_access() )
		return array( 'status' => 'error', 'error' => 'The filesystem is not writable with the supplied credentials' );		

	$skin = new WPRP_Theme_Upgrader_Skin();
	$upgrader = new Theme_Upgrader( $skin );

	// Do the upgrade
	ob_start();
	$result = $upgrader->upgrade( $theme );
	$data = ob_get_contents();
	ob_clean();

	if ( ( ! $result && ! is_null( $result ) ) || $data )
		return array( 'status' => 'error', 'error' => 'file_permissions_error' );

	elseif ( is_wp_error( $result ) )
		return array( 'status' => 'error', 'error' => $result->get_error_code() );

	if ( $skin->error )
		return array( 'status' => 'error', 'error' => $skin->error );

	return array( 'status' => 'success' );

}

/**
 * Delete a theme.
 *
 * @param mixed $theme
 * @return array
 */
function _wprp_delete_theme( $theme ) {
	global $wp_filesystem;

	if ( ! wp_get_theme( $theme )->exists() )
		return array( 'status' => 'error', 'error' => 'Theme is not installed.' );

	include_once ABSPATH . 'wp-admin/includes/admin.php';
	include_once ABSPATH . 'wp-admin/includes/upgrade.php';
	include_once ABSPATH . 'wp-includes/update.php';

	if ( ! _wpr_check_filesystem_access() || ! WP_Filesystem() )
		return array( 'status' => 'error', 'error' => 'The filesystem is not writable with the supplied credentials' );

	$themes_dir = $wp_filesystem->wp_themes_dir();
	if ( empty( $themes_dir ) )
		return array( 'status' => 'error', 'error' => 'Unable to locate WordPress theme directory.' );

	$themes_dir = trailingslashit( $themes_dir );
	$theme_dir = trailingslashit( $themes_dir . $theme );
	$deleted = $wp_filesystem->delete( $theme_dir, true );

	if ( ! $deleted )
		return array( 'status' => 'error', 'error' => sprintf( 'Could not fully delete the theme: %s.', $theme ) );

	// Force refresh of theme update information
	delete_site_transient('update_themes');

	return array( 'status' => 'success' );
}

/**
 * Check if the site can support theme upgrades
 *
 * @todo should probably check if we have direct filesystem access
 * @todo can we remove support for versions which don't support Theme_Upgrader
 * @return bool
 */
function _wprp_supports_theme_upgrade() {

	include_once ( ABSPATH . 'wp-admin/includes/admin.php' );

	return class_exists( 'Theme_Upgrader' );

}

function _wpr_add_non_extend_theme_support_filter() {
	add_filter( 'pre_set_site_transient_update_themes', '_wpr_add_non_extend_theme_support' );
}

function _wpr_add_non_extend_theme_support( $value ) {

    foreach( $non_extend_list = _wprp_get_non_extend_themes_data() as $key => $anon_function ) {

        if ( ( $returned = call_user_func( $non_extend_list[$key] ) ) )
            $value->response[ $returned['Template'] ] = $returned;
    }

    return $value;

}


function _wprp_get_non_extend_themes_data() {

    return array(
        'pagelines' => '_wpr_get_pagelines_theme_data'
    );

}

function _wpr_get_pagelines_theme_data() {

	global $global_pagelines_settings;

	if ( !class_exists( 'PageLinesUpdateCheck' ) )
		return false;

	if ( defined( 'PL_CORE_VERSION' ) )
		$version = PL_CORE_VERSION;
	elseif ( defined( 'CORE_VERSION' ) )
		$version = CORE_VERSION;
	else
		return false;

	$global_pagelines_settings['disable_updates'] = true; // prevent endless loop in PageLinesUpdateCheck::pagelines_theme_check_version()
	$updater = new PageLinesUpdateCheck( $version );
	$update_data = (array) maybe_unserialize( $updater->pagelines_theme_update_check() );

	if ( $update_data && isset( $update_data['package'] ) && $update_data['package'] !== 'bad' ) {
		$update_data['Template'] = 'pagelines'; // needed in _wpr_add_non_extend_theme_support()
		return $update_data;
	}

	return false;

}
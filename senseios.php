<?php
/*
Plugin Name: SenseiOS
Plugin URI:  http://senseilabs.com
Description: WordPress Plugin for SenseiOS' Candidate360 app.
Version:     1.2.0
Author:      Sensei Labs
Author URI:  http://senseilabs.com
License:     (c) 2016, Klick Inc. All rights reserved.
*/

// Block direct access to this file to avoid hackers
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

require('senseios-job-importer.php');

/**
 * Registers the settings used in the SenseiOS options
 *
 * @since 1.0
 *
 */
function sensei_register_settings() {
	register_setting( 'sensei_settings', 'sensei_url', 'sensei_settings_validate_url' ); 
	register_setting( 'sensei_settings', 'sensei_apply_url', 'sensei_settings_validate_apply_url' ); 
	register_setting( 'sensei_settings', 'sensei_vendor', 'sensei_settings_validate_vendor' ); 
	register_setting( 'sensei_settings', 'sensei_token', 'sensei_settings_validate_token' ); 
	register_setting( 'sensei_settings', 'sensei_keep_log' ); 
}

add_action( 'admin_init', 'sensei_register_settings' );

function sensei_settings_validate_url( $input ) {
    if( !isset( $input ) || $input == '' ) {
        add_settings_error( 'sensei_url', 'sensei-error', $input . 'SenseiOS URL cannot be empty', 'error' );
        return false;
    }else{     
    	return apply_filters( 'sensei_settings_validation', $input ); 
    }
}

function sensei_settings_validate_apply_url( $input ) {
	if( !isset( $input ) || $input == '' ) {
        add_settings_error( 'sensei_apply_url', 'sensei-error', $input . 'SenseiOS Apply URL cannot be empty', 'error' );
        return false;
    }else{     
    	return apply_filters( 'sensei_settings_validation', $input ); 
    }
}

function sensei_settings_validate_vendor( $input ) {
    if( !isset( $input ) || $input == '' ) {
        add_settings_error( 'sensei_vendor', 'sensei-error', $input . 'Vendor Code cannot be empty', 'error' );
        return false;
    }else{     
    	return apply_filters( 'sensei_settings_validation', $input ); 
    }
}

function sensei_settings_validate_token( $input ) {
    if( !isset( $input ) || $input == '' ) {
        add_settings_error( 'sensei_token', 'sensei-error', $input . 'API Token cannot be empty', 'error' );
        return false;
    }else{     
    	return apply_filters( 'sensei_settings_validation', $input ); 
    }
}


/**
 * Adds the SenseiOS options and tools menu to the WordPress Admin menu.
 *
 * @since 1.0
 *
 */
function sensei_menus() {
	add_options_page( 'SenseiOS Careers', 'SenseiOS Careers', 'manage_options', 'sensei_settings_menu', 'sensei_settings_page' );
	add_submenu_page( 'tools.php', 'SenseiOS Import Careers', 'Import Careers', 'import', 'sensei_import_menu', 'sensei_import_page');
}

add_action( 'admin_menu', 'sensei_menus' );

/**
 * Displays the SenseiOS settings page in the WordPress Admin options area.
 *
 * @since 1.0
 *
 */
function sensei_settings_page(){
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	
	wp_enqueue_script('jquery');
	wp_enqueue_script( 'sensei_script', plugins_url( 'senseios.js', __FILE__ ) );
	wp_enqueue_style( 'sensei_styles', plugins_url( 'senseios.css', __FILE__ ) );

	$keepLogStatus = '';
	if( get_option('sensei_keep_log') == 'on' ){
		$keepLogStatus = ' checked="checked"';
	}
	
	echo '<div class="wrap">';
	echo '<h2>SenseiOS Careers</h2>';
	echo '<p>This plugin will sync the current job postings from your <a href="http://senseilabs.com" title="SenseiOS" target="_blank">SenseiOS</a> instance to a <a href="/wp-admin/edit.php?post_type=career">Careers Custom Post Type</a>, which you can then use to display them on your site.</p>';
	echo '<div id="sensei_test_login_output"><ul></ul></div>';
	echo '<form name="sensei_settings" method="post" action="options.php">';
	settings_fields( 'sensei_settings' );
	do_settings_sections( 'sensei_settings' );
	echo '<p><label for="sensei_URL">SenseiOS URL:</label><input type="text" id="sensei_url" name="sensei_url" value="' . get_option('sensei_url') . '" placeholder="https://subdomain.domain.com" /></p>';
	echo '<p><label for="sensei_apply_URL">Apply Now URL:</label><input type="text" id="sensei_apply_url" name="sensei_apply_url" value="' . get_option('sensei_apply_url') . '" placeholder="https://subdomain.domain.com/apply/" /></p>';
	echo '<p><label for="sensei_vendor">Vendor Code:</label><input type="text" id="sensei_vendor" name="sensei_vendor" value="' . get_option('sensei_vendor') . '" /></p>';
	echo '<p><label for="sensei_token">API Token:</label><input type="text" id="sensei_token" name="sensei_token" value="' . get_option('sensei_token') . '" /></p>';
	echo '<p><label for="sensei_keep_log"> Logging: </label><input type="checkbox" id="sensei_keep_log" name="sensei_keep_log" ' . $keepLogStatus . '/> Keep an import log (<a href="' . plugins_url( 'senseios-import.log', __FILE__ )  . '" target="_blank">see log</a>)</p>';
	echo '<input id="sensei_save_settings" type="submit" name="sensei_submit" class="button button-primary" value="Test Login and Save">';
	echo '<img id="sensei_test_loading" src="' . plugins_url( 'loading.gif', __FILE__ ) . '" alt="Loading" />';
	echo '</form>';
	echo '<p>The jobs will automatically refresh every hour as long as your website has traffic to it. You can also manually run the <a href="/wp-admin/tools.php?page=sensei_import_menu">Careers Import</a> at any time.</p>';
	echo '</div>';
}

/**
 * Displays the SenseiOS import page in the WordPress Admin tools area.
 *
 * @since 1.0
 *
 */
function sensei_import_page(){
	if ( !current_user_can( 'import' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

	wp_enqueue_style ( 'sensei_styles', plugins_url( 'senseios.css', __FILE__ ) );
	
	echo '<div class="wrap">';
	echo '<h2>SenseiOS Import Careers</h2>';
	echo '</div>';

	try{
		$jobImporter = new JobImporter();
		$jobImporter->outputSummary();

		$current_user = wp_get_current_user();
		sensei_writeLog('Manual import by ' . $current_user->user_login . ': ' . $jobImporter->getEntryCount() . ' entries');
	}catch (Exception $e){
		echo('<div id="sensei_import_message" class="error settings-error notice"><p><strong>Error:</strong> ' . $e->getMessage() . '</p></div>');
	}
}

/**
 * Create the Custom Post Type used to store the Careers records.
 *
 * @since 1.0
 *
 */
function sensei_post_type(){
	$labels = array(
 		'name' => 'Careers',
    	'singular_name' => 'Career',
    	'add_new' => 'Add New Career',
    	'add_new_item' => 'Add New Career',
    	'edit_item' => 'Edit Career',
    	'new_item' => 'New Career',
    	'all_items' => 'All Careers',
    	'view_item' => 'View Careers',
    	'search_items' => 'Search Careers',
    	'not_found' =>  'No Careers Found',
    	'not_found_in_trash' => 'No Careers found in Trash', 
    	'parent_item_colon' => '',
    	'menu_name' => 'Careers',
    );

	$args = array(  
        'rewrite' => array('with_front' => false, 'slug' => 'careers'),
        'labels'             => $labels,
        'public'         => true,
        'publicly_queryable' => true,
        'show_ui'        => true,
        'show_in_menu'   => true,
        'query_var'      => true,
        'rewrite'        => true,
        'capability_type'    => 'post',
        'has_archive'    => true,
        'hierarchical'   => false,
        'menu_position'  => 4,
        'menu_icon'		 => 'dashicons-universal-access-alt',
        'taxonomies'     => array('category'),
        'supports'       => array('title', 'editor', 'thumbnail', 'excerpt',)
        );
    register_post_type( 'career', $args );
}

add_action( 'init', 'sensei_post_type' );

/**
 * Add the Custom Fields to the Custom Post Type to display the Job info
 *
 * @since 1.0
 *
 */
function sensei_register_meta(){
	add_meta_box('sensei_meta', 'SenseiOS Job Info', 'sensei_meta_info', 'career', 'side', 'high');
}

/**
 * Display the Custom Fields in the Post edit page
 *
 * @since 1.0
 *
 */
function sensei_meta_info(){
	global $post;
	$custom = get_post_custom( $post->ID );
	$jobDescriptionID = $custom['JobDescriptionID'][0];
	$isHiring = $custom['IsHiring'][0];
	$jobCity = $custom['JobCity'][0];
	$jobTypeName = $custom['JobTypeName'][0];
	$jobTypeCategoryName = $custom['JobTypeCategoryName'][0];
	$location = $custom['LocationName'][0];

	echo('<ul>');
	echo('<li><strong>Hiring:</strong> ' . ($isHiring ? 'Yes' : 'No') . '</li>');
	echo('<li><strong>Job ID:</strong> ' . $jobDescriptionID . '</li>');
	echo('<li><strong>Job Type:</strong> ' . $jobTypeName . '</li>');
	echo('<li><strong>Job Category:</strong> <a href="/wp-admin/edit.php?category_name=' . sanitize_title($jobTypeCategoryName) . '&post_type=career">' . $jobTypeCategoryName . '</a></li>');
	echo('<li><strong>Job City:</strong> ' . $jobCity . '</li>');
	echo('<li><strong>Location:</strong> ' . $location . '</li>');
	echo('</ul>');
}

add_action( 'add_meta_boxes', 'sensei_register_meta' );

/**
 * Register the Locations Custom Taxonomy
 *
 * @since 1.0
 *
 */
function sensei_locations_handler() {
	$labels = array(
		'name'                       => _x( 'Locations', 'Taxonomy General Name', 'text_domain' ),
		'singular_name'              => _x( 'Location', 'Taxonomy Singular Name', 'text_domain' ),
		'menu_name'                  => __( 'Locations', 'text_domain' ),
		'all_items'                  => __( 'All Locations', 'text_domain' ),
		'parent_item'                => __( 'Parent Location', 'text_domain' ),
		'parent_item_colon'          => __( 'Parent Location:', 'text_domain' ),
		'new_item_name'              => __( 'New Location', 'text_domain' ),
		'add_new_item'               => __( 'Add Location', 'text_domain' ),
		'edit_item'                  => __( 'Edit Location', 'text_domain' ),
		'update_item'                => __( 'Update Location', 'text_domain' ),
		'view_item'                  => __( 'View Location', 'text_domain' ),
		'separate_items_with_commas' => __( 'Separate Location with commas', 'text_domain' ),
		'add_or_remove_items'        => __( 'Add or remove Location', 'text_domain' ),
		'choose_from_most_used'      => __( 'Choose from the most Locations', 'text_domain' ),
		'popular_items'              => __( 'Popular Location', 'text_domain' ),
		'search_items'               => __( 'Search Location', 'text_domain' ),
		'not_found'                  => __( 'Location Not Found', 'text_domain' ),
		'no_terms'                   => __( 'No Locations', 'text_domain' ),
		'items_list'                 => __( 'Locations list', 'text_domain' ),
		'items_list_navigation'      => __( 'Locations list navigation', 'text_domain' ),
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => false,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => true,
	);
	register_taxonomy( 'sensei_locations', array( 'career' ), $args );
}

add_action( 'init', 'sensei_locations_handler' );

/**
 * Cron to run the import on an hourly basis
 *
 * @since 1.0
 *
 */
function sensei_schedule_cron(){
	wp_schedule_event( time(), 'hourly', 'sensei_hourly_import' );
	sensei_writeLog('Activated! Scheduled cron');
}

add_action('sensei_hourly_import', 'sensei_cron');

/**
 * Runs hourly to trigger the import
 *
 * @since 1.0
 *
 */
function sensei_cron(){
	try{
		$jobImporter = new JobImporter();
		sensei_writeLog('Scheduled import: ' . $jobImporter->getEntryCount() . ' entries');
	}catch (Exception $e){
		sensei_writeLog('Scheduled import failed: ' . $e->getMessage());
	}
}

/**
 * Clears the scheduled cron
 *
 * @since 1.0
 *
 */
function sensei_clear_cron(){
	wp_clear_scheduled_hook( 'sensei_cron' );
	sensei_writeLog('Deactivated! Cleared cron');
}

register_activation_hook(__FILE__, 'sensei_schedule_cron');
register_deactivation_hook(__FILE__, 'sensei_clear_cron');

/**
 * Writes to the plugin's log file
 *
 * @since 1.0
 *
 */
function sensei_writeLog($message){
	if(get_option('sensei_keep_log') == 'on'){
		file_put_contents( __DIR__ . '/senseios-import.log' , date( 'M j Y, g:i:s A' ) . ' - ' . $message . "\n", FILE_APPEND);
	}
}

function sensei_apply_now_buttons($postID){	
	$existingLocations = wp_get_object_terms($postID, 'sensei_locations', array('orderby' => 'term_id')); 
	$locationIDMapping = unserialize(get_post_meta($postID)['Locations'][0]);
	if($existingLocations != null){
		echo '<div class="sensei-apply-now-buttons">';
		foreach($existingLocations as $existingLocation){			
			$senseiLocationID = $locationIDMapping[$existingLocation->term_id];
			echo '<div class="sensei-apply-now-container">';
			sensei_apply_now_button($postID, $senseiLocationID, $existingLocation->name);
			echo '</div>';
		}
		echo '</div>';
	} else {
		sensei_apply_now_button($postID, 0, '');
	}
}

function sensei_apply_now_button($postID, $locationID, $locationName){
	$custom = get_post_custom( $postID );
	$jobDescriptionID = $custom['JobDescriptionID'][0];
	$applyUrl = get_option('sensei_apply_url');
	$printedLocationName = '';
	if ($locationName != null || $locationName != ''){
		$printedLocationName = ': ' . $locationName;
	}
	$the_button = '<a class="sensei-apply-now-button" href="' .
		$applyUrl . '?r=1&l=1&lid=' . $locationID . '/#/apply-now/' . $jobDescriptionID .
		'">Apply Now ' . $printedLocationName . '</a>';
	echo $the_button;
}

?>
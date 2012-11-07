<?php

/***************************************
 * phpAce Bulk Image Converter V1.0
 * Copyright 2008 Sam Yapp
 * http://www.phpace.com/products/bulk-image-converter/
 *
 * Support Forum: http://forum.phpace.com/
 *
 * You may not sell or distribute this 
 * script without the express written 
 * permission of the author.
 ***************************************/

error_reporting(E_ALL);

define('PABI_INCLUDED', true);

if( !isset($_SESSION) ) {
	session_start();
}

if( !file_exists(dirname(__FILE__).'/config.php') ) {
	die('No configuration file. Please ensure you have followed the installation guide.');
}

require_once dirname(__FILE__) . '/config.php';

if( !is_writable($pabi_config['temp_dir']) ) {
	die('Cannot write to temporary directory. Please check your configuration file and directory permissions.');
}

require_once dirname(__FILE__) . '/functions.php';

// check that maximum upload size permitted by server configuration
$pabi_post_max_size = (int)ini_get('post_max_size');
$pabi_upload_max_filesize = (int)ini_get('upload_max_filesize');

$pabi_config['max_image_filesize'] = min($pabi_config['max_image_filesize'],min($pabi_upload_max_filesize,$pabi_post_max_size));

error_reporting($pabi_config['error_reporting']);

$pabi_translations = pabi_load_translations();

// clean up
if( mt_rand(0,$pabi_config['cleanup_frequency']) == 0 ) {
	pabi_clean_old_dirs();
}

$pabi_errors = array();

if( isset($_POST['pabi_convert']) ) {
	foreach( pabi_get_options() as $pabi_name => $pabi_value ) {
		if( isset($_POST['pabi_options'][$pabi_name]) ) {
			pabi_set_option($pabi_name, get_magic_quotes_gpc() ? stripslashes($_POST['pabi_options'][$pabi_name]) : $_POST['pabi_options'][$pabi_name]);
		}
		else{
			pabi_set_option($pabi_name, 0);
		}
	}
	if( isset($_FILES['pabi_file']) ) {
		if( $_FILES['pabi_file']['error'] != UPLOAD_ERR_NO_FILE || pabi_count_images() == 0 ) {
			set_time_limit(0);
			pabi_process_upload($_FILES['pabi_file']);
		}
	}

	if( pabi_count_images() > 0 ) {
		pabi_convert();
	}

}
elseif( isset($_POST['pabi_clear']) ) {
	pabi_clean_user_dir();
}
elseif( isset($_POST['pabi_download']) ) {
	set_time_limit(0);
	pabi_download();
}

include dirname(__FILE__) . '/' . $pabi_config['template'];



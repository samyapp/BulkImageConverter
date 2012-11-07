<?php

if( !defined('PABI_INCLUDED') ) {
	die();
}
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

define('PABI_OK',0);
define('PABI_ERR_FILESIZE', 1);
define('PABI_ERR_TYPE', 2);
define('PABI_ERR_FILENAME', 3);

function pabi_load_translations()
{
	global $pabi_config;
	include dirname(__FILE__) . '/translations/default.php';
	$trans = $pabi_lang;
	if( $pabi_config['language'] != '' ) {
		include dirname(__FILE__) . '/translations/'.$pabi_config['language'].'.php';
	}
	// unlike array_merge, using + preserves numeric keys
	return $pabi_lang + $trans;
}

function pabi_translate($token, $escape = true)
{
	global $pabi_translations;
	settype($token,'string');
	if( isset($pabi_translations[$token] ) ) {
		$token = $pabi_translations[$token];
	}
	if( $escape ) {
		$token = htmlspecialchars($token);
	}
	return $token;
}

function pabi_option($name)
{
	$options = pabi_get_options();
	if( isset($options[$name]) ) {
		return $options[$name];
	}
	else{
		return null;
	}
}

function pabi_setting($name)
{
	global $pabi_config;
	if( isset($pabi_config[$name]) ) {
		return $pabi_config[$name];
	}
	return null;
}

function pabi_get_options()
{
	global $pabi_config;
	if( !isset($_SESSION['pabi_options']) ) {
		$_SESSION['pabi_options'] = $pabi_config['default_options'];
	}
	return $_SESSION['pabi_options'];
}

function pabi_set_option($name, $value)
{
	global $pabi_config;
	if( !isset($_SESSION['pabi_options']) ) {
		$_SESSION['pabi_options'] = $pabi_config['default_options'];
	}
	switch( $name ) {
		case 'watermark_size':
			$value = min($pabi_config['max_watermark_size'],max($pabi_config['min_watermark_size'],(int)$value));
			break;
		case 'watermark_text':
			$value = substr($value,0,$pabi_config['max_watermark_length']);
			break;
		case 'width':
			if( $value ) {
				$value = min($pabi_config['max_resize_width'],max(1,$value));
			}
			break;
		case 'height':
			if( $value ) {
				$value = min($pabi_config['max_resize_height'],max(1,$value));
			}
			break;
		case 'size':
			if( !in_array($value,$pabi_config['size_options']) ){
				$value = $pabi_config['default_options']['size'];
			}
			break;
	}
	$_SESSION['pabi_options'][$name] = $value;
}

function pabi_process_upload($upload)
{
	global $pabi_errors;
	if( $upload['error'] != UPLOAD_ERR_OK ) {
		switch( $upload['error'] ) {
			case UPLOAD_ERR_INI_SIZE:
				$pabi_errors[] = pabi_translate('Your uploaded file is too large');
				break;
			case UPLOAD_ERR_PARTIAL:
				$pabi_errors[] = pabi_translate('The upload was interrupted. Please try again later');
				break;
			case UPLOAD_ERR_NO_FILE:
				$pabi_errors[] = pabi_translate('You did not upload any images');
				break;
			case UPLOAD_ERR_NO_TMP_DIR:
			case 7:
			case 8:
				$pabi_errors[] = pabi_translate('A server error occurred. Please try again later');
				break;
			default: die($upload['error']);break;
		}
	}
	elseif( !is_uploaded_file($upload['tmp_name']) ) {
		$pabi_errors[] = pabi_translate('You did not upload any images');
	}
	elseif( preg_match('#^(.+)\.(jpg|png|gif|zip)$#i', $upload['name'], $matches) ) {
		$ext = strtolower($matches[2]);
		if( $ext == 'zip' ) {
			return pabi_process_zip($upload['tmp_name']);
		}
		else{
			return pabi_process_image($upload['tmp_name'], $upload['name']);
		}
	}
	else{
		$pabi_errors[] = $upload['name'] . ': ' . pabi_translate('Unknown File Format');
	}
	return false;
}

function pabi_process_zip_ext($filename)
{
	global $pabi_errors;
	$added = 0;
	$zip = zip_open($filename);
	$existing = pabi_count_images();
	if( $zip ) {
		while( ($zip_entry = zip_read($zip) ) && $existing < pabi_setting('max_images') ) {
			$name = zip_entry_name($zip_entry);
			if( preg_match('#([^/\\\\]+)\.(jpg|gif|png)$#i', $name, $matches ) ) {
				$filesize = zip_entry_filesize($zip_entry);
				if( $filesize <= pabi_setting('max_image_filesize') * (1024*1024) ) {
					$dir = pabi_setting('temp_dir') . '/' . pabi_get_user_dir();
					$basename = $matches[1];
					$ext = $matches[2];
					$newname = $dir . '/' . $basename . '.' . $ext;
					$cnt = 0;
					while( file_exists($newname) ) {
						$cnt++;
						$newname = $dir . '/' . $basename . '_' . $cnt . '.' . $ext;
					}
					if(zip_entry_open($zip, $zip_entry, "r")) {
						$buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
						zip_entry_close($zip_entry);
						$fp = fopen($newname, 'w');
						if( $fp ) {
							fwrite($fp, $buf);
						}
						$added++;
						$existing++;
					}
				}
				else{
					$pabi_errors[] = htmlspecialchars($name).': '.pabi_translate('file too large.');
				}
			}
			elseif( $name[strlen($name)-1] != '/' ) {
				$pabi_errors[] = htmlspecialchars($name).': '.pabi_translate('Unknown file format');
			}
		}
		zip_close($zip);
	}
	else{
		$pabi_errors[] = pabi_translate('Unable to read from zip archive');
	}
	return $added;
}

function pabi_process_zip_pclzip($filename)
{
	global $pabi_errors;
	require_once dirname(__FILE__) . '/pclzip.lib.php';
	$added = 0;
	$existing = pabi_count_images();
	$zip = new PclZip($filename);
	$list = $zip->listContent();
	if( $list ) {
		$exdir = pabi_setting('temp_dir').'/'.pabi_get_user_dir().'/ex';
		mkdir($exdir);
		foreach( $list as $entry ) {
			if( !$entry['folder'] && $existing < pabi_setting('max_images') ) {
				$name = $entry['filename'];
				if( preg_match('#([^/\\\\]+)\.(jpg|gif|png)$#i', $name, $matches ) ) {
					$filesize = $entry['size'];
					if( $filesize <= pabi_setting('max_image_filesize') * (1024*1024) ) {
						$dir = pabi_setting('temp_dir') . '/' . pabi_get_user_dir();
						$basename = $matches[1];
						$ext = $matches[2];
						$newname = $dir . '/' . $basename . '.' . $ext;
						$cnt = 0;
						while( file_exists($newname) ) {
							$cnt++;
							$newname = $dir . '/' . $basename . '_' . $cnt . '.' . $ext;
						}
						$result = $zip->extractByIndex($entry['index'], PCLZIP_OPT_PATH, $exdir, PCLZIP_OPT_REMOVE_ALL_PATH);
						if($result ) {
							rename($exdir.'/'.$basename.'.'.$ext, $newname);
							$added++;
							$existing++;
						}
					}
					else{
						$pabi_errors[] = htmlspecialchars($name).': '.pabi_translate('file too large.');
					}
				}
				else{
					$pabi_errors[] = htmlspecialchars($name).': '.pabi_translate('Unknown file format');
				}
			}
		}
		rmdir($exdir);
	}
	else{
		$pabi_errors[] = pabi_translate('Unable to read from zip archive');
	}
	return $added;

}

function pabi_process_zip($filename)
{
	$added = 0;
	if( function_exists('zip_open') ) {
		$added = pabi_process_zip_ext($filename);
	}
	else{
		$added = pabi_process_zip_pclzip($filename);
	}
	return $added;
}

function pabi_process_image($filename, $original_name)
{
	if( pabi_count_images() < pabi_setting('max_images') ) {
		return pabi_add_image($filename,$original_name);
	}
	else{
		return false;
	}
}

function pabi_user_dir_name()
{
	global $pabi_config;
	if( !isset($_SESSION['pabi_user_dir']) ) {
		$dir = session_id() . time();
		$_SESSION['pabi_user_dir'] = $dir;
	}
	return $_SESSION['pabi_user_dir'];
}

function pabi_touch_user_dir()
{
	global $pabi_config;
	$dir = $pabi_config['temp_dir'] . '/' . pabi_user_dir_name();
	if( is_dir($dir) ) {
		touch($dir);
	}
}

/**
 * Gets the dirname (and creates the dirs if they don't exist)
 * for the current user's temporary storage
 * @return The name of the directory where user images are stored
 */
function pabi_get_user_dir()
{
	global $pabi_config;
	$dir = pabi_user_dir_name();
	if( !is_dir($pabi_config['temp_dir'] . '/' . $dir) ) {
		mkdir($pabi_config['temp_dir'] . '/' . $dir);
		chmod($pabi_config['temp_dir'] . '/' . $dir, $pabi_config['dir_chmod']);
		mkdir($pabi_config['temp_dir'] . '/' . $dir . '/converted');
		chmod($pabi_config['temp_dir'] . '/' . $dir . '/converted', $pabi_config['dir_chmod']);		
	}
	return $dir;
}

function pabi_get_converted_images()
{
	global $pabi_config;
	$images = array();
	$dir = $pabi_config['temp_dir'] . '/' . pabi_user_dir_name() . '/converted';
	if( file_exists($dir) ) {
		$d = dir($dir);
		while( false !== ($entry = $d->read() ) ) {
			$path = $dir . '/' . $entry;
			if( !is_dir( $path ) && $entry[0] != '.' ) {
				if( preg_match('#^(.+)\.(jpg|gif|png)$#i', $entry, $matches ) ) {
					$images[$path] = $entry;
				}
			}
		}
	}
	return $images;
}

/**
 * Gets the names of all uploaded images
 * @return an associative array of image filenames in the form
 * 'image.jpg' => array('name' => 'image', 'extension' => 'jpg')
 */
function pabi_get_user_images($refresh = false)
{
	global $pabi_config;
	$images = array();
	$dir = $pabi_config['temp_dir'] . '/' . pabi_user_dir_name();
	if( file_exists($dir) ) {
		$d = dir($dir);
		while( false !== ($entry = $d->read() ) ) {
			$path = $dir . '/' . $entry;
			if( !is_dir( $path ) && $entry[0] != '.' ) {
				if( preg_match('#^(.+)\.(jpg|gif|png)$#i', $entry, $matches ) ) {
					$images[$entry] = array('name' => $matches[1], 'extension' => $matches[2]);
				}
			}
		}
		$d->close();
	}
	return $images;
}

function pabi_resize($image, $max_width, $max_height, $method = 'scale')
{
	// get the current dimensions of the image
	$src_width = imagesx($image);
	$src_height = imagesy($image);
 
	// if either max_width or max_height are 0 or null then calculate it proportionally
	if( !$max_width ){
		$max_width = $src_width / ($src_height / $max_height);
	}
	elseif( !$max_height ){
		$max_height = $src_height / ($src_width / $max_width);
	}
 
	// initialize some variables
	$thumb_x = $thumb_y = 0;	// offset into thumbination image
 
	// if scaling the image calculate the dest width and height
	$dx = $src_width / $max_width;
	$dy = $src_height / $max_height;
	if( $method == 'scale' ){
		$d = max($dx,$dy);
	}
	// otherwise assume cropping image
	else{
		$d = min($dx, $dy);
	}
	$new_width = $src_width / $d;
	$new_height = $src_height / $d;
	// sanity check to make sure neither is zero
	$new_width = max(1,$new_width);
	$new_height = max(1,$new_height);
 
	$thumb_width = min($max_width, $new_width);
	$thumb_height = min($max_height, $new_height);
 
	$thumb_x = ($thumb_width - $new_width) / 2;
	$thumb_y = ($thumb_height - $new_height) / 2;
 
	// create a new image to hold the thumbnail
	$thumb = imagecreatetruecolor($thumb_width, $thumb_height);
 
	// copy from the source to the thumbnail
	imagecopyresampled($thumb, $image, $thumb_x, $thumb_y, 0, 0, $new_width, $new_height, $src_width, $src_height);
	return $thumb;
}

function pabi_watermark($image, $options, $replace = array() )
{
	global $pabi_config;
	$padding = 3;
	if( !$options['watermark_text'] ) {
		return;
	}
	$text = $options['watermark_text'];
	$s = array();
	$r = array();
	foreach( $replace as $key => $value ) {
		$s[] = '{'.$key.'}';
		$r[] = $value;
	}
	if( count($s) ) {
		$text = str_replace($s, $r, $text);
	}

	$iwidth = imagesx($image);
	$iheight = imagesy($image);
	
	if( function_exists('imagettfbbox') ) {

		$font = dirname(__FILE__) . '/fonts/' . $pabi_config['watermark_font'].'.ttf';
		$box = imagettfbbox($options['watermark_size'], 0, $font, $text);
		$width = $box[2] - $box[0];
		$height = $box[3] - $box[5];
		$xoff = $box[0];
		$yoff = -$box[5];

	}
	else{
		$width = imagefontwidth(2)*strlen($text);
		$height = imagefontheight(2)+4;
		$xoff = $yoff = 0;
	}

	switch( $options['watermark_location'] ) {
		case 'top-left':
			$xoff = $pabi_config['watermark_padding'];
			$yoff = $pabi_config['watermark_padding'];
			break;
		case 'top':
			$xoff = ($iwidth - $width) / 2 + $xoff;
			$yoff = $pabi_config['watermark_padding'];
			break;
		case 'top-right':
			$xoff = ($iwidth - $width) + $xoff - $pabi_config['watermark_padding'];
			$yoff += $pabi_config['watermark_padding'];
			break;
		case 'left':
			$yoff = ($iheight - $height) / 2 + $yoff + $pabi_config['watermark_padding'];
			break;
		case 'middle':
			$xoff = ($iwidth - $width) / 2 + $xoff;
			$yoff = ($iheight - $height) / 2 + $yoff;
			break;
		case 'right':
			$xoff = ($iwidth - $width) + $xoff - $pabi_config['watermark_padding'];
			$yoff = ($iheight - $height) / 2 + $yoff;
			break;
		case 'bottom-left':
			$xoff += $pabi_config['watermark_padding'];
			$yoff = ($iheight - $height) + $yoff - $pabi_config['watermark_padding'];
			break;
		case 'bottom':
			$xoff = ($iwidth - $width) / 2 + $xoff;
			$yoff = ($iheight - $height) + $yoff - $pabi_config['watermark_padding'];
			break;
		case 'bottom-right':
			$xoff = ($iwidth - $width) + $xoff - $pabi_config['watermark_padding'];
			$yoff = ($iheight - $height) + $yoff - $pabi_config['watermark_padding'];
			break;
	}

	$transparency = $options['watermark_transparency'] * 127 / 100;
	$colour = imagecolorallocatealpha($image,255,255,255,$transparency);
/*
	if( $options['watermark_bgcolour'] ) {
		$bgcolour = imagecolorallocatealpha($image,0,0,0,$transparency);
		imagefilledrectangle($image,0,$by,imagesx($image),$by + $height+ (2*$padding),$bgcolour);
	}
*/
	$bg = imagecolorallocatealpha($image,0,0,0,$transparency);

	if( function_exists('imagettfbbox') ) {
		imagettftext($image,$options['watermark_size'],0,$xoff + 2, $yoff + 2, $bg, $font, $text);
		imagettftext($image,$options['watermark_size'],0,$xoff, $yoff, $colour, $font, $text);
	}
	else{
		imagestring($image, 2, $xoff + 2, $yoff + 2,$text, $bg);
		imagestring($image, 2, $xoff, $yoff, $text, $colour);
	}
}

function pabi_clean_user_dir()
{
	global $pabi_config;
	$dir = $pabi_config['temp_dir'] . '/' . pabi_user_dir_name();
	if( file_exists($dir) ) {
		pabi_rmdir($dir);
	}
}

function pabi_rmdir($dir)
{
	if( !is_dir($dir) ) {
		return;
	}
	$d = dir($dir);
	if( !$d ) {
		return;
	}
	while( false !== ($entry = $d->read() ) ) {
		$path = $dir . '/' . $entry;
		if( is_file($path) ) {
			unlink($path);
		}
		elseif( $entry[0] != '.' ){
			if( is_dir($path) ) {
				pabi_rmdir($path);
			}
		}
	}
	$d->close();
	if( is_dir($dir) ) {
		rmdir($dir);
	}
}

function pabi_empty_dir($dir)
{
	$d = dir($dir);
	while( false !== ($entry = $d->read() ) ) {
		if( is_file($dir.'/'.$entry) ) {
			unlink($dir.'/'.$entry);
		}
		elseif( $entry[0] != '.' ) {
			pabi_rmdir($dir.'/'.$entry);
		}
	}
	$d->close();
}

/**
 * Add an image
 * @param $filename the filename of the image
 * @param $name The original name of the image
 */
function pabi_add_image($filename, $name)
{
	global $pabi_config;
	global $pabi_errors;
	$info = getimagesize($filename);
	if( !in_array($info[2], array(IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_GIF) ) ) {
		$pabi_errors[] = pabi_translate('Unknown File Format');
		return false;
	}
	$filesize = filesize($filename);
	if( $filesize > $pabi_config['max_image_filesize'] * (1024 * 1024)) {
		$pabi_errors[] = pabi_translate('file too large.');
		return false;
	}
	$dir = $pabi_config['temp_dir'] . '/' . pabi_get_user_dir();
	if( !preg_match('#^(.+)\.(jpg|gif|png)$#i', $name, $matches ) ) {
		$pabi_errors[] = pabi_translate('Unknown File Format');
		return false;
	}
	$basename = $matches[1];
	$ext = $matches[2];
	$newname = $dir . '/' . $name;
	$cnt = 0;
	while( file_exists($newname) ) {
		$cnt++;
		$newname = $dir . '/' . $basename . '_' . $cnt . '.' . $ext;
	}
	rename($filename, $newname);
	return true;
}

/**
 * Count the number of images uploaded
 * @return the number of images the user has uploaded
 */
function pabi_count_images()
{
	$images =pabi_get_user_images();
	return count($images);
}

/**
 * Uses the user's option settings to convert all the user's images
 */
function pabi_convert()
{
	$options = pabi_get_options();
	$images = pabi_get_user_images();
	pabi_empty_dir(pabi_setting('temp_dir').'/'.pabi_get_user_dir().'/converted');
	$width = $height = 0;
	if( $options['resize'] ) {
		if( $options['width'] > 0 || $options['height'] > 0 ) {
			$width = $options['width'];
			$height = $options['height'];
		}
		else{
			list($width, $height) = explode('x',$options['size']);
		}
	}
	foreach( $images as $filename => $parts ) {
		$ifilename = pabi_setting('temp_dir') . '/' . pabi_get_user_dir() . '/' . $filename;
		$img = pabi_load_image($ifilename);
		if( $img ) {
			
			if( $options['resize'] ) {
				$resized = pabi_resize($img, $width, $height, 'scale');
				imagedestroy($img);
				$img = $resized;
			}
			$format = $options['format'];
			if( $format == '' ) {
				$format = $parts['extension'];
			}
			$replace = array(
				'width' => imagesx($img),
				'height' => imagesy($img),
				'filename' => $filename
			);
			if( $options['watermark_text'] != '' ) {
				pabi_watermark($img, $options, $replace);
			}
			$savename = $parts['name'] . '.' . $format;
			$saved_filename = pabi_setting('temp_dir').'/'.pabi_get_user_dir() . '/converted/' . $savename;
			pabi_save_image($img, $saved_filename);
		}
	}
	return true;
}

function pabi_count_converted()
{
	$dir = pabi_setting('temp_dir') . '/' . pabi_user_dir_name() . '/converted';
	$converted = 0;
	if( is_dir($dir) ) {
		$d = dir($dir);
		if( $d ) {
			while( false !== ($entry = $d->read() ) ) {
				if( preg_match('#\.(jpg|gif|png)$#i', $entry) ) {
					$converted++;
				}
			}
			$d->close();
		}
	}
	return $converted;
}

function pabi_load_image($filename)
{
	$image = null;
	$info = getimagesize($filename);
	if( $info ) {
		switch( $info[2] ) {
			case IMAGETYPE_JPEG:
				$image = imagecreatefromjpeg($filename);
				break;
			case IMAGETYPE_PNG:
				$image = imagecreatefrompng($filename);
				break;
			case IMAGETYPE_GIF:
				$image = imagecreatefromgif($filename);
				break;
		}
	}
	return $image;
}

function pabi_save_image($image, $filename)
{
	$options = pabi_get_options();
	$format = 'jpg';
	if( preg_match('#\.(jpg|gif|png)$#i', $filename, $match) ) {
		$format = strtolower($match[1]);
	}
	switch( $format ) {
		case 'jpg':
			imagejpeg($image, $filename, $options['jpeg_quality']);
			break;
		case 'png':
			imagepng($image, $filename);
			break;
		case 'gif':
			imagegif($image, $filename);
			break;
	}
}

/**
 * download the converted image(s)
 */
function pabi_download()
{
	global $pabi_config;
	$converted = pabi_get_converted_images();
	if( count($converted) == 1 ) {
		foreach( $converted as $filename => $name ) {
			$type = preg_replace('#^.*\.(jpg|gif|png)$#i', '$1', $name);
			$type = strtolower($type);
			if( $type == 'jpg' ) {
				$type = 'jpeg';
			}
			header('Content-Type: image/'.$type);
			header('Content-Disposition: attachment; filename="'.$name.'"');
			header('Content-Length: '.filesize($filename));
			readfile($filename);
			exit();
		}
	}
	elseif( count($converted) > 1 ) {
		$zip = $pabi_config['temp_dir'] . '/' . pabi_user_dir_name() . '/images.zip';

		if( file_exists($zip) ) {
			unlink($zip);
		}

		if( class_exists('ZipArchive') && PHP_VERSION > 5) {
			$z = new ZipArchive();
			include dirname(__FILE__) . '/php5zipcode.php';
		}
		else{
			require_once dirname(__FILE__) . '/pclzip.lib.php';
			$z = new PclZip($zip);
			$converteddir = pabi_setting('temp_dir').'/'.pabi_user_dir_name().'/converted';
			$list = $z->create(array_keys($converted) ,PCLZIP_OPT_REMOVE_ALL_PATH);
			if( $list ) {
			}
			else{
			}
		}
		if( file_exists( $zip ) ) {
			header('Content-Type: application/zip');
			header('Content-Disposition: attachment; filename=images.zip');
			header('Content-Length: '.filesize($zip));
			readfile($zip);
			exit();
		}
	}
	return null;
}

function pabi_get_zip()
{
	if( pabi_count_converted() == 0 ) {
		return null;
	}
	
}

function pabi_clean_old_dirs()
{
	global $pabi_config;
	$d = dir($pabi_config['temp_dir']);
	while( false !== ($entry = $d->read() ) ) {
		if( $entry[0] != '.' ) {
			$path = $pabi_config['temp_dir'] . '/' . $entry;
			if( is_dir($path) ) {
				if( filemtime($path) < time() - (60 * $pabi_config['clean_after_minutes']) ) {
					pabi_rmdir($path);
				}
			}
		}
	}
	$d->close();
}

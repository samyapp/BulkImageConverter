<?php

/*
 Quick hacky workaround because php 4 seems to get flustered by usage of class constants
 in code that would otherwise do different things for php 4 and php 5...
*/
if( !defined('PABI_INCLUDED') ) {
	die();
}
			if( $z->open($zip,ZIPARCHIVE::CREATE ) ) {
				foreach( $converted as $filename => $name ) {
					$z->addFile($filename, $name);
				}
				$z->close();
			}

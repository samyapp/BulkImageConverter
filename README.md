Bulk Image Converter V1.0
=============================
Copyright _2008_ Sam Yapp
https://githib.com/samyapp/BulkImageConverter/
Released under the MIT Licence

This script makes of the PclZip library released under the lgpl by Vincent Blavet 
http://www.phpconcept.net You can obtain updates to the pclzip.lib.ph from http://www.phpconcept.net/pclzip/index.en.php

I) Requirements
- recent version of php (4 or 5)
- gd library / extension installed with support for
  reading and writing png, gif and jpeg images
	freetype support required for using fonts / specifying size
	for watermarks, otherwise built in (small) font is used.
- Apache web server (may work on others, not tested though)
- php zip extension *recommended* for processing zip archives. If not present,
	a 3rd party library written in php is used which is slower.
- php zlib extension *required* for processing zip archives
- reasonably large values specified for the following settings relating to 
	uploading and processing files in your php.ini file - consult your webhost if unsure:
	- max_input_time
	- memory_limit
	- post_max_size
	- upload_max_filesize

II) Installation

Installing phpAce Bulk Image Converter is relatively simple.

1) Rename the configuration file "config.php.sample" to "config.php"
2) Edit the "config.php" file with the settings you want to use.
3) Upload all the files to the directory on your server where you
	 want to install the script.
4) Change the permissions on the "temp" directory so that the script
	 can create subdirectories and files in it. Consult your web host
	 if you are unsure of how to do this.

	If you want to embed the script in an existing php page of your website
	you need to:

	i) change the configuration option "standalone" to 0
	ii) link to this script's css stylesheet in your script's <head> section
			or copy and past the styles.css content into your own stylesheet,
			modifying the path to the 'loading.gif' image in your own stylesheet
			if you want it to still display.
	iii) add the php code (surrounded in php tags if neccessary ) to your 
			script where you want it to display:

				include('path/to/this/directory/index.php');

The script should now be installed.

III) Customization

1) Changing the texts / translating the script
	- Make a copy of the "default.php" file in the translations directory
		and save it with a new name (for example "custom.php")
	- Edit the relevant variables in this file.
	- Edit the config.php file and enter the name of your translations file
		without the .php as the value for the $pabi_config['language'] setting,
		eg. $pabi_config['language'] = 'custom';

	It is recommended you edit a copy of this file rather than the original
	"default.php" so that when updates to the script are released your translations
	and modifications are not overwritten.

2) Changing the style
	- You can edit the styles.css file that comes with the script directly,
		but it is recommended to make a copy of it instead, named for example
		"mystyles.css" and edit that.
		You should then edit the $pabi_config['stylesheet'] variable (in the config.php file)
		with the name of your stylesheet, eg: $pabi_config['stylesheet'] = 'mystyles.css';

	Doing this instead of editing the styles.css directly means that your modifications
	won't be overwritten when installing updates to the script.

3) Editing the layout template
	- You can either:
		- directly edit the file "layout.phtml" (not recommended)
		- or copy it with a new filename, edit that directly, and enter the new filename 
			in the $pabi_config['template'] option in the config.php file.

	The second option is recommended as your changes will not be overwritten if you
	update the script to a newer version in the future.



<?php
###############################
## ResourceSpace
## Local Configuration Script
###############################

# All custom settings should be entered in this file.
# Options may be copied from config.default.php and configured here.

# MySQL database settings
$mysql_server = 'mariadb';
$mysql_username = 'Anto';
$mysql_password = 'Twitch@127006';
$mysql_db = 'AntoDB';

# Base URL of the installation
$baseurl = 'http://localhost';

# Email settings
$email_notify = '4nt013harath567@gmail.com';
$email_from = '4nt013harath567@gmail.com';
# Secure keys
$scramble_key = 'ba959bb5cd385822851345e39b0b0efdf6b79a4f4beb48e83219e06980619505';
$api_scramble_key = 'eac5eeb04745f930c092bdb3f13c44422e9df7a14c30b21ef3cec88377a98988';

# Paths
$imagemagick_path = '/usr/bin';
$ghostscript_path = '/usr/bin';
$ffmpeg_path = '/usr/bin';
$exiftool_path = '/usr/bin';
$pdftotext_path = '/usr/bin';

$applicationname = 'ResourceSpace';
$homeanim_folder = 'filestore/system/slideshow_2cf781b5717ffa0';

/*

New Installation Defaults
-------------------------

The following configuration options are set for new installations only.
This provides a mechanism for enabling new features for new installations without affecting existing installations (as would occur with changes to config.default.php)

*/
                                
// Set imagemagick default for new installs to expect the newer version with the sRGB bug fixed.
$imagemagick_colorspace = "sRGB";

$contact_link=false;

$stemming=true;
$case_insensitive_username=true;
$user_pref_user_management_notifications=true;

$use_zip_extension=true;
$collection_download=true;

$ffmpeg_preview_force = true;
$ffmpeg_preview_extension = 'mp4';
$ffmpeg_preview_options = '-f mp4 -b:v 1200k -b:a 64k -ac 1 -c:v libx264 -pix_fmt yuv420p -profile:v baseline -level 3 -c:a aac -strict -2';

$daterange_search = true;
$upload_then_edit = true;

$purge_temp_folder_age=90;
$filestore_evenspread=true;

$comments_resource_enable=true;

$api_upload_urls = array();

$use_native_input_for_date_field = true;
$resource_view_use_pre = true;

$sort_tabs = false;
$maxyear_extends_current = 5;
$thumbs_display_archive_state = true;
$file_checksums = true;
$hide_real_filepath = true;
$annotate_enabled = true;

$plugins[] = "brand_guidelines";

<?php

// Allow access from Chrome Extension
header("Access-Control-Allow-Origin: *");

// Fix Docker Paths
if (substr(php_uname(), 0, 7) != "Windows"){
	putenv('PATH=/usr/local/bin:/usr/bin:/bin');
}

// Helper Function
function bexec($name, $cmd) {
	if (substr(php_uname(), 0, 7) == "Windows"){
		pclose(popen("start /B \"{$name}\" {$cmd} 2>&1", "r"));
	} else {
		shell_exec("{$cmd} 2>&1 &");
	}
}
 
// Debug Vars
$DEBUG = false;

/* Return Errors with ERR_{Code}
*/
$return = "";

$data = $_GET;
if ($data["action"] == "createTask") {
	// Generate UUID
	$uuid = uniqid("YDL_", true);
		
	// Escape Args
	$data["url"] = escapeshellcmd($data["url"]);
	$data["title"] = escapeshellcmd($data["title"]);
	$data["artist"] = escapeshellcmd($data["artist"]);
	$data["album"] = escapeshellcmd($data["album"]);
	$data["albumArtist"] = escapeshellcmd($data["albumArtist"]);
	$data["genre"] = escapeshellcmd($data["genre"]);
	$data["bitrate"] = escapeshellcmd($data["bitrate"]);
	
	$thumbnail = "default_thumbnail.png";
	// Download Thumbnail
	if ($data["thumbnail"] != "") {
		$data["thumbnail"] = escapeshellarg($data["thumbnail"]);
		// On Windows install wget and add to Path
		shell_exec("wget --no-check-certificate -O \"thumbnails/{$uuid}\" {$data["thumbnail"]}");
		if (file_exists("thumbnails/{$uuid}")) {
			$thumbnail = "thumbnails/{$uuid}";
		}
	}
	
	// Create Status File
	$createStatus = "touch status/{$uuid}.downloading";
	
	// FFMPEG Command
	$ffm_args = "\"ffmpeg\" ";
	if (substr(php_uname(), 0, 7) == "Windows"){
		$ffm_args = "\"bin/ffmpeg\" ";
	}
	$ffm_args .= "-i \"temp/{$uuid}.mp3\" ";
	$ffm_args .= "-i \"{$thumbnail}\" ";
	$ffm_args .= "-map 0:0 -map 1:0 -c copy ";
	$ffm_args .= "-id3v2_version 3 ";
	$ffm_args .= "-metadata:s:v comment=\"Cover (Front)\" ";
	$ffm_args .= "-metadata title=\"{$data["title"]}\" ";
	$ffm_args .= "-metadata artist=\"{$data["artist"]}\" ";
	$ffm_args .= "-metadata album_artist=\"{$data["albumArtist"]}\" ";
	$ffm_args .= "-metadata album=\"{$data["album"]}\" ";
	if ($data["track"] != 0) {
		$data["track"] = escapeshellcmd($data["track"]);
		$ffm_args .= "-metadata track=\"{$data["track"]}\" ";
	}
	$ffm_args .= "-metadata genre=\"{$data["genre"]}\" ";
	$ffm_args .= "\"output/{$uuid}.mp3\"";
	
	// YDL Command
	$ydl_args = "--abort-on-error ";
	$ydl_args .= "--prefer-ffmpeg "; 
	$ydl_args .= "--no-playlist ";
	$ydl_args .= "--no-continue "; 
	$ydl_args .= "--no-part "; 
	$ydl_args .= "--no-progress "; 
	$ydl_args .= "-x --audio-format mp3 "; 
	$ydl_args .= "--audio-quality {$data["bitrate"]}K "; 
	$ydl_args .= "-o \"temp/" . $uuid . ".%(ext)s\" "; 
	$ydl_args .= "--exec 'mv status/{$uuid}.downloading status/{$uuid}.converting && " . $ffm_args . " && mv status/{$uuid}.converting status/{$uuid}.done && echo {}' "; 
	$ydl_args .= "{$data["url"]}";
	if ($DEBUG) {
		$ydl_args = "-v " . $ydl_args;
	}
	// On Linux python must be intalled through 'apt-get install python'
	$ydl = "\"bin/youtubedl\" " . $ydl_args . " > logs/{$uuid}.txt";
	
	// Run Command
	bexec("PHP-CreateStatus-{$uuid}", $createStatus);
	bexec("PHP-{$uuid}", $ydl);
	
	$return = $uuid;
} else if ($data["action"] == "checkStatus") {
	$uuid = escapeshellcmd($data["uuid"]);
	if (file_exists("logs/{$uuid}.txt")) {
		if (file_exists("status/{$uuid}.downloading")) {
			$return = "Downloading";
		} else if (file_exists("status/{$uuid}.converting")) {
			$return = "Converting";
		} else if (file_exists("status/{$uuid}.done") && file_exists("output/{$uuid}.mp3")) {
			$return = "Done-{$uuid}";
		} else {
			$return = "Removed";
		}
	} else {
		$return = "Removed";
	}
} else if ($data["action"] == "download") {
	$uuid = escapeshellcmd($data["uuid"]);
	
	// Send user file to download
	$file = file_get_contents("output/{$uuid}.mp3");
	$fileName = $data["name"] . "test";
	header('Content-disposition: attachment; filename="'.$fileName.'".mp3');
	header('Content-type: audio/mpeg');
	echo $file;
	
	// Remove Temp files
	if (file_exists("thumbnails/{$uuid}")) {
		shell_exec("rm -f thumbnails/{$uuid}");
	}
	// Output file will be deleted by Cron Job, everything else is deleted on the stop
	shell_exec("rm -f logs/{$uuid}.*");
	shell_exec("rm -f temp/{$uuid}.*");
	shell_exec("rm -f status/{$uuid}.*");
}

// Dont echo return code if the user is downloading
if ($data["action"] != "download") {
	echo $return;
}
?>
<?php

// Allow access from Chrome Extension
header("Access-Control-Allow-Origin: *");

// Checks and Vars
$isWindows = substr(php_uname(), 0, 7) == "Windows";
$webRoot = substr(dirname(__FILE__), 0, strlen(dirname(__FILE__))-3);
if (!$isWindows){
	putenv('PATH=/usr/local/bin:/usr/bin:/bin');
	require_once('/etc/other-creds/creds.php');
} else {
	$youtubedl_api_key = ""; // Fetch this when need be
}

// Helper Functions
function bexec($name, $cmd) {
	global $isWindows;
	if ($isWindows){
		pclose(popen("start /B \"{$name}\" {$cmd} 2>&1", "r"));
	} else {
		shell_exec("{$cmd} 2>&1 &");
	}
}
function contains($str, $chk) {
	return strpos($str, $chk) !== false;
}
function checkVid($videoID){
	$apikey = $youtubedl_api_key;
	$dur = file_get_contents("https://www.googleapis.com/youtube/v3/videos?part=contentDetails&id={$videoID}&key={$apikey}");
	$VidDuration = json_decode($dur, true);
	foreach ($VidDuration['items'] as $vidTime) 
       $check = explode("PT", $vidTime['contentDetails']['duration'])[1];
	if (contains($check, "W") || contains($check, "D") || contains($check, "H"))
		return "Video > 1 Hour Long";
	if ($check == "0S")
		return "No streams!";
   return "Valid";
}
 
// Debug Vars
$DEBUG = true;

/* Return Errors with ERR_{Code}
*/
$return = "";

$data = $_GET;
if ($data["action"] == "createTask") {
	// Generate UUID
	$uuid = uniqid("YDL_", true);
		
	// Escape Args
	$data["url"] = $isWindows ? $data["url"] : escapeshellcmd($data["url"]);
	$data["title"] = escapeshellcmd($data["title"]);
	$data["artist"] = escapeshellcmd($data["artist"]);
	$data["album"] = escapeshellcmd($data["album"]);
	$data["albumArtist"] = escapeshellcmd($data["albumArtist"]);
	$data["genre"] = escapeshellcmd($data["genre"]);
	$data["bitrate"] = escapeshellcmd($data["bitrate"]);
	
	// Check to see if URL is from Youtube
	preg_match('@^(?:https://)?([^/]+)@i', $data["url"], $m);
	if ($m[1] != "www.youtube.com")
		die("Source [" . $m[1] . "] is not from Youtube (HTTPS)!");
	
	// Check if video is within length limits
	$id = explode("=", $data["url"])[1];
	$chkVid = checkVid($id);
	if ($chkVid != "Valid")
		die($chkVid);
	
	$thumbnail = "default_thumbnail.png";
	// Download Thumbnail
	if ($data["thumbnail"] != "") {
		$data["thumbnail"] = escapeshellarg($data["thumbnail"]);
		// On Windows install wget and add to Path
		shell_exec("wget --no-check-certificate -O \"thumbnails/{$uuid}\" {$data["thumbnail"]}");
		if (file_exists("thumbnails/{$uuid}"))
			$thumbnail = "thumbnails/{$uuid}";
	}
	
	// Create Status File
	$createStatus = "touch status/{$uuid}.downloading";	
	
	// FFMPEG Command
	$ffm_args = "ffmpeg ";
	if ($isWindows)
		$ffm_args = "\"bin/ffmpeg.exe\" ";
	
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
	// To-Do Limit Maximum filesize of 100MB download
	$ydl_args .= "-x --audio-format mp3 "; 
	$ydl_args .= "--audio-quality {$data["bitrate"]}K "; 
	$ydl_args .= "-o \"temp/{$uuid}.%(ext)s\" "; 
	
	// Disable FFMPeg for Testing on Windows
	if (!$isWindows)
		$ydl_args .= "--exec 'mv status/{$uuid}.downloading status/{$uuid}.converting && {$ffm_args} && mv status/{$uuid}.converting status/{$uuid}.done && echo {}' "; 
	else
		$ydl_args .= "--exec \"mv status/{$uuid}.downloading status/{$uuid}.done && cp temp/{$uuid}.mp3 output/{$uuid}.mp3 && echo {}\" "; 
	
		
	$ydl_args .= "{$data["url"]}";
	if ($DEBUG)
		$ydl_args = "-v " . $ydl_args;
	
	$ydl = $isWindows ? "\"bin/youtubedl.exe\"" : "\"bin/youtubedl\"";
	$ydl .= " " . $ydl_args . " > logs/{$uuid}.txt";

	// Execute Command
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
			$return = "Unknown Error";
		}
	} else {
		$return = "Removed";
	}
	
} else if ($data["action"] == "download") {
	$uuid = escapeshellcmd($data["uuid"]);
	
	// TODO Return an error message instead of dieing
	if (strpos($uuid, '/') !== false || strpos($uuid, '\\') !== false)
		die();

	// Send user file to download
	$file = file_get_contents("output/{$uuid}.mp3");
	header('Content-disposition: attachment;');
	header('Content-type: audio/mpeg');
	echo $file;
	
	// TODO Remove all files older than 1 Hour in age
	
}

// Dont echo return code if the user is downloading
if ($data["action"] != "download") {
	echo $return;
}
?>
// Global Vars
var title = document.getElementById('title');
var artist = document.getElementById('artist');
var album = document.getElementById('album');
var albumArtist = document.getElementById('aartist');
var track = document.getElementById('track');
var genre = document.getElementById('genre');
var bitrate = document.getElementById('bitrate');
var thumbnail = document.getElementById('thumbnail');
var submitButton = document.getElementById('submit');
var idDisplay = document.getElementById("id");
var container = document.getElementById("container");

var fileName = "";

document.addEventListener('DOMContentLoaded', function() {
	var url = "";
	var id = "";
	
	// Re-fetch Form Elements
	title = document.getElementById('title');
	artist = document.getElementById('artist');
	album = document.getElementById('album');
	albumArtist = document.getElementById('aartist');
	track = document.getElementById('track');
	genre = document.getElementById('genre');
	bitrate = document.getElementById('bitrate');
	thumbnail = document.getElementById('thumbnail');
	submitButton = document.getElementById('submit');
	idDisplay = document.getElementById("id");
	container = document.getElementById("container");
	
	// Check to see if tab is on Youtube
	chrome.tabs.getSelected(null, function(tab) {
		url = tab.url;
		var host = url.split("//")[1].split("/")[0];
		if (!(host == "www.youtube.com" || host == "youtube.com")) {
			// User is not on Youtube.com
			container.innerHTML = "<center><h4>Only videos on Youtube.com are supported!</h4></center>";
		
		} else {
			// User is on Youtube.com
			
			// Fetch Youtube Video ID
			id = url.split('v=')[1];
			var ampersandPosition = id.indexOf('&');
			if(ampersandPosition != -1) {
				id = id.substring(0, ampersandPosition);
			}
			idDisplay.innerHTML = id + " (Youtube ID)";
			url = "https://www.youtube.com/watch?v=" + id;
			
			// Download Button
			submitButton.addEventListener('click', function() {		
				// Submit form
				ajax("http://youtubedl.ml/", checkStatus, {
					"action": "createTask",
					"url": url,
					"title": title.value,
					"artist": artist.value,
					"album": album.value,
					"albumArtist": albumArtist.value,
					"track": track.value,
					"genre": genre.value,
					"bitrate": bitrate.value,
					"thumbnail": thumbnail.value,
				});
				
				// Set Filename to download
				fileName = artist.value + " - " + title.value;
				if (artist.value == "" || title.value == "") {
					fileName = id;
				}
			});
		}
	});
});

var checkIntervalID = -1;
function checkStatus(response) {
	if (response.indexOf("YDL_") == 0) {
		idDisplay.innerHTML = response;
		checkIntervalID = setInterval(queryServerStatus, 100, response);
	} else {
		idDisplay.innerHTML = "Error!";
		container.innerHTML = "<center>Internal server error: " + response + "</center>";
	}
}
function queryServerStatus(uuid) {
	ajax("http://youtubedl.ml/", updateStatus, {
		"action": "checkStatus",
		"uuid": uuid
	});
}
function updateStatus(response) {
	container.innerHTML = "<center><h4>" + response + "</center></h4>";
	if (response.split("-")[0] == "Done") {
		var uuid = response.split("-")[1];
		clearInterval(checkIntervalID);
		
		// Create Download Button
		container.innerHTML = "<center><h4><button id='dlButton'>Download</button></h4></center>";
		var dlLink = "http://youtubedl.ml/?action=download&uuid=" + uuid + "&name=" + fileName;
		document.getElementById("dlButton").addEventListener("click", function(activeTab){
			chrome.downloads.download({
				url: dlLink,
				filename: fileName + ".mp3"
			});
		});
	} else if (response == "Removed") {
		clearInterval(checkIntervalID);
	}
}

// ("https://...", function(){}, {key: args, ...})
function ajax(url, cb, postArgs) {
	var xhttp;
	xhttp = new XMLHttpRequest();
	xhttp.onreadystatechange = function() {
		if (this.readyState == 4 && this.status == 200) {
			cb(this.responseText);
		}
	};
	var params = "";
    for (var key in postArgs) {
		params += key + "=" + postArgs[key] + "&";
	}
	params = params.slice(0, -1);
	xhttp.open("GET", url + "?" + params, true);
	xhttp.send();
}

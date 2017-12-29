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

document.addEventListener('DOMContentLoaded', function() {
	var url = "";
	var id = "";
	
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
			container.innerHTML = "<center><h4>We currently only support downloading Youtube Videos.</h4></center>";
		
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
			});
		}
	});
});

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

function checkStatus(response) {
	if (response.indexOf("YDL_") == 0) {
		idDisplay.innerHTML = response;
	} else {
		idDisplay.innerHTML = "Error!";
		container.innerHTML = "Internal server error: " + response;
	}
}
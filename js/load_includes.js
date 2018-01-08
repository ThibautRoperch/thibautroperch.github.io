
function getXMLHttpRequest() {
	var xhr = null;
	
	if (window.XMLHttpRequest || window.ActiveXObject) {
		if (window.ActiveXObject) {
			try {
				xhr = new ActiveXObject("Msxml2.XMLHTTP");
			} catch(e) {
				xhr = new ActiveXObject("Microsoft.XMLHTTP");
			}
		} else {
			xhr = new XMLHttpRequest(); 
		}
	} else {
		alert("Votre navigateur ne supporte pas l'objet XMLHTTPRequest...");
		return null;
	}
	
	return xhr;
}

function openFile(path, callback, args) {
	var xhr = getXMLHttpRequest();

	xhr.onreadystatechange = function() {
		if (xhr.readyState == 4 && (xhr.status == 200 || xhr.status == 0)) {
			callback(xhr.responseText, args);
		}
	};
	
	xhr.open("GET", path, true);
	xhr.overrideMimeType("text/html");
	xhr.send();
}

function load_includes() {
    openFile("includes.json", display_includes, []);
}

function display_includes(includes) {
    includes = JSON.parse(includes);

    for (let include in includes) {
        let dom = include;
        let file = includes[include];
        openFile(file, write_contents, dom);
    }
}

function write_contents(contents, dom) {
    document.getElementsByTagName(dom)[0].innerHTML = contents;
}

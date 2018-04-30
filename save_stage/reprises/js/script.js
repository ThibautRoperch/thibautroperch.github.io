
// Accueil

function autocomplete_typing(input, target_id) {
    document.querySelector("#" + target_id).value = input.value;
}

function show_password(show) {
    var password_input = document.querySelector("#password");
    if (show) {
        password_input.type = "text";
    } else {
        password_input.type = "password";
    }
}

function loading() {
    display_message("Reprise en cours", false);
}

function display_message(message, popup_mode) {
    var new_class_name = "displayed";
    new_class_name += popup_mode ? " popup" : "";
    document.querySelector("layer").className = new_class_name;
    document.querySelector("layer").querySelector("message").innerHTML = message;
}

function hide_message() {
    document.querySelector("layer").className = "";
}

function test_connection(button, event, conn) {
    event.preventDefault();

    var prefixe = (conn === "oracle") ? "src_" : "dest_";
    var host = document.querySelector("#" + prefixe + "host").value;
    var port = document.querySelector("#" + prefixe + "port").value;
    var database = document.querySelector("#" + prefixe + "database").value;
    var user = document.querySelector("#" + prefixe + "user").value;
    var password = document.querySelector("#" + prefixe + "password").value;

    var xhr = new XMLHttpRequest();
    xhr.open("GET", "../test_conn.php?conn=" + conn + "&host=" + host + "&port=" + port + "&database=" + database + "&user=" + user + "&password=" + password, true);
    xhr.onload = function() {
        var message = button.innerHTML + "<br><hr><br>";
        if (xhr.response === "") {
            message += "<ok></ok> Connexion vérifiée";
        } else {
            message += "<nok></nok> " + xhr.response;
        }
        message += "<buttons><a class=\"button\" onclick=\"hide_message()\" href=\"#\">Fermer</a></buttons>";
        display_message(message, true);
    };
    xhr.send();
}

// https://script-tutorials.developpez.com/tutoriels/html5/drag-drop-file-upload-html5/
var droparea = document.querySelector(".droparea");
if (droparea) {
    droparea.addEventListener("dragover", drag_over, false);
}

function drag_over(event) { // survol
    event.stopPropagation();
    event.preventDefault();

    display_message("Glisser-déposer les fichiers", false);

    var layer = document.querySelector("layer");
    layer.addEventListener("drop", drop, false);
}

var form_data = new FormData();

function drop(event) { // glisser déposer
    event.stopPropagation();
    event.preventDefault();

    dropped_files = event.dataTransfer.files;
    if (!dropped_files || !dropped_files.length) return;

    for (var i = 0; i < dropped_files.length; ++i) {
        form_data.append(""+i, dropped_files[i]);
    }

    upload_files(false);
}

function upload_files(confirm_erase) {
    var get_confirm = confirm_erase ? "?confirm=1" : "";

    var xhr = new XMLHttpRequest();
    xhr.open("POST", script_file_name + get_confirm);
    xhr.onload = function() {
        if (!confirm_erase) {
            var parser = new DOMParser();
            var alert = parser.parseFromString(xhr.response, "text/html").querySelector("alert");
            var message = alert.innerHTML + "<buttons><a class=\"button\" onclick=\"hide_message()\" href=\"#\">Annuler</a><button onclick=\"upload_files(true)\">Confirmer</button></buttons>";
            display_message(message, true);
        } else {
            display_message("Importation des fichiers", false);
            location.reload();
        }
    };
    xhr.send(form_data);
}

// Analyse

summary_li = (document.querySelector("#sommaire")) ? document.querySelector("#sommaire").querySelectorAll("li") : [];
for (li of summary_li) {
    li.onclick = function() { clicked_link(this); }
}

function clicked_link(clicked_li) {
    var new_class_name = (clicked_li.className === "") ? "active" : "";
    for (li of summary_li) {
        li.className = "";
    }
    clicked_li.className = new_class_name;
}

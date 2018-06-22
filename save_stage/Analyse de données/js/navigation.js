
domains_links = document.querySelectorAll("header nav ul a");
category_links = document.querySelectorAll("aside nav ul a");

// Change le lien des domaines en rajoutant le nom de la ville
for (link of domains_links) {
    link.href = link.href + "?" + city_name;
}

// Change le lien des catégories en rajoutant un event js et en mettant actif le premier lien et la première section
for (link of category_links) {
    link.addEventListener("click", function(event) { change_feature(event); });
    if (link === category_links[0]) {
        link.className = "active";
        active_section_from(link);
    }
}

if (category_links.length === 0) {
    active_section(document.querySelector("section"));
}

function change_feature(event) {
    event.preventDefault();
    var clicked_link = event.target;

    hide_features();

    clicked_link.className = "active";
    active_section_from(clicked_link);
}

function hide_features() {
    for (link of category_links) {
        link.className = "";
        get_target_section(link).className = "";
    }
}

function get_target_section(associated_link) {
    var id_target = associated_link.href.substr(associated_link.href.indexOf("#"));
    return document.querySelector(id_target);
}

function active_section_from(associated_link) {
    var section_target = get_target_section(associated_link);
    active_section(section_target);
}

function active_section(section) {
    if (section.className === "") section.className = "displayed";

    section.className = section.className + " active";
}

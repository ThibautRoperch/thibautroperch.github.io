
var city_name = document.location.search.substr(1).toLowerCase();
var content = document.querySelector("content");

// Titre de la page
document.querySelector("title").innerHTML = city_name.toUpperCase();
document.querySelector("h1").innerHTML = city_name.toUpperCase();

// Génération des DOM associés aux datas et callback functions
for (var data in datas_to_load) {
    var section = document.createElement("section");
    section.id = data;
    content.appendChild(section);

    var p = document.createElement("p");
    p.innerHTML = "Chargement des données en cours...";
    p.className = "center";
    section.appendChild(p);

    ajax("php/api/" + data + ".php?ville=" + city_name, datas_to_load[data], section);
}

function new_div(dom, nb_sub_doms) {
    var div = document.createElement("div");
    dom.appendChild(div);
    div.className = nb_sub_doms;

    return div;
}

function new_article(dom, title) {
    var article = document.createElement("article");
    dom.appendChild(article);

    var h2 = document.createElement("h2");
    h2.innerHTML = title;
    article.appendChild(h2);

    var nb_articles = parseInt(dom.className);
    article.style.flex = 1 / nb_articles;

    return article;
}

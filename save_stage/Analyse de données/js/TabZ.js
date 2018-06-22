
/* Tabulation pannel */

var tabz_style = document.createElement("style");
tabz_style.innerHTML = "\
tabz div.tabs {\
    display: flex;\
    justify-content: flex-start;\
    flex-direction: row;\
    flex-wrap: wrap;\
    margin-bottom: 0px;\
}\
\
tabz div.tabs button {\
    margin-bottom: 0;\
    padding: 10px 5px;\
    color: gray;\
    border: none;\
    border-bottom: 2px solid transparent;\
    background: white;\
    cursor: pointer;\
    transition: all .2s;\
}\
\
tabz div.tabs button + button {\
    margin-left: 5px;\
}\
\
tabz div.tabs button.active {\
    margin-bottom: 5px;\
    padding-bottom: 5px;\
    border-color: silver;\
}\
\
tabz div.contents > div {\
    display: none;\
    animation: fadeEffect 0s;\
}\
\
tabz div.contents > div.active {\
    display: block;\
}\
\
@keyframes fadeEffect {\
    from {opacity: 0;}\
    to {opacity: 1;}\
}\
";

document.querySelector("body").appendChild(tabz_style);

class TabZ {

    constructor(dom) {
        this.nb_tabs = 0;

        // Root

        this.root = document.createElement("tabz");
        dom.appendChild(this.root);

        // Tabs

        this.tabs_dom = document.createElement("div");
        this.tabs_dom.className = "tabs";
        this.root.appendChild(this.tabs_dom);

        // Contents

        this.contents_dom = document.createElement("div");
        this.contents_dom.className = "contents";
        this.root.appendChild(this.contents_dom);
    }

    add(tab, content) {
        this.addTab(tab);
        this.addContent(content);
    }

    addTab(tab) {
        var tab_dom = document.createElement("button");
        tab_dom.innerHTML = tab;

        this.resetTabs();
        tab_dom.className = this.nb_tabs++ + " active";
        tab_dom.addEventListener("click", function(event) { tabz_change_tab(event); })

        this.tabs_dom.appendChild(tab_dom);
    }

    addContent(content) {
        var div = document.createElement("div");
        div.appendChild(content);

        this.resetContents();
        div.className = "active";
        
        this.contents_dom.appendChild(div);
    }

    appendChild(content) {
        this.addContent(content);
    }

    resetTabs() {
        for (var tab of this.tabs_dom.children) {
            tab.className = tab.className.replace(" active", "");
        }
    }

    resetContents() {
        for (var content of this.contents_dom.children) {
            content.className = "";
        }
    }

}

function tabz_change_tab(event) {
    var button = event.target;
    var tabs_dom = button.parentNode;
    var contents_dom = tabs_dom.parentNode.querySelector("div.contents");

    // Reset tabs

    for (var tab of tabs_dom.children) {
        tab.className = tab.className.replace(" active", "");
    }

    // Reset contents

    for (var content of contents_dom.children) {
        content.className = "";
    }

    // Update content

    var content = contents_dom.children[parseInt(button.className)];
    content.className = "active";
    
    // Update tab

    var button = event.target;
    button.className = button.className + " active";
}

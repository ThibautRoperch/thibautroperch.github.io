
var onclick_expand_doms = document.querySelectorAll(".onclick-expand");
for (dom of onclick_expand_doms) {
    dom.onclick = function() { expand(this); };
}

function expand(dom) {
    var class_name = dom.parentNode.className;
    var new_class_name = (class_name === "") ? "expand" : "";
    dom.parentNode.className = new_class_name;
}

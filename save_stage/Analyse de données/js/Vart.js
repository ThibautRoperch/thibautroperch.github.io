
/* Vertical chart */

var vart_style = document.createElement("style");
vart_style.innerHTML = "\
vart {\
    display: table;\
    width: 100%;\
    border-collapse: collapse;\
    text-align: center;\
    color: rgb(80, 80, 80);\
}\
\
vart th, vart td {\
    width: 100px;\
    padding: 5px 10px;\
    text-align: center;\
    vertical-align: middle;\
    background-clip: content-box;\
}\
\
vart tr:first-child th {\
    padding: 10px 20px;\
}\
\
vart th:first-letter {\
    text-transform: uppercase;\
}\
\
vart td {\
    background-color: rgba(255, 150, 50, 0.2);\
}\
\
vart th.active {\
    color: rgba(255, 255, 255, 0.9);\
    background-color: rgba(100, 50, 0, 0.8);\
}\
vart td.active {\
    background-color: rgba(255, 150, 50, 0.8);\
}\
\
";

document.querySelector("body").appendChild(vart_style);

class Vart {

    constructor(dom, label_y, labels_x, datasets) {
        // Root

        this.root = document.createElement("vart");
        dom.appendChild(this.root);

        // Variables

        this.datasets = datasets;
        this.label_y = label_y;
        this.labels_x = labels_x;
        
        // Let's gooo

        this.display_labels_x();
        this.display_labels_y();

        this.vertical_datas = []; // pour chaque label_x : {15: ["dataset5_label", "dataset2_label"], ...}
        this.compute_vertical_datas();
        
        this.display_vertical_datas();
    }
    
    display_labels_x() {
        var labels_x_dom = document.createElement("tr");
        this.root.appendChild(labels_x_dom);

        var label_y_dom = document.createElement("th");
        label_y_dom.innerHTML = this.label_y;
        labels_x_dom.appendChild(label_y_dom);

        for (var i = 0; i < this.labels_x.length; ++i) {
            var label_dom = document.createElement("th");
            label_dom.innerHTML = this.labels_x[i];
            labels_x_dom.appendChild(label_dom);
        }
    }

    compute_vertical_datas() {
        // Pour chaque label x, créer un tableau de valeurs contenant les valeurs des datasets
        for (var i = 0; i < this.labels_x.length; ++i) {
            var datas = [];
            
            for (var key in this.datasets) {
                var val_y_x = this.datasets[key][i];
                if (val_y_x in datas) {
                    datas[val_y_x].push(key);
                } else {
                    datas[val_y_x] = [key];
                }
            }

            this.vertical_datas.push(datas);
        }
    }

    display_labels_y() {
        // Pour chaque label y, créer une ligne avec autant de cellules que de labels x
        for (var label in this.datasets) {
            var datas_dom = document.createElement("tr");
            this.root.appendChild(datas_dom);
        
            var label_dom = document.createElement("th");
            label_dom.innerHTML = label;
            label_dom.className = this.label_y_into_class_name(label);
            label_dom.addEventListener("mouseover", function() { Vart.reveal(this); });
            label_dom.addEventListener("mouseout", function() { Vart.conceal(this); });
            datas_dom.appendChild(label_dom);
            
            for (var i = 0; i < this.labels_x.length; ++i) {
                var data_dom = document.createElement("td");
                data_dom.addEventListener("mouseover", function() { Vart.reveal(this); });
                data_dom.addEventListener("mouseout", function() { Vart.conceal(this); });
                datas_dom.appendChild(data_dom);
            }
        }
    }

    display_vertical_datas() {
        // Pour chaque label x, pour chaque donnée verticale du label, marquer les cellules avec le(s) label(s) y associé(s)
        var column_index = 0;
        for (var datas of this.vertical_datas) { // pour chaque colonne du tableau
            var row_index = 0;

            for (var data in datas) { // pour chaque ligne de cette colonne
                var labels_y = datas[data];

                var cell = this.root.getElementsByTagName("td")[row_index * this.labels_x.length + column_index];

                for (var label of labels_y) {
                    cell.innerHTML = data;
                    cell.className = (cell.className === "") ? this.label_y_into_class_name(label) : cell.className + " " + this.label_y_into_class_name(label);
                }
                ++row_index;
            }
            ++column_index;
        }
    }

    label_y_into_class_name(label) {
        return label.replace(" ", "_");
    }

    static conceal(cell) {
        var table = cell.parentNode.parentNode;
        var classes = cell.className.split(" ");

        for (var class_name of classes) {
            if (class_name !== "") {
                var cells = table.querySelectorAll("." + class_name);
                for (cell of cells) {
                    cell.className = cell.className.replace(" active", "");
                }
            }
        }
    }

    static reveal(cell) {
        var table = cell.parentNode.parentNode;
        var classes = cell.className.split(" ");

        for (var class_name of classes) {
            if (class_name !== "") {
                var cells = table.querySelectorAll("." + class_name);
                for (cell of cells) {
                    cell.className = cell.className + " active";
                }
            }
        }
    }

}


/* Planning */

var plart_style = document.createElement("style");
plart_style.innerHTML = "\
plart {\
    display: table;\
    width: 100%;\
    border-collapse: collapse;\
    text-align: center;\
    color: rgb(80, 80, 80);\
}\
\
plart tr {\
    border-bottom: 1px dashed transparent;\
    border-color: rgba(0, 0, 0, 0.2);\
    opacity: 0.65;\
    transition: all .1s linear;\
}\
\
plart tr:first-child {\
    position: sticky;\
    background: rgba(255, 255, 255, 0.8);\
    opacity: 1;\
    /*z-index: 500;*/\
}\
\
plart tr:last-child {\
    border-bottom: none;\
}\
\
plart tr:hover {\
    opacity: 1;\
}\
\
plart th, plart td {\
    width: 100px;\
}\
\
plart th {\
    padding: 10px 20px;\
}\
\
plart tr:first-child th {\
    padding: 15px 0 20px 0;\
    vertical-align: middle;\
}\
\
plart th:first-letter {\
    text-transform: uppercase;\
}\
\
plart td {\
    padding: 2px 1px 1px 1px;\
    background-clip: content-box;\
}\
\
plart th:first-child {\
    text-align: left;\
    min-width: 130px;\
}\
\
";

document.querySelector("body").appendChild(plart_style);

var chart_colors = [
    "rgb(255, 99, 132)",
    "rgb(255, 159, 64)",
    "rgb(255, 205, 86)",
    "rgb(75, 192, 192)",
    "rgb(54, 162, 235)",
    "rgb(153, 102, 255)",
    "rgb(88, 160, 114)",
    "rgb(209, 83, 214)",
    "rgb(51, 110, 150)",
];

chart_colors = chart_colors.concat(chart_colors).concat(chart_colors).concat(chart_colors).concat(chart_colors).concat(chart_colors).concat(chart_colors).concat(chart_colors).concat(chart_colors);

class Plart {

    constructor(dom, labels_y, datasets, range) {
        // Root

        this.root = document.createElement("plart");
        dom.appendChild(this.root);

        // Variables

        this.datasets = datasets;
        this.labels_y = labels_y;
        this.range = range;
        
        // Let's gooo

        this.labels_x = [];
        this.compute_labels_x();
        
        this.sums = [];
        this.compute_sums();

        this.display_labels_x();

        this.display_datas();
    }

    compute_labels_x() {
        switch (this.range) {
            case "week":
            this.labels_x = ["lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi", "dimanche"];
            break;
        }
    }
    
    display_labels_x() {
        var labels_x_dom = document.createElement("tr");
        this.root.appendChild(labels_x_dom);

        var blank = document.createElement("th");
        labels_x_dom.appendChild(blank);
        
        for (var i = 0; i < this.labels_x.length; ++i) {
            var label_dom = document.createElement("th");
            label_dom.innerHTML = this.labels_x[i] + " (" + this.sums[i] + ")";
            labels_x_dom.appendChild(label_dom);
        }
    }

    display_datas() {
        for (var i = 0; i < this.datasets.length; ++i) {
            var datas_dom = document.createElement("tr");
            this.root.appendChild(datas_dom);
        
            var label_dom = document.createElement("th");
            label_dom.innerHTML = this.labels_y[i];
            datas_dom.appendChild(label_dom);
            
            var datas = this.datasets[i];
            for (var data of datas) {
                var data_dom = document.createElement("td");
                if (data === 1) data_dom.style.backgroundColor = chart_colors[i % chart_colors.length];
                datas_dom.appendChild(data_dom);
            }
        }
    }
        
    compute_sums() {
        for (var i = 0; i < this.labels_x.length; ++i) {
            var sum = 0;
            for (var j = 0; j < this.datasets.length; ++j) {
                sum += this.datasets[j][i];
                this.sums[i] = sum;
            }
        }
    }
    
    position_first_row() {
        var first_tr = this.root.querySelector("tr");
        var root_rect = this.root.getBoundingClientRect();
        first_tr.style.top = root_rect.y + "px"; // first tr is sticky
    }
    
}

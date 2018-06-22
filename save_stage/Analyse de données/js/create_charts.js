
function new_canvas(dom) {
    var canvas = document.createElement("canvas");
    dom.appendChild(canvas);

    return canvas;
}

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

function new_double_time_chart(dom, labels_x, labels_x_format, datas_line, label_line, datas_bar, label_bar) {
    var canvas = new_canvas(dom);
    var ctx = canvas.getContext("2d");

    var cfg = {
        type: "bar",
        data: {
            labels: labels_x,
            datasets: [
                {
                    type: "line",
                    label: label_line,
                    yAxisID: label_line,
                    data: datas_line,
                    pointRadius: 0,
                    fill: false,
                    lineTension: 0,
                    borderWidth: 2,
                    borderColor: chart_colors[1],
                    backgroundColor: chart_colors[1],
                },
                {
                    type: "bar",
                    label: label_bar,
                    yAxisID: label_bar,
                    data: datas_bar,
                    borderWidth: 2,
                }
            ]
        },
        options: {
            responsive: true,
            legend: {
                // display: false,
            },
            tooltips: {
                position: "average",
                mode: "index",
                intersect: false,
            },
            scales: {
                xAxes: [{
                    type: "time",
                    time: {
                        displayFormats: {
                            month: labels_x_format,
                        },
                        unit: "month",
                    },
                    distribution: "series",
                    // ticks: {
                    //     source: "labels",
                    // }
                }],
                yAxes: [
                    {
                        id: label_line,
                        position: "left",
                        scaleLabel: {
                            display: true,
                            labelString: label_line,
                            min: 0,
                        }
                    },
                    {
                        id: label_bar,
                        position: "right",
                        scaleLabel: {
                            display: true,
                            labelString: label_bar,
                            min: 0,
                        }
                    }
                ]
            }
        }
    };

    var chart = new Chart(ctx, cfg);
    return chart;
}

function new_multiple_time_chart(dom, labels_x, datas_labeled) {
    var datasets = [];

    for (label in datas_labeled) {
        var dataset = [];

        dataset["label"] = label;
        dataset["data"] = datas_labeled[label];
        dataset["pointRadius"] = 0;
        dataset["borderWidth"] = 2;
        dataset["borderColor"] = chart_colors[datasets.length];
        dataset["backgroundColor"] = chart_colors[datasets.length];

        datasets.push(dataset);
    }

    var canvas = new_canvas(dom);
    var ctx = canvas.getContext("2d");

    var cfg = {
        type: "bar",
        data: {
            labels: labels_x,
            datasets: datasets,
        },
        options: {
            responsive: true,
            legend: {
                // display: false,
            },
            tooltips: {
                position: "average",
                mode: "index",
                intersect: false,
            },
            scales: {
                xAxes: [{
                    type: "time",
                    distribution: "series",
                }],
                yAxes: [{
                    ticks: {
                        min: 0,
                    },
                }],
            }
        }
    };

    var chart = new Chart(ctx, cfg);
    return chart;
}

function new_simple_pie_chart(dom, labels, datas) {
    var canvas = new_canvas(dom);
    var ctx = canvas.getContext("2d");

    var cfg = {
        type: "pie",
        data: {
            labels: labels,
            datasets: [
                {
                    data: datas,
                    backgroundColor: chart_colors,
                    borderWidth: 1,
                }
            ]
        },
        options: {
            responsive: true,
            legend: {
                display: false,
            },
        }
    };

    var chart = new Chart(ctx, cfg);
    return chart;
}

function new_double_pie_chart(dom, labels, datas_1, datas_2) {
    var canvas = new_canvas(dom);
    var ctx = canvas.getContext("2d");

    var cfg = {
        type: "pie",
        data: {
            labels: labels,
            datasets: [
                {
                    data: datas_1,
                    backgroundColor: chart_colors,
                    borderWidth: 1,
                },
                {
                    data: datas_2,
                    backgroundColor: chart_colors,
                    borderWidth: 1,
                }
            ]
        },
        options: {
            responsive: true,
            // legend: {
            //     display: false,
            // },
        }
    };

    var chart = new Chart(ctx, cfg);
    return chart;
}

function new_multiple_pie_chart(dom, labels, datas, legend) {
    var canvas = new_canvas(dom);
    var ctx = canvas.getContext("2d");

    var datasets = [];
    for (data of datas) {
        var dataset = [];

        dataset["data"] = data;
        dataset["backgroundColor"] = chart_colors;
        dataset["borderWidth"] = 1;

        datasets.push(dataset);
    }

    var cfg = {
        type: "pie",
        data: {
            labels: labels,
            datasets: datasets,
        },
        options: {
            responsive: true,
            legend: {
                display: legend,
            },
        }
    };

    var chart = new Chart(ctx, cfg);
    return chart;
}

function new_positive_scatter_chart(dom, datas, labels, label_x, label_y) {
    var canvas = new_canvas(dom);
    var ctx = canvas.getContext("2d");

    var datasets = [];
    for (var i = 0; i < datas.length; ++i) {
        dataset = [];

        dataset["data"] = datas[i];
        dataset["label"] = labels[i];
        dataset["backgroundColor"] = chart_colors[i];

        datasets.push(dataset);
    }

    var cfg = {
        type: "scatter",
        data: {
            datasets: datasets,
        },
        options: {
            responsive: true,
            legend: {
                display: true,
            },
            scales: {
                xAxes: [{
                    scaleLabel: {
                        display: true,
                        labelString: label_x,
                        min: 0,
                    }
                }],
                yAxes: [
                    {
                        scaleLabel: {
                            display: true,
                            labelString: label_y,
                            min: 0,
                        }
                    },
                ]
            }
        },
    };

    // var chart = new Chart.Scatter(ctx, cfg);

    var chart = new Chart(ctx, cfg);

    return chart;
}

function new_planning(dom, labels_y, datas, unit) {
    return new Plart(dom, labels_y, datas, unit);
}

function new_tree_map(dom, labels, datas) {
    var div = document.createElement("div");
    document.querySelector("tmp").appendChild(div);
    div.id = generate_id();

    var formatted_datas = [];
    for (var i = 0; i < labels.length; ++i) {
        var data = {
            "id" : labels[i],
            "value": datas[i]
        }

        formatted_datas.push(data);
    }

    var visualization = d3plus.viz()
        .container("#" + div.id)
        .data(formatted_datas)
        .type("tree_map")
        .id("id")           // key for which our data is unique on
        .size("value")      // sizing of blocks
        .draw()
    
    setTimeout(function() {
        div.parentNode.removeChild(div);
        dom.appendChild(div);
    }, 800);

    return div;
}

function generate_id() {
    return "r-" + Math.trunc(Math.random() * 1000);
}

/**
 * pmm-payments plugin for Craft CMS
 *
 * Payments Field JS
 *
 * @author    Pleo Digtial
 * @copyright Copyright (c) 2020 Pleo Digtial
 * @link      https://pleodigital.com/
 * @package   Pmmpayments
 * @since     1.0.0
 */

var pmmChart = {
    _init: function() {
        pmmChart.buildChart();
    },

    buildChart: function () {
        var ctx = $( "#canvas-stats" );
        console.log(ctx);
        var chart = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: [{
                    label: 'Demo',
                    data: [{
                        t: "2015-03-15T13:03:00Z",
                        y: 12
                    },
                        {
                            t: "2015-03-25T13:02:00Z",
                            y: 21
                        },
                        {
                            t: "2015-04-25T14:12:00Z",
                            y: 32
                        }
                    ],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)',
                        'rgba(255, 159, 64, 0.2)'
                    ],
                    borderColor: [
                        'rgba(255,99,132,1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    xAxes: [{
                        type: 'time',
                        distribution: 'linear',
                        time: {
                            unit: 'month'
                        }
                    }]
                }
            }
        });
    }
};

$(document).ready(function() {
    pmmChart._init();
});

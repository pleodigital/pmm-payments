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
        // console.log(totals);
        // totals = JSON.parse(totals);
        // console.log(totals);
        var ctx = $( "#canvas-stats" );
        var data = [];
        totals.forEach(function (monthData) {
            data.push({
                t: new Date(monthData.year, monthData.month - 1),
                y: monthData.total
            })
        })
        var chart = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: [{
                    label: 'Wpłaty',
                    data: data,
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
                legend: {
                    display: false
                },
                scales: {
                    xAxes: [{
                        type: 'time',
                        distribution: 'linear',
                        time: {
                            unit: 'month',
                            format: 'MM/YYYY',
                            tooltipFormat:'MM/YYYY'
                        },
                        ticks: {
                            autoSkip: true,
                            maxTicksLimit: 10
                        }
                    }]
                }
            }
        });
        console.log(ctx);
    }
};

$(document).ready(function() {
    // pmmChart._init();
});

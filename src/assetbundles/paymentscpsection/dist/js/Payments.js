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

var pmmPayments = {
    sortBy: null,
    sortOrder: null,
    projectFilter: null,
    startRangeFilter: null,
    endRangeFilter: null,
    paymentTypeFilter: null,
    subNav: '#nav-pmm-payments .subnav a',


    _init: function() {
        this.loadEntries();
        this.registerEvents();
    },

    registerEvents: function() {
        $(pmmPayments.subNav).click(this.onChangePaymentsList);
        $('.content-pane').on('click', '#nav-pmm-payments .subnav a', this.onRefreshEntries);
        $('.content-pane').on('click', '.page-link.load-entries', this.onRefreshEntries);
        $($(pmmPayments.subNav)[2]).click(this.onStats);
        $($(pmmPayments.subNav)[4]).click(this.onRecurring);

        var start = moment().subtract(29, 'days');
        var end = moment();
        $('#datepicker').daterangepicker({
            opens: "left",
            "locale": {
                "format": "MM/DD/YYYY",
                "separator": " - ",
                "applyLabel": "Potwierdź",
                "cancelLabel": "Anuluj",
                "fromLabel": "Od",
                "toLabel": "Do",
                "customRangeLabel": "Własne",
                "weekLabel": "W",
                "daysOfWeek": [
                    "Pon",
                    "Wt",
                    "Śr",
                    "Czw",
                    "Pt",
                    "Sb",
                    "Ndz"
                ],
                "monthNames": [
                    "Styczeń",
                    "Luty",
                    "Marzec",
                    "Kwiecień",
                    "Maj",
                    "Czerwiec",
                    "Lipiec",
                    "Sierpień",
                    "Wrzesień",
                    "Październik",
                    "Listopad",
                    "Grudzień"
                ],
                "firstDay": 0
            },
        }, (start, end) => {this.onRangeChange(start, end)});
        $('.daterangepicker .drp-calendar').css("max-width", "none");
        $(".daterangepicker").css("background-color", "#e4edf6");

        $('body').on('click', 'ul.sort-attributes li a', this.onSetSortBy);
        $('body').on('click', 'ul.sort-directions li a', this.onSetSortOrder);
        $('body').on('click', 'ul.project-filters li a', (e) => this.onSetFilter(e, "p"));
        $('body').on('click', 'ul.payment-type-filters li a', (e) => this.onSetFilter(e, "pt"));
        $('body').on('click', 'div.clear-filters', this.onClearFilters);
        // $('body').on('click', 'ul.sort-attributes li a', this.onSetSortBy);
    },

    // onEmailSettings: function() {
    //     console.log("XD");
    //     var notifyEmail = $("#notifyEmail").value();
    //     var paymentEmail = $("#paymentEmail").value();
    //     console.log(notifyEmail);
    // },

    onRangeChange: function(start, end) {
        console.log(start.format("YYYY-MM-DD") + " - " + end.format("YYYY-MM-DD"));
        $("#datepicker").html(start.format("YYYY-MM-DD") + " - " + end.format("YYYY-MM-DD"));
        this.onSetFilter({
            start: start.format("YYYY-MM-DD"),
            end: end.format("YYYY-MM-DD")
        }, "r");
    },

    onSubCancel: function(event) {
        $.get(`${event.target.dataset.href}?id=${event.target.dataset.id}`, function(res) {
            // console.log(event);
            pmmPayments.loadEntries();
        });
    },

    onRecurring: function(event) {
        event.preventDefault();
        $(".payment-type-filter-name").hide();
        pmmPayments.clearFilters();

        // var element = $('.sidebar-pmmpayments a.sel');
        // $('.content-pane .main .elements').addClass('busy');
        // var url = $(element).attr('href') + '?';
        // console.log(this.projectFilter, this.yearFilter, this.monthFilter);
        // if(this.projectFilter) {
        //     url += 'projectFilter=' + this.projectFilter + '&';
        // }
        // if(this.yearFilter) {
        //     url += 'yearFilter=' + this.yearFilter + '&';
        // }
        // if(this.monthFilter) {
        //     url += 'monthFilter=' + this.monthFilter + '&';
        // }
        // $.get(url, function(html) {
        //     $('.content-pane .main .elements').html(html);
        //     $('.content-pane .main .elements').removeClass('busy');
        // })
    },

    onStats: function(event) {
        $(".sortmenubtn").hide();
        $(".month-filter-name").hide();
        event.preventDefault();
        console.log("onStats");
        $('.sidebar-pmmpayments a').not(this).removeClass('sel');
        $(this).addClass('sel');
        pmmPayments.clearFilters();

        var element = $('.sidebar-pmmpayments a.sel');
        $('.content-pane .main .elements').addClass('busy');
        var url = $(element).attr('href') + '?';
        console.log(this.projectFilter, this.yearFilter, this.monthFilter);
        if(this.projectFilter) {
            url += 'projectFilter=' + this.projectFilter + '&';
        }
        if(this.yearFilter) {
            url += 'yearFilter=' + this.yearFilter + '&';
        }
        if(this.monthFilter) {
            url += 'monthFilter=' + this.monthFilter + '&';
        }
        if (typeof this.paymentTypeFilter === "object" && this.paymentTypeFilter == null) { 
            url += "paymentType=" + 3 + "&";
        } else {
            console.log("paymentType");
            url += 'paymentType=' + this.paymentTypeFilter + '&';
        }
        $.get(url, function(html) {
            $('.content-pane .main .elements').html(html);
            $('.content-pane .main .elements').removeClass('busy');
        })
    },

    onExport: function(event) {
        event.preventDefault();
        var url = event.target.dataset.href;
        if (url.includes("page")) {
            url += '&';
        } else {
            url += '?';
        }
        console.log(this.projectFilter, this.yearFilter, this.monthFilter);
        if(this.sortBy) {
            url += 'sortBy=' + this.sortBy + '&'; 
        } 
        if(this.sortOrder) {
            url += 'sortOrder=' + this.sortOrder + '&'; 
        }
        if(this.projectFilter) {
            url += 'projectFilter=' + this.projectFilter + '&';
        }
        if(this.startRangeFilter) {
            url += 'startRangeFilter=' + this.startRangeFilter + '&';
        }
        if(this.endRangeFilter) {
            url += 'endRangeFilter=' + this.endRangeFilter + '&';
        }
        if (typeof this.paymentTypeFilter === "object" && this.paymentTypeFilter == null) { 
            url += "paymentType=" + 3 + "&";
        } else {
            url += 'paymentType=' + this.paymentTypeFilter + '&';
        }
        console.log(url);
    },

    onChangePaymentsList: function(event) {
        $(".sortmenubtn").show();
        $(".month-filter-name").show();
        $(".payment-type-filter-name").show();
        event.preventDefault();
        $(pmmPayments.subNav).not(this).removeClass('sel');
        $(this).addClass('sel');
        pmmPayments.clearFilters();
        pmmPayments.loadEntries(this);
    },

    onRefreshEntries: function(event) {
        event.preventDefault();
        console.log($(this));
        if($(this).hasClass('disabled')) {
            return;
        }
        pmmPayments.loadEntries(this);
    },

    onSetFilter: function(event, type) {
        if (type === "r") {
            pmmPayments.startRangeFilter = event.start;
            pmmPayments.endRangeFilter = event.end;
            console.log(pmmPayments);
        } else {
            event.preventDefault();
            $(event.target).addClass('sel');
        }

        if (type === "p") {
            pmmPayments.projectFilter = $(event.target).data('project-filter');
            $('.project-filter-name').text($(event.target).text())
            $('ul.project-filters li a.sel').not($(event.target)).removeClass('sel');
        }
        if (type === "pt") {
            pmmPayments.paymentTypeFilter = $(event.target).data("payment-type-filter");
            $('.payment-type-filter-name').text($(event.target).text())
            $('ul.payment-type-filters li a.sel').not($(event.target)).removeClass('sel');

        }
        pmmPayments.loadEntries();
    },

    onClearFilters: function(event) {
        event.preventDefault();
        pmmPayments.clearFilters();
        pmmPayments.loadEntries();
    },

    clearFilters: function() {
        pmmPayments.projectFilter = null;
        pmmPayments.startRangeFilter = null;
        pmmPayments.endRangeFilter = null;
        pmmPayments.paymentTypeFilter = null;
        $('.project-filter-name').text("Filtruj po projektach");
        $('.range-filter-name').text("Filtruj po dacie");
        $('.payment-type-filter-name').text("Filtruj po rodzaju płatności");
        $('ul.filters li a.sel').not(this).removeClass('sel');
    },

    onSetSortBy: function() {      
        pmmPayments.sortBy = $(this).data('sort-by');
        $('ul.sort-attributes li a.sel').not(this).removeClass('sel');
        $(this).addClass('sel');
        $('.sortmenubtn').text($(this).text());
        pmmPayments.loadEntries();
    },

    onSetSortOrder: function() {
        pmmPayments.sortOrder = $(this).data('sort-order');
        $('ul.sort-directions li a.sel').not(this).removeClass('sel');
        $(this).addClass('sel');
        $('.sortmenubtn').attr('data-icon', pmmPayments.sortOrder.toLowerCase());
        pmmPayments.loadEntries();
    },

    loadEntries: function(element) {
        if(!element) {
            element = $(pmmPayments.subNav + '.sel');
        }
        $('.content-pane .main .elements').addClass('busy');
        var url = $(element).attr('href');
        if ($(element).attr("href").includes("page")) {
            url += '&';
        } else {
            url += '?';
        }
        console.log(this.projectFilter, this.yearFilter, this.monthFilter);
        if(this.sortBy) {
            url += 'sortBy=' + this.sortBy + '&'; 
        } 
        if(this.sortOrder) {
            url += 'sortOrder=' + this.sortOrder + '&'; 
        }
        if(this.projectFilter) {
            url += 'projectFilter=' + this.projectFilter + '&';
        }
        if(this.startRangeFilter) {
            url += 'startRangeFilter=' + this.startRangeFilter + '&';
        }
        if(this.endRangeFilter) {
            url += 'endRangeFilter=' + this.endRangeFilter + '&';
        }
        if (typeof this.paymentTypeFilter === "object" && this.paymentTypeFilter == null) { 
            url += "paymentType=" + 3 + "&";
        } else {
            console.log("paymentType");
            url += 'paymentType=' + this.paymentTypeFilter + '&';
        }
        console.log();
        console.log(url);
        $.get(url, function(html) {
            $('.content-pane .main .elements').html(html);
            $('.content-pane .main .elements').removeClass('busy');
            if ($("#settings-notifyEmail").length) {
                $("#toolbar").hide();
            } else {
                $("#toolbar").show();
            }
            // $("#export-btn").click(pmmPayments.onExport);
            console.log("CANCEL", $(".sub-cancel"));
            if ($(".sub-cancel").length) {
                console.log("true");
                $(".sub-cancel").click(pmmPayments.onSubCancel);
            }
        });

    }
}

$(document).ready(function() {
    pmmPayments._init();
});

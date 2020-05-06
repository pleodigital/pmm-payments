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
    yearFilter: null,
    monthFilter: null,


    _init: function() {
        this.loadEntries();
        this.registerEvents();
    },

    registerEvents: function() {
        $('.sidebar-pmmpayments a.load-entries').click(this.onChangePaymentsList);
        $('.content-pane').on('click', 'a.load-entries', this.onRefreshEntries);
        $('.content-pane').on('click', 'a.stats', this.onStats);
        $('body').on('click', 'ul.sort-attributes li a', this.onSetSortBy);
        $('body').on('click', 'ul.sort-directions li a', this.onSetSortOrder);
        $('body').on('click', 'ul.project-filters li a', (e) => this.onSetFilter(e, "p"));
        $('body').on('click', 'ul.year-filters li a', (e) => this.onSetFilter(e, "y"));
        $('body').on('click', 'ul.month-filters li a', (e) => this.onSetFilter(e, "m"));
        $('body').on('click', 'div.clear-filters', this.onClearFilters);
        // $('body').on('click', 'ul.sort-attributes li a', this.onSetSortBy);
    },

    onStats: function(event) {
        event.preventDefault();
        console.log("onStats");
        // pmmChart._init();
        pmmPayments.onRefreshEntries(event);
    },

    onChangePaymentsList: function(event) {
        event.preventDefault();
        $('.sidebar-pmmpayments a').not(this).removeClass('sel');
        $(this).addClass('sel');
        pmmPayments.clearFilters();
        pmmPayments.loadEntries(this);         
    },

    onRefreshEntries: function(event) {
        event.preventDefault();
        if($(this).hasClass('disabled')) {
            return;
        }
        pmmPayments.loadEntries(this);
    },

    onSetFilter: function(event, type) {
        event.preventDefault();
        if (type === "y") {
            pmmPayments.yearFilter = $(event.target).data('year-filter');
            $('.year-filter-name').text($(event.target).text())
            $('ul.year-filters li a.sel').not(this).removeClass('sel');
        }
        if (type === "p") {
            pmmPayments.projectFilter = $(event.target).data('project-filter');
            $('.project-filter-name').text($(event.target).text())
            $('ul.project-filters li a.sel').not(this).removeClass('sel');
        }
        if (type === "m") {
            pmmPayments.monthFilter = $(event.target).data('month-filter');
            $('.month-filter-name').text($(event.target).text())
            $('ul.month-filters li a.sel').not(this).removeClass('sel');
        }
        $(event.target).addClass('sel');
        pmmPayments.loadEntries();
    },

    onClearFilters: function(event) {
        event.preventDefault();
        pmmPayments.clearFilters();
        pmmPayments.loadEntries();
    },

    clearFilters: function() {
        pmmPayments.projectFilter = null;
        pmmPayments.yearFilter = null;
        pmmPayments.monthFilter = null;
        $('.month-filter-name').text("Filtruj po miesiącach");
        $('.year-filter-name').text("Filtruj po latach");
        $('.project-filter-name').text("Filtruj po projektach");
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
            element = $('.sidebar-pmmpayments a.sel');
        }
        $('.content-pane .main .elements').addClass('busy');
        var url = $(element).attr('href') + '?';
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
        if(this.yearFilter) {
            url += 'yearFilter=' + this.yearFilter + '&';
        }
        if(this.monthFilter) {
            url += 'monthFilter=' + this.monthFilter + '&';
        }
        $.get(url, function(html) {
            $('.content-pane .main .elements').html(html);
            $('.content-pane .main .elements').removeClass('busy');
        })      
    }
}

$(document).ready(function() {
    pmmPayments._init();
});

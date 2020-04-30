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
        $('body').on('click', 'ul.sort-attributes li a', this.onSetSortBy);
        $('body').on('click', 'ul.sort-directions li a', this.onSetSortOrder);
        $('body').on('click', 'ul.filters li a', this.onSetFilter);
        // $('body').on('click', 'ul.sort-attributes li a', this.onSetSortBy);
    },

    onChangePaymentsList: function(event) {
        event.preventDefault();
        $('.sidebar-pmmpayments a').not(this).removeClass('sel');
        $(this).addClass('sel');
        pmmPayments.loadEntries(this);         
    },

    onRefreshEntries: function(event) {
        event.preventDefault();
        if($(this).hasClass('disabled')) {
            return;
        }
        pmmPayments.loadEntries(this);
    },

    onSetFilter: function(event) {
        event.preventDefault();
        pmmPayments.projectFilter = $(this).data('project-filter');
        pmmPayments.yearFilter = $(this).data('year-filter');
        pmmPayments.monthFilter = $(this).data('month-filter');
        $('ul.filters li a.sel').not(this).removeClass('sel');
        $(this).addClass('sel');
        pmmPayments.loadEntries();
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
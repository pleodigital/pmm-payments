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
    _init: function() {
        this.loadEntries();
        this.registerEvents();
    },

    registerEvents: function() {
        $('.sidebar-pmmpayments a.load-entries').click(this.onChangePaymentsList);
        $('.content-pane').on('click', 'a.load-entries', this.onRefreshEntries);
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

    loadEntries: function(element) {
        if(!element) {
            element = $('.sidebar-pmmpayments a.sel');
        }
        $('.content-pane .main .elements').addClass('busy');
        $.get($(element).attr('href'), function(html) {
            $('.content-pane .main .elements').html(html);
            $('.content-pane .main .elements').removeClass('busy');
        })      
    }
}

$(document).ready(function() {
    pmmPayments._init();
});
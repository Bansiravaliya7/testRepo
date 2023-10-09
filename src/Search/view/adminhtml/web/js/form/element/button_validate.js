define(['Magento_Ui/js/form/components/button', 'underscore', 'uiRegistry', 'jquery'], function (Button, _, registry, $) {
    'use strict';
    
    return Button.extend({
        defaults: {},
        
        initialize: function () {
            this._super();
            window.registry = registry
        },
        
        validate: function () {
            const sources = this.sourceNames;
            const bindTo = this.bindTo;
            const data = {};
            
            _.each(sources, source => {
                const sourceObject = registry.get(source);
                data[sourceObject.parameter] = sourceObject.value();
            })
            
            $.ajax({
                showLoader: true,
                url:        this.validateUrl,
                data:       data,
                type:       'GET',
                dataType:   'json'
            }).done(response => {
                $('[data-role=result-message]').remove();
                
                const message = $('<div>')
                    .addClass('mst-search-validator')
                    .attr('data-role', 'result-message')
                    .html(response.result);
                
                $(message).append(response.html);
                
                message.appendTo('[data-index="' + bindTo + '"]');
            }).fail(response => {
                $('[data-role=result-message]').remove();
                
                var message = $('<div>')
                    .css('margin-top', '10px')
                    .addClass('message')
                    .addClass('message-error')
                    .attr('data-role', 'result-message')
                    .html(response.responseText);
                
                message.appendTo('[data-index="' + bindTo + '"]');
            });
        }
    });
});
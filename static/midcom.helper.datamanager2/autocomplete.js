$.widget( "custom.category_complete", $.ui.autocomplete,
{
    _renderMenu: function(ul, items)
    {
        var self = this,
        currentCategory = "";
        $.each(items, function(index, item)
        {
            if (item.category !== currentCategory)
            {
                ul.append( "<li class='ui-autocomplete-category'>" + item.category + "</li>" );
                currentCategory = item.category;
            }
            self._renderItemData(ul, item);
        });
    }
});

if (typeof JSON == 'undefined')
{
    JSON =
    {
        stringify: function(value)
        {
            var ret = '';
            if ($.isArray(value))
            {
                ret += '[';
                $.each(value, function(index, val)
                {
                    ret += val;
                    if (index + 1 != value.length)
                    {
                        ret += ',';
                    }
                });
                ret += ']';
            }
            return ret;
        }
    }
}

var midcom_helper_datamanager2_autocomplete =
{
    get_default_options: function()
    {
        return {
            minLength: 2,
            source: midcom_helper_datamanager2_autocomplete.query,
            select: midcom_helper_datamanager2_autocomplete.select,
            position: {collision: 'flipfit'}
        }
    },
    query: function(request, response)
    {
        var query_options_var = $('.ui-autocomplete-loading').attr('id').replace(/_search_input$/, '') + '_handler_options',
        query_options = window[query_options_var];
        query_options.term = request.term;
        $.ajax({
            url: query_options.handler_url,
            dataType: "json",
            data: query_options,
            success: function(data)
            {
                response(data);
            },
            error: function(jqXHR, textStatus, errorThrown)
            {
                $('.ui-autocomplete-loading')
                    .addClass('ui-autocomplete-error')
                    .prop('title', errorThrown);
                response();
            }
        });
    },

    select: function(event, ui)
    {
        var identifier = $(event.target).attr('id').replace(/_search_input$/, ''),
        handler_options = window[identifier + '_handler_options'];

        if ($('#' + identifier + '_selection_holder').length > 0)
        {
            midcom_helper_datamanager2_autocomplete.add_selected(identifier, ui.item.id, ui.item.label, 'autocomplete-new');
            midcom_helper_datamanager2_autocomplete.update_selection(identifier, ui.item.id, 'add');
        }
        else
        {
            $('#' + identifier + '_selection').val(JSON.stringify([ui.item.id]));
            $(event.target).data('selected', ui.item.label);
        }
    },

    open: function(event, ui)
    {
        var offset = $(this).offset(),
        height = $(window).height() - (offset.top + $(this).height() + 10);
        $('ul.ui-autocomplete').css('maxHeight', height);
    },

    /**
     * Enable the creation mode
     */
    enable_creation_mode: function(identifier, creation_url)
    {
        var dialog_id = identifier + '_creation_dialog',
        input = $('#' + identifier + '_search_input');

        var dialog_html = '<div class="autocomplete_widget_creation_dialog" id="' + dialog_id + '">';
        dialog_html += '<div class="autocomplete_widget_creation_dialog_content_holder">';
        dialog_html += "</div>";
        dialog_html += "</div>";

        var button_html = '<div class="autocomplete_widget_create_button" id="' + identifier + '_create_button">';
        button_html += "</div>";

        var html = button_html + dialog_html;
        jQuery(html).insertAfter(input);

        input.css({float: 'left'});

        creation_dialog = jQuery('#' + identifier + '_creation_dialog');
        create_button = jQuery('#' + identifier + '_create_button');
        create_button.css('display', 'block');
        create_button.bind('click', function()
        {
            if (jQuery('#' + identifier + '_creation_dialog').css('display') === 'block')
            {
                jQuery('#' + identifier + '_creation_dialog').hide();
                return;
            }

            creation_url += '?chooser_widget_id=' + identifier;

            if (jQuery('#' + identifier + '_creation_dialog_content'))
            {
                var iframe = ['<iframe src="' + creation_url + '"'];
                iframe.push('id="' + identifier + '_creation_dialog_content"');
                iframe.push('class="autocomplete_widget_creation_dialog_content"');
                iframe.push('frameborder="0"');
                iframe.push('marginwidth="0"');
                iframe.push('marginheight="0"');
                iframe.push('width="600"');
                iframe.push('height="450"');
                iframe.push('scrolling="auto"');
                iframe.push('/>');

                var iframe_html = iframe.join(' ');
                jQuery('.autocomplete_widget_creation_dialog_content_holder', creation_dialog).html(iframe_html);
            }
            jQuery('#' + identifier + '_creation_dialog').show();
        });
    },

    /**
     * Add creation result to form (from chooser-compatible data)
     */
    add_result_item: function(identifier, data)
    {
        var query_options = window[identifier + '_handler_options'],
        input_value = '';

        jQuery('#' + identifier + '_selection').val(JSON.stringify([data[query_options.id_field]]));
        jQuery(query_options.result_headers).each(function(index, value)
        {
            if (typeof data[value.name] !== 'undefined')
            {
                input_value += data[value.name] + ', ';
            }
        });
        jQuery('#' + identifier + '_search_input').val(input_value.replace(/, $/, ''));
    },

    create_dm2_widget: function(selector, min_length)
    {
        var identifier = selector.replace(/_search_input$/, ''),
        handler_options = window[identifier + '_handler_options'],
        options =  $.extend({minLength: min_length}, midcom_helper_datamanager2_autocomplete.get_default_options()),
        input = $('#' + selector);

        input.autocomplete(options);
        input.parent().prepend('<span class="autocomplete-selection-holder" id="' + identifier + '_selection_holder"></span>')
        if (!$.isEmptyObject(handler_options.preset))
        {
            $.each(handler_options.preset, function(id, text)
            {
                midcom_helper_datamanager2_autocomplete.add_selected(identifier, id, text, 'autocomplete-saved');
            });
        }

        $('.autocomplete-selection-holder').on('click', '.autocomplete-action-icon', function()
        {
            var item = $(this).parent(),
            item_id = item.data('id');
            if (item.hasClass('autocomplete-selected'))
            {
                if (item.hasClass('autocomplete-saved'))
                {
                    item.removeClass('autocomplete-selected');
                    item.addClass('autocomplete-todelete');
                }
                else
                {
                    item.remove();
                }
                if (handler_options.allow_multiple !== true)
                {
                    input.show();
                }
                midcom_helper_datamanager2_autocomplete.update_selection(identifier, item_id, 'remove');
            }
            else if (item.hasClass('autocomplete-todelete'))
            {
                midcom_helper_datamanager2_autocomplete.restore_item(identifier, item);
            }
        });
    },

    restore_item: function(identifier, item)
    {
        var handler_options = window[identifier + '_handler_options'];

        item.removeClass('autocomplete-todelete');
        item.addClass('autocomplete-selected');
        if (handler_options.allow_multiple !== true)
        {
            item.parent().find('.autocomplete-new').remove();
            input.hide();
        }
        midcom_helper_datamanager2_autocomplete.update_selection(identifier, item.data('id'), 'add');
    },

    add_selected: function(identifier, item_id, text, status)
    {
        var handler_options = window[identifier + '_handler_options'],
        selection_holder = $('#' + identifier + '_selection_holder'),
        existing_item;

        if (selection_holder.find('[data-id="' + item_id + '"]').length > 0)
        {
            existing_item = selection_holder.find('[data-id="' + item_id + '"]');
            if (existing_item.hasClass('autocomplete-todelete'))
            {
                midcom_helper_datamanager2_autocomplete.restore_item(identifier, existing_item);
            }
            return;
        }

        $('<span class="autocomplete-item autocomplete-selected ' + status + '" data-id="' + item_id + '"><span class="autocomplete-item-label">' + text + '</span></span>')
            .append('<span class="autocomplete-action-icon"></span>')
            .prependTo(selection_holder);

        if (handler_options.allow_multiple !== true)
        {
            $('#' + identifier + '_search_input').hide();
        }
    },

    update_selection: function(identifier, item_id, operation)
    {
        var selection = JSON.parse($('#' + identifier + '_selection').val()),
        new_selection = [];

        if (operation === 'add')
        {
            new_selection = selection;
            if ($.inArray(item_id, new_selection) === -1)
            {
                new_selection.push(item_id);
            }
        }
        else
        {
            $.each(selection, function(index, item)
            {
                if (item !== item_id)
                {
                    new_selection.push(item);
                }
            });
        }
        $('#' + identifier + '_selection').val(JSON.stringify(new_selection));
    },

    /**
     * Generate and attach HTML for autocomplete widget (for use outside of DM2)
     */
    create_widget: function(config, autocomplete_options)
    {
        var default_config =
        {
            id_field: 'guid',
            auto_wildcards: 'both',
            categorize_by_parent_label: false,
            helptext: '',
            default_value: ''
        },
        default_value = config.default_value || default_config.default_value,
        helptext = config.helptext || default_config.helptext;

        autocomplete_options = $.extend({autoFocus: true}, midcom_helper_datamanager2_autocomplete.get_default_options(), autocomplete_options || {});
        window[config.id + '_handler_options'] = $.extend({}, default_config, config.widget_config);

        var widget_html = '<input type="text" id="' + config.id + '_search_input" name="' + config.id + '_search_input" style="display: none" class="batch_widget" value="' + helptext + '" />';
        widget_html += '<input type="hidden" id="' + config.id + '_selection" name="' + config.id + '_selection" value="' + default_value + '" />';

        if (typeof config.insertAfter !== 'undefined')
        {
            $(widget_html).insertAfter($(config.insertAfter));
        }
        else if (typeof config.appendTo !== 'undefined')
        {
            $(widget_html).appendTo($(config.appendTo));
        }

        if (helptext !== '')
        {
            if ($('#' + config.id + '_search_input').val() === helptext)
            {
                $('#' + config.id + '_search_input').addClass('autocomplete_helptext_shown');
            }

            $('#' + config.id + '_search_input')
                .bind('focus', function()
                {
                    if ($(this).val() === helptext)
                    {
                        $(this)
                            .val('')
                            .removeClass('autocomplete_helptext_shown');
                    }
                })
                .bind('blur', function()
                {
                    if ($(this).val() === '')
                    {
                        $(this).data('selected', false);
                    }
                    if (!$(this).data('selected'))
                    {
                        var selection_holder_id = $(this).attr('id').replace(/_search_input$/, '') + '_selection';
                        $('#' + selection_holder_id).val('');

                        $(this)
                            .val(helptext)
                            .addClass('autocomplete_helptext_shown');
                    }
                    else
                    {
                        $(this).val($(this).data('selected'));
                    }
                });
        }

        if (window[config.id + '_handler_options'].categorize_by_parent_label === true)
        {
            $('#' + config.id + '_search_input').category_complete(autocomplete_options);
        }
        else
        {
            $('#' + config.id + '_search_input').autocomplete(autocomplete_options);
        }
    }
};

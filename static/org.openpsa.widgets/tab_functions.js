var org_openpsa_widgets_tabs =
{
    loaded_scripts: [],
    form_append: "&midcom_helper_datamanager2_cancel=Cancel",
    initialize: function(uiprefix)
    {
        org_openpsa_widgets_tabs.bind_events(uiprefix);

        $('#tabs').tabs({
            beforeLoad: function(event, ui)
            {
                if (ui.tab.data("loaded"))
                {
                    event.preventDefault();
                    return;
                }

                ui.jqXHR.success(function()
                {
                    ui.tab.data("loaded", true);
                });
                ui.ajaxSettings.dataFilter = org_openpsa_widgets_tabs.load_head_elements
            },
            load: function()
            {
                $(window).trigger('resize');
            },
            activate: function(event, ui)
            {
                $.history.load('!' + ui.newPanel.attr('id'));
                $(window).trigger('resize');
            },
            create: function(event, ui)
            {
                $.history.init(org_openpsa_widgets_tabs.history_loader);
            }
        });

    },
    history_loader: function(hash)
    {
        var tab_id = 0;
        if (hash !== '')
        {
            tab_id = parseInt(hash.replace(/!ui-tabs-/, '')) - 1;
        }

        if ($('#tabs').tabs('option', 'selected') != tab_id)
        {
            $('#tabs').tabs('select', tab_id);
        }
    },
    bind_events: function(uiprefix)
    {
        $('#tabs')
            .delegate('.ui-state-active a', 'mousedown', function(event)
            {
                if (event.which !== 1)
                {
                    return;
                }
                var url = $(this).attr('href').replace(new RegExp('/' + uiprefix + '/'), '/');
                location.href = url;
            })
            .delegate('.ui-tabs-panel a', 'click', function(event){org_openpsa_widgets_tabs.intercept_clicks(event);})

            //bind click functions so the request can pass if it should saved or cancelled
            .delegate('input[type=submit]:not(.tab_escape)', 'click', function(event)
            {
                org_openpsa_widgets_tabs.form_append = "&" + $(event.currentTarget).attr('name') + "=" + $(event.currentTarget).val();
                return true;
            })

            //since this is loaded in a tab - change the submit-function of
            // an occurring form - so the result will be loaded in the tab also
            .delegate('form:not(.tab_escape)', "submit", function(event)
            {
                if ($(event.currentTarget).attr('onsubmit'))
                {
                    return;
                }
                var send_data = $(this).serialize() + org_openpsa_widgets_tabs.form_append;

                $.ajax(
                {
                    data: send_data,
                    dataFilter: org_openpsa_widgets_tabs.load_head_elements,
                    type: $(this).attr("method"),
                    url: $(this).attr("action"),
                    success: function(data, textStatus, jqXHR)
                    {
                        $(":not(.ui-tabs-hide) > .tab_div").html(data);
                    }
                });
                return false;
            });
    },
    load_head_elements: function(data, type)
    {
        data = data.replace(/^[\s\S]+?<HEAD_ELEMENTS>/m, '<HEAD_ELEMENTS>');
        var regex = /^<HEAD_ELEMENTS>(.+?)<\/HEAD_ELEMENTS>/;
        regex.exec(data);
        var head_elements = $.parseJSON(RegExp.$1);
        data = data.substr((RegExp.$1.length + 31));

        $.each(head_elements.head_js, function(index, jscall)
        {
            if (   typeof jscall.url !== 'undefined'
                && $('script[src="' + jscall.url + '"]').length === 0
                && $.inArray(jscall.url, org_openpsa_widgets_tabs.loaded_scripts) === -1)
            {
                org_openpsa_widgets_tabs.loaded_scripts.push(jscall.url);
                $.ajax({url: jscall.url, cache: true, dataType: 'script', async: false});
            }
            else if (   typeof jscall.content !== 'undefined'
                     && jscall.content.length > 0)
            {
                $('<script type="text/javascript">' + jscall.content + '</script>');
            }
        });

        var insertion_point = $('link[rel="stylesheet"]:first');
        $.each(head_elements.head_css, function(index, data)
        {
            if (   typeof data.type === 'undefined'
                || typeof data.href === 'undefined'
                || data.type !== 'text/css')
            {
                return;
            }
            if ($('link[href="' + data.href + '"]').length !== 0)
            {
                insertion_point = $('link[href="' + data.href + '"]');
            }
            else
            {
                var tag = '<link';
                $.each(data, function(key, value)
                {
                    tag += ' ' + key + '="' + value + '"';
                });
                tag += ' />';
                insertion_point.after(tag);
                insertion_point = insertion_point.next();
            }
        });

        return data;
    },
    intercept_clicks: function(event)
    {
        if ($(event.currentTarget).attr('onclick'))
        {
            return true;
        }

        var href = $(event.currentTarget).attr('href');

        if (!href.match(/\/uitab\//))
        {
            return true;
        }
        //for now, only links to plugins will be displayed inside the tab
        if (   !href.match(/\/__mfa\/org\.openpsa/)
            || $(event.currentTarget).hasClass('tab_escape'))
        {
            location.href = href.replace(/\/uitab\/+/, '/');
            event.preventDefault();
            return false;
        }

        if (href.slice(href.length - 1, href.length) !== '#')
        {
            $.ajax({
                url: href,
                dataFilter: org_openpsa_widgets_tabs.load_head_elements,
                success: function(data, textStatus, jqXHR)
                {
                    $(":not(.ui-tabs-hide) > .tab_div").html(data);
                }
            });

            event.preventDefault();
            return false;
        }
    }
};

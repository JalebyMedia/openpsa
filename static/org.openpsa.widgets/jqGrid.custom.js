var org_openpsa_jqgrid_presets =
{
    autowidth: true,
    altRows: true,
    altclass: 'even',
    deselectAfterSort: false,
    forceFit: true,
    gridview: true,
    headertitles: true,
    height: 'auto',
    hoverrows: true,
    shrinkToFit: true,
    sortable: true,
    jsonReader:
    {
        repeatitems: false,
        id: '0'
    }
};

$.jgrid.defaults = $.extend($.jgrid.defaults, org_openpsa_jqgrid_presets);

var org_openpsa_grid_resize =
{
    timer: false,
    containment: '#content-text',
    firstrun: true,
    add_header_controls: function()
    {
        $('table.ui-jqgrid-btable').jqGrid('setGridParam', {onHeaderClick: function(gridstate)
        {
            $(this).closest('.ui-jqgrid').find('.ui-jqgrid-titlebar-maximize').toggle(gridstate === 'visible');
            $(window).trigger('resize');
        }});

        org_openpsa_grid_resize.attach_maximizer($('.ui-jqgrid-titlebar'));
    },
    event_handler: function(resizing)
    {
        if (org_openpsa_grid_resize.firstrun)
        {
            org_openpsa_grid_resize.firstrun = false;
            org_openpsa_grid_resize.add_header_controls();
        }
        if (resizing)
        {
            if (!org_openpsa_grid_resize.timer)
            {
                $(org_openpsa_grid_resize.containment).addClass('openpsa-resizing');
            }
            else
            {
                clearTimeout(org_openpsa_grid_resize.timer);
            }
            org_openpsa_grid_resize.timer = setTimeout(org_openpsa_grid_resize.end_resize, 200);
        }
        if ($('.ui-jqgrid-maximized').length > 0)
        {
            org_openpsa_grid_resize.maximize_height($('.ui-jqgrid-maximized'));
            org_openpsa_grid_resize.fill_width($('.ui-jqgrid-maximized'));
        }
        else
        {
            org_openpsa_grid_resize.set_height($('.fill-height'), 'fill');
            org_openpsa_grid_resize.set_height($('.crop-height'), 'crop');
            org_openpsa_grid_resize.fill_width($('.full-width'));
        }
    },
    end_resize: function()
    {
        org_openpsa_grid_resize.timer = false;
        $(org_openpsa_grid_resize.containment).removeClass('openpsa-resizing');
    },
    attach_maximizer: function(items)
    {
        $(items).each(function()
        {
            $('<a role="link" class="ui-jqgrid-titlebar-maximize HeaderButton" style="right: 20px;"><span class="ui-icon ui-icon-circle-zoomin"></span></a>')
                .bind('click', function()
                {
                    var container = $(this).closest('.ui-jqgrid').parent();

                    if (container.hasClass('ui-jqgrid-maximized'))
                    {
                        $(this).removeClass('ui-state-active ui-state-hover');

                        var jqgrid_id = container.find('table.ui-jqgrid-btable').attr('id'),
                        placeholder = $('#maximized_placeholder');

                        try
                        {
                            $("#" + jqgrid_id).jqGrid().setGridHeight(placeholder.data('orig_height'));
                        }
                        catch(e){}

                        container
                            .detach()
                            .removeClass('ui-jqgrid-maximized')
                            .insertBefore(placeholder)
                            .find('.ui-jqgrid-titlebar-close').show();
                        placeholder.remove();
                        $(org_openpsa_grid_resize.containment).children().removeClass('ui-jqgrid-maximized-background');
                    }
                    else
                    {
                        $(this).addClass('ui-state-active');
                        $(org_openpsa_grid_resize.containment).scrollTop(0);
                        $('<div id="maximized_placeholder"></div>')
                            .data('orig_height', container.find('.ui-jqgrid-bdiv').outerHeight())
                            .insertAfter(container);
                        container
                            .detach()
                            .addClass('ui-jqgrid-maximized')
                            .prependTo($(org_openpsa_grid_resize.containment))
                            .find('.ui-jqgrid-titlebar-close').hide();
                        $(org_openpsa_grid_resize.containment).children(':not(:first-child)').addClass('ui-jqgrid-maximized-background');
                    }
                    $(window).trigger('resize');
                })
                .bind('mouseenter', function()
                {
                    $(this).addClass('ui-state-hover');
                })
                .bind('mouseleave', function()
                {
                    $(this).removeClass('ui-state-hover');
                })
                .prependTo($(this));
            if ($(this).closest('.ui-jqgrid').find('.ui-jqgrid-btable').data('maximized'))
            {
                $(this).find('.ui-jqgrid-titlebar-maximize').trigger('click');
            }
            if ($(this).closest('.ui-jqgrid').find('.ui-jqgrid-btable').is(':hidden'))
            {
                $(this).find('.ui-jqgrid-titlebar-maximize').hide();
            }
        });
    },
    fill_width: function(items)
    {
        if (items.length === 0)
        {
            return;
        }
        var new_width;

        $.each(items, function(index, item)
        {
            if (items.hasClass('ui-jqgrid-maximized'))
            {
                new_width = $(org_openpsa_grid_resize.containment).attr('clientWidth') - 12;
            }
            else
            {
                //calculate for each item separately to take care of floating neighbors
                new_width = $(item).width() - 12;
            }
            $(item).find('.ui-jqgrid table.ui-jqgrid-btable').each(function()
            {
                var id = $(this).attr('id')
                panel = $("#gbox_" + id).closest('.ui-tabs-panel');
                if (   panel.length > 0
                    && panel.hasClass('ui-tabs-hide'))
                {
                    return;
                }
                try
                {
                    var old_width = $("#" + id).jqGrid().getGridParam('width');
                    if (new_width != old_width)
                    {
                        $("#" + id).jqGrid().setGridWidth(new_width);
                    }
                }
            catch(e){}
        });
        });
    },
    set_height: function(items, mode)
    {
        if (items.length === 0)
        {
            return;
        }

        var grids_content_height = 0,
        container_height = $(org_openpsa_grid_resize.containment).height(),
        container_nongrid_height = 0,
        visible_grids = 0,
        grid_heights = {},
        minimum_height = 21;

        if ($('#org_openpsa_resize_marker_end').length === 0)
        {
            $(org_openpsa_grid_resize.containment)
                .append('<div id="org_openpsa_resize_marker_end"></div>')
                .prepend('<div id="org_openpsa_resize_marker_start"></div>');
        }
        container_nongrid_height = $('#org_openpsa_resize_marker_end').position().top - $('#org_openpsa_resize_marker_start').position().top;

        items.each(function()
        {
            var grid_body = $("table.ui-jqgrid-btable", $(this));
            if (grid_body.length > 0)
            {
                var grid_height = grid_body.parent().parent().height(),
                content_height = grid_body.outerHeight();
                if (    content_height === 0
                    && (   grid_body.jqGrid('getGridParam', 'datatype') !== 'local'
                        || (   grid_body.jqGrid('getGridParam', 'treeGrid') === true
                            && grid_body.jqGrid('getGridParam', 'treedatatype') !== 'local')))
                {
                    content_height = 100;
                }

                if (   grid_body.jqGrid('getGridParam', 'gridstate') === 'visible'
                    && $(this).is(':visible'))
                {
                    grid_heights[grid_body.attr('id')] = content_height;
                    grids_content_height += content_height;
                    container_nongrid_height -= grid_height;
                    visible_grids++;
                }
            }
        });

        var available_space = container_height - container_nongrid_height;

        if (   grids_content_height === 0
            || available_space <= minimum_height * visible_grids)
        {
            return;
        }

        if (   available_space > grids_content_height
            && mode !== 'fill')
        {
            $.each(grid_heights, function(grid_id, content_height)
            {
                set_param(grid_id, content_height);
            });
            return;
        }

        $.each(grid_heights, function(grid_id, content_height)
        {
            var new_height = available_space * (content_height / grids_content_height);
            if (new_height < minimum_height)
            {
                available_space -= minimum_height;
                grids_content_height -= content_height;
                set_param(grid_id, minimum_height);
                delete grid_heights[grid_id];
            }
        });

        $.each(grid_heights, function(grid_id, content_height)
        {
            var new_height = Math.round(available_space * (content_height / grids_content_height));
            set_param(grid_id, new_height);
        });

        function set_param(grid_id, value)
        {
            if ($("#" + grid_id).parent().parent().height() !== value)
            {
                try
                {
                    $("#" + grid_id).jqGrid().setGridHeight(value);
                }
                catch(e){}
                if ($("#" + grid_id).data('vScroll'))
                {
                    $("#" + grid_id).closest(".ui-jqgrid-bdiv").scrollTop($("#" + grid_id).data('vScroll'));
                    $("#" + grid_id).removeData('vScroll')
                }
            }
        }
    },
    maximize_height: function(part)
    {
        var part_height = $(part).outerHeight(true),
        grid_height = $("table.ui-jqgrid-btable", part).parent().parent().outerHeight(),
        new_height = $(org_openpsa_grid_resize.containment).height() + grid_height - part_height;

        try
        {
            $("table.ui-jqgrid-btable", part).jqGrid().setGridHeight(new_height);
        }
        catch(e){}
    }
};

org_openpsa_resizers.append_handler('grid', org_openpsa_grid_resize.event_handler);

var org_openpsa_grid_editable =
{
    grid_id: '',
    last_added_row: 0,
    default_options:
    {
        keys: true,
        afterrestorefunc: function(id)
        {
            org_openpsa_grid_editable.toggle(id, false);
        },
        aftersavefunc: function(id)
        {
            org_openpsa_grid_editable.toggle(id, false);
        },
        oneditfunc: function(id)
        {
            org_openpsa_grid_editable.toggle(id, true);
        },
        successfunc: function(data)
        {
            var return_values = $.parseJSON(data.responseText);
            return [true, return_values, return_values.id];
        }
    },
    toggle: function(id, edit_mode)
    {
        $('#save_button_' + id).toggleClass('hidden', !edit_mode);
        $('#cancel_button_' + id).toggleClass('hidden', !edit_mode);
        $('#edit_button_' + id).toggleClass('hidden', edit_mode);
    },

    enable_inline: function (grid_id, custom_options)
    {
        var lastsel,
        self = this;
        self.options = $.extend({}, self.default_options, custom_options);
        self.grid_id = grid_id;
        $('#' + grid_id).jqGrid('setGridParam',
        {
            onSelectRow: function(id)
            {
                if (id && id !== lastsel)
                {
                    $('#' + id).restoreRow(lastsel);
                    lastsel = id;
                }
                self.editRow(id);
            }
        });
        self.add_inline_controls();
        var create_button_parameters =
        {
            caption: "",
            buttonicon: "ui-icon-plus",
            onClickButton: function()
            {
                var new_id = 'new_' + self.last_added_row++;
                $('#' + self.grid_id).jqGrid('addRowData', new_id, {}, 'last');
            }
        };
        $('#' + grid_id)
            .jqGrid('navGrid', "#p_" + grid_id, {add: false, del:false, refresh: false, edit: false, search: false})
            .jqGrid('navButtonAdd', "#p_" + grid_id, create_button_parameters);

    },
    editRow: function(id)
    {
        $('#' + this.grid_id).jqGrid('editRow', id, this.options);
        $('#cancel_button_' + id).closest("tr").find('input[type="text"]:first:visible').focus();
    },
    saveRow: function(id)
    {
        $('#' + this.grid_id).jqGrid('saveRow', id, this.options);
    },
    restoreRow: function(id)
    {
        $('#' + this.grid_id).jqGrid('restoreRow', id, this.options);
    },
    deleteRow: function(id)
    {
        var edit_url = $('#' + this.grid_id).jqGrid('getGridParam', 'editurl'),
        rowdata = $('#' + this.grid_id).jqGrid('getRowData', id),
        self = this;
        rowdata.oper = 'del';

        $.post(edit_url, rowdata, function(data, textStatus, jqXHR)
        {
            $('#' + self.grid_id).jqGrid('delRowData', id);
            if (   typeof self.options.aftersavefunc !== 'undefined'
                && $.isFunction(self.options.aftersavefunc))
            {
                self.options.aftersavefunc(0, []);
            }
        });
    },
    add_inline_controls: function()
    {
        var rowids = $("#" + this.grid_id).jqGrid('getDataIDs'),
        self = this,
        i, current_rowid;

        for (i = 0; i < rowids.length; i++)
        {
            current_rowid = rowids[i];
            var be = "<input class='row_button row_edit' id='edit_button_" + current_rowid + "' type='button' value='E' />",
            bs = "<input class='row_button row_save hidden' id='save_button_" + current_rowid + "' type='button' value='S' />",
            bc = "<input class='row_button row_cancel hidden' id='cancel_button_" + current_rowid + "' type='button' value='C' />",
            bd = "<input class='row_button row_delete' id='delete_button_" + current_rowid + "' type='button' value='D' />";
            $("#" + this.grid_id).jqGrid('setRowData', current_rowid, {actions: be + bs + bc + bd});
        }

        $(".row_edit").live('click', function()
        {
            var id = $(this).attr('id').replace(/^edit_button_/, '');
            self.editRow(id);
        });
        $(".row_delete").live('click', function()
        {
            var id = $(this).attr('id').replace(/^delete_button_/, '');
            self.deleteRow(id);
        });
        $(".row_save").live('click', function()
        {
            var id = $(this).attr('id').replace(/^save_button_/, '');
            self.saveRow(id);
        });
        $(".row_cancel").live('click', function()
        {
            var id = $(this).attr('id').replace(/^cancel_button_/, '');
            self.restoreRow(id);
        });
    }
};

var org_openpsa_grid_footer =
{
    set_field: function(grid_id, colname, operation)
    {
        var value = $('#' + grid_id).jqGrid('getCol', colname, false, operation),
        footerdata = {};
        footerdata[colname] = value;
        $('#' + grid_id).jqGrid('footerData', 'set', footerdata);
    }
};

var org_openpsa_grid_helper =
{
    event_handler_added: false,
    active_grids: [],
    set_tooltip: function (grid_id, column, tooltip)
    {
        var thd = $("thead:first", $('#' + grid_id)[0].grid.hDiv)[0];
        $("tr.ui-jqgrid-labels th:eq(" + column + ")", thd).attr("title", tooltip);
    },
    setup_grid: function (grid_id, config)
    {
        var identifier = location.hostname + location.href + '#' + grid_id,
        saved_values = {};
        if (   typeof window.localStorage !== 'undefined'
            && window.localStorage)
        {
            org_openpsa_grid_helper.active_grids.push(grid_id);
            saved_values = $.parseJSON(window.localStorage.getItem(identifier));
            if (saved_values)
            {
                if (typeof saved_values.custom_keys !== 'undefined')
                {
                    var keys = saved_values.custom_keys;
                    delete saved_values.custom_keys;
                    $('#' + grid_id).data('vScroll', keys.vScroll);
                    $('#' + grid_id).data('maximized', keys.maximized);
                }
                config = $.extend(config, saved_values);
            }
            if (   config.data
                && config.data.length <= (config.rowNum * config.page))
            {
                config.page = Math.ceil(config.data.length / config.rowNum);
            }

            if (org_openpsa_grid_helper.event_handler_added === false)
            {
                $(window).bind('unload', org_openpsa_grid_helper.save_grid_data);
            }
        }
        $('#' + grid_id).jqGrid(config);
    },
    save_grid_data: function()
    {
        $.each(org_openpsa_grid_helper.active_grids, function(index, grid_id)
        {
            var identifier = location.hostname + location.href + '#' + grid_id,
            grid = $('#' + grid_id),
            data =
            {
                'page': grid.jqGrid('getGridParam', 'page'),
                'sortname': grid.jqGrid('getGridParam', 'sortname'),
                'sortorder': grid.jqGrid('getGridParam', 'sortorder'),
                'grouping': grid.jqGrid('getGridParam', 'grouping'),
                'groupingView': grid.jqGrid('getGridParam', 'groupingView'),
                'hiddengrid': grid.closest('.ui-jqgrid-view').find('.ui-jqgrid-titlebar-close .ui-icon').hasClass('ui-icon-circle-triangle-s'),
                'custom_keys':
                {
                    'vScroll': grid.closest(".ui-jqgrid-bdiv").scrollTop(),
                    'maximized': grid.closest('.ui-jqgrid-maximized').length > 0
                }
            };
            //window.localStorage.setItem(identifier, JSON.stringify(data))
        });
    }
};

var org_openpsa_export_csv =
{
    configs: {},
    separator: ';',
    add: function (config)
    {
        this.configs[config.id] = config;

        $('#' + config.id + '_export input[type="submit"]').bind('click', function()
        {
            var id = $(this).parent().attr('id').replace(/_export$/, '');
            org_openpsa_export_csv.prepare_data(id);
        });
    },
    prepare_data: function(id)
    {
        var config = this.configs[id],
        rows = $('#' + config.id).jqGrid('getRowData'),
        field, i,
        data = '';
        for (field in config.fields)
        {
            data += this.trim(config.fields[field]) + this.separator;
        }

        data += '\n';

        for (i = 0; i < rows.length; i++)
        {
            for (field in config.fields)
            {
                if (typeof rows[i][field] !== 'undefined')
                {
                    data += this.trim(rows[i][field]) + this.separator;
                }
            }
            data += '\n';
        }
        document.getElementById(config.id + '_csvdata').value += data;
    },
    trim: function(input)
    {
        var output = input.replace(/\n|\r/g, " " ); // remove line breaks
        output = output.replace(/\s+/g, " " ); // Shorten long whitespace
        output = output.replace(/^\s+/g, "" ); // strip leading ws
        output = output.replace(/\s+$/g, "" ); // strip trailing ws
        return output.replace(/<\/?([a-z][a-z0-9]*)\b[^>]*>/gi, ''); //strip HTML tags
    }
};

var org_openpsa_batch_processing =
{
    initialize: function(config)
    {
        var widgets_to_add = [],
        //build action form and associated widgets
        action_select = '<div class="action_select_div" id="' + config.id + '_batch" style="display: none;">';
        action_select += '<input type="hidden" name="batch_grid_id" value="' + config.id + '" />';
        action_select += '<select id="' + config.id + '_batch_select" class="action_select" name="action" size="1">';

        $.each(config.options, function(key, values)
        {
            action_select += '<option value="' + key + '" >' + values.label + '</option>';
            if (typeof values.widget_config !== 'undefined')
            {
                var widget_id = config.id + '__' + key;
                widgets_to_add.push({id: widget_id, insertAfter: '#' + config.id + '_batch_select', widget_config: values.widget_config});
            }
        });
        action_select += '</select><input type="submit" name="send" /></div>';
        $(action_select).appendTo($('#form_' + config.id));

        $.each(widgets_to_add, function(index, widget_conf)
        {
            midcom_helper_datamanager2_autocomplete.create_widget(widget_conf);
        });

        $('#' + config.id + '_batch_select').bind('change', function(event)
        {
            var grid_id = $(event.target).attr('id').replace(/_batch_select$/, ''),
            selected_option = $(event.target).val();
            $('.batch_widget').hide();
            $('#' + config.id + '_batch').css('display', 'inline');
            $('#' + config.id + '__' + selected_option + '_search_input').show();
        });

        //hook action select into grid so that it'll get shown when necessary
        $('#' + config.id).jqGrid('setGridParam',
        {
            onSelectRow: function(id)
            {
                if ($('#' + config.id).jqGrid('getGridParam', 'selarrrow').length === 0)
                {
                    $('#' + config.id + '_batch').hide();
                }
                else
                {
                    $('#' + config.id + '_batch').show();
                }
                $(window).trigger('resize');
            },
            onSelectAll: function(rowids, status)
            {
                if (!status)
                {
                    $('#' + config.id + '_batch').hide();
                }
                else
                {
                    $('#' + config.id + '_batch').show();
                }
                $(window).trigger('resize');
            }
        });

        //make sure grid POSTs our selection
        $("#form_" + config.id).bind('submit', function()
        {
            var i,
            s = $("#" + config.id).jqGrid('getGridParam', 'selarrrow');
            for (i = 0; i < s.length; i++)
            {
                $('<input type="hidden" name="entries[' + s[i] + ']" value="On" />').appendTo('#form_' + config.id);
            }
        });
    }
};
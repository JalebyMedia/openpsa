<?php
$prefix = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX);
if ($data['component'] == 'midgard')
{
    $component_label = 'Midgard CMS';
}
else
{
    $component_label = midcom::get()->i18n->get_string($data['component'], $data['component']);
}
echo "<h2>";
echo sprintf($data['l10n_midcom']->get('%s in %s'),
        midgard_admin_asgard_plugin::get_type_label($data['type']),
        $component_label);
echo "</h2>";

echo "<p>";
if ($data['parent_type'])
{
    echo sprintf($data['l10n']->get('%s is under type %s'), midgard_admin_asgard_plugin::get_type_label($data['type']), "<a href=\"{$prefix}__mfa/asgard/{$data['parent_type']}/\">" . midgard_admin_asgard_plugin::get_type_label($data['parent_type']) . "</a>") . ' ';
}

if ($data['component'] == 'midgard')
{
    echo $data['l10n']->get('this is a midgard core type') . "</p>\n";
}
else
{
    echo sprintf($data['l10n']->get('this type belongs to %s component'), "<a href=\"{$prefix}__mfa/asgard/components/{$data['component']}/\">{$data['component']}</a>") . "</p>\n";
}
?>

&(data['help']:h);

<form method="get">
<div id="search_bar">
    <label>
        <?php echo $data['l10n']->get('search'); ?>
        <input id="search_field" type="text" name="search" class="search"<?php if (isset($_GET['search'])) { echo " value=\"{$_GET['search']}\""; } ?> />
    </label>
    <input class="search" type="submit" value="<?php echo $data['l10n']->get('go'); ?>" />
</div>
</form>
<script type="text/javascript">
document.getElementById('search_field').focus();
</script>

<?php
if (isset($data['search_results']))
{
    function resolve_label(&$object)
    {
        if (empty($object->guid))
        {
            return;
        }
        $reflector = midcom_helper_reflector_tree::get($object);
        $label = $reflector->get_object_label($object);
        try
        {
            $parent = $object->get_parent();
            $label = resolve_label($parent) . "/" . $label;
        }
        catch (midcom_error $e)
        {
            $e->log();
        }
        return $label;
    }
    if (!$data['search_results'])
    {
        echo "<p>" . $data['l10n']->get('no results') . "</p>\n";
    }

    else
    {
        echo "<table class=\"table_widget\" id=\"search_results\">\n";
        echo "    <thead>\n";
        echo "        <tr>\n";
        echo "            <th>" . $data['l10n_midcom']->get('title') . "</th>\n";
        echo "            <th>" . $data['l10n']->get('created on') . "</th>\n";
        echo "            <th>" . $data['l10n']->get('created by') . "</th>\n";
        echo "        </tr>\n";
        echo "    </thead>\n";
        echo "    <tbody>\n";
        $persons = array();
        foreach ($data['search_results'] as $result)
        {
            $reflector = midcom_helper_reflector_tree::get($result);
            $icon = $reflector->get_object_icon($result);
            $label = resolve_label($result);

            if (!isset($persons[$result->metadata->creator]))
            {
                $persons[$result->metadata->creator] = midcom::get()->auth->get_user($result->metadata->creator);
            }

            echo "        <tr>\n";
            echo "            <td><a href=\"{$prefix}__mfa/asgard/object/{$data['default_mode']}/{$result->guid}/\">{$icon} {$label}</a></td>\n";
            echo "            <td>" . strftime('%x %X', $result->metadata->created) . "</td>\n";

            if (!empty($persons[$result->metadata->creator]->guid))
            {
                echo "            <td><a href=\"{$prefix}__mfa/asgard/object/view/{$persons[$result->metadata->creator]->guid}/\">{$persons[$result->metadata->creator]->name}</a></td>\n";
            }
            else
            {
                echo "            <td>&nbsp;</td>\n";
            }

            echo "        </tr>\n";
        }
        echo "    </tbody>\n";
        echo "</table>\n";
        echo "<script type=\"text/javascript\">\n";
        echo "        // <![CDATA[\n";
        echo "            jQuery('#search_results').tablesorter(\n";
        echo "            {\n ";
        echo "                widgets: ['zebra'],";
        echo "                sortList: [[0,0]]\n";
        echo "            });\n";
        echo "        // ]]>\n";
        echo "    </script>\n";
    }
}
?>

<?php
$prefix = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX);

midcom_show_style('group-sidebar');
?>

<div class="main">
<?php
//quick way to remove members field
array_pop($data['view']->schema->field_order);
$data['view']->display_view();
?>
</div>
<?php
    midcom::get()->dynamic_load($prefix . 'members/' . $data['group']->guid . '/');
?>

<div class="object_edit">
<?php
$data['controller']->display_form();
?>
</div>

<div id="output-wrapper">
<h3><?php echo $data['l10n']->get('script output'); ?></h3>
<iframe name="shell-runner" id="shell-runner" frameborder="0" src="./?ajax"></iframe>
</div>
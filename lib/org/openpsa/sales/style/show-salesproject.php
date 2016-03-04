<?php
$view = $data['view_salesproject'];
$salesproject = $data['salesproject'];
?>
<div class="sidebar contacts">
    <?php
    if ($customer = $data['salesproject']->get_customer())
    {
        echo "<h2>{$customer->get_label()}</h2>\n";
    }
    foreach (array_keys($data['salesproject']->contacts) as $contact_id)
    {
        $person_card = org_openpsa_widgets_contact::get($contact_id);
        $person_card->show();
    } ?>
</div>

<div class="main salesproject">
<h1>&(view['title']:h);</h1>
<div class="midcom_helper_datamanager2_view">
    <div class="field">
     <div class="title"><?php echo $data['l10n']->get('code'); ?></div>
     <div class="value">&(view['code']:h);</div>
    </div>
    <div class="field">
     <div class="title"><?php echo $data['l10n']->get('state'); ?></div>
     <div class="value">&(view['state']:h);</div>
    </div>
    <div class="field">
     <div class="title"><?php echo $data['l10n_midcom']->get('description'); ?></div>
     <div class="value">&(view['description']:h);</div>
    </div>
    <?php if ($salesproject->state == org_openpsa_sales_salesproject_dba::STATE_ACTIVE)
    { ?>
        <div class="field">
         <div class="title"><?php echo $data['l10n']->get('estimated closing date'); ?></div>
         <div class="value">&(view['close_est']:h);</div>
        </div>
        <div class="field">
         <div class="title"><?php echo $data['l10n']->get('probability'); ?></div>
         <div class="value">&(view['probability']:h);</div>
        </div>
    <?php } ?>
    <div class="field">
     <div class="title"><?php echo $data['l10n']->get('value'); ?></div>
     <div class="value"><?php echo org_openpsa_helpers::format_number($salesproject->value); ?></div>
    </div>
    <div class="field">
     <div class="title"><?php echo $data['l10n']->get('profit'); ?></div>
     <div class="value"><?php echo org_openpsa_helpers::format_number($salesproject->profit); ?></div>
    </div>
    <?php
     $owner_card = org_openpsa_widgets_contact::get($salesproject->owner);
    ?>
    <div class="field">
     <div class="title"><?php echo $data['l10n']->get('owner'); ?></div>
     <div class="value"><?php echo $owner_card->show_inline(); ?></div>
    </div>
</div>

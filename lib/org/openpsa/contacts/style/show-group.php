<?php
$nap = new midcom_helper_nav();
$node = $nap->get_node($nap->get_current_node());

$addresses = array();
?>
<div class="sidebar">
    <?php
    if ($data['parent_group'])
    {
        ?>
        <div class="area parent">
          <h2><?php echo sprintf($data['l10n']->get('%s of'), $data['l10n']->get($data['view']['organization_type'])); ?></h2>
            <dl>
                <dt><?php echo "<a href=\"{$node[MIDCOM_NAV_FULLURL]}group/{$data['parent_group']->guid}/\">{$data['parent_group']->official}</a>"; ?></dt>
            </dl>
        </div>
        <?php
    }
    ?>
    <?php midcom::get()->dynamic_load($node[MIDCOM_NAV_RELATIVEURL] . "group/" . $data['group']->guid . "/members/"); ?>
    <?php midcom::get()->dynamic_load($node[MIDCOM_NAV_RELATIVEURL] . "group/" . $data['group']->guid . "/subgroups/"); ?>

    <!-- TODO: Add salesprojects here -->
    <!-- TODO: Projects list, Add project button -->
</div>

<div class="main">
    <?php
    // Display the group information
    foreach ($data['view'] as $fieldname => $fielddata)
    {
        if (!$fielddata)
        {
            continue;
        }
        switch($fieldname)
        {
            case 'members':
            case 'organization_type':
                break;
            case 'homepage':
                echo "<h2>" . $data['l10n']->get('contact information') . "</h2>\n";
                echo "<div><strong>" . $data['l10n']->get($fieldname) . ": </strong>";
                echo '<a href="' .$fielddata . '">' . $fielddata . "</a></div>";
                break;
            case 'email':
                echo "<div><strong>" . $data['l10n']->get($fieldname) . ": </strong>";
                echo '<a href="mailto:' . $fielddata . '">' . $fielddata . "</a></div>";
                break;
            case 'notes':
                echo "<h2>" . $data['l10n']->get('notes') . "</h2>\n";
                echo "<pre>" . $fielddata . "</pre>";
                break;
            case 'categories':
                echo "<div><strong>" . $data['l10n']->get($fieldname) . ": </strong>";
                echo $data['l10n']->get($fielddata) . "</div>";
                break;

            case 'official':
            case 'company_id':
            case 'phone':
            case 'fax':
                echo "<div><strong>" . $data['l10n']->get($fieldname) . ": </strong>";
                echo $fielddata . "</div>";
                break;
        }
    }
    if (array_key_exists('billing_data', $data))
    {
        echo "<h2>" . $data['l10n']->get('invoice defaults') . "</h2>\n";
        echo "<div><strong>" . midcom::get('i18n')->get_string('vat', 'org.openpsa.invoices') . ": </strong>";
        echo $data['billing_data']->vat . " %</div>\n";
        echo "<div><strong>" . midcom::get('i18n')->get_string('payment target', 'org.openpsa.invoices') . ": </strong>";
        echo $data['billing_data']->due . "</div>\n";
        $data['billing_data']->render_address();
    }
    org_openpsa_widgets_contact::show_address_card($data['group'], array('visiting', 'postal'));

    echo '<br style="clear:left" />';

    $siteconfig = org_openpsa_core_siteconfig::get_instance();

    $tabs = array();
    if (strpos($data['view']['categories'], $data['l10n']->get('client')) !== false)
    {
        //TODO: Check for privileges somehow
        $invoices_url = $siteconfig->get_node_relative_url('org.openpsa.invoices');
        if ($invoices_url)
        {
            $tabs[] = array
            (
                'url' => $invoices_url . "list/customer/all/{$data['group']->guid}/",
                'title' => midcom::get('i18n')->get_string('invoices', 'org.openpsa.invoices'),
            );
        }
        $sales_url = $siteconfig->get_node_relative_url('org.openpsa.sales');
        if ($sales_url)
        {
            $tabs[] = array
            (
                'url' => $sales_url . "list/customer/{$data['group']->guid}/",
                'title' => midcom::get('i18n')->get_string('salesprojects', 'org.openpsa.sales'),
            );
        }
    }
    org_openpsa_widgets_ui::render_tabs($data['group']->guid, $tabs);
    ?>
</div>

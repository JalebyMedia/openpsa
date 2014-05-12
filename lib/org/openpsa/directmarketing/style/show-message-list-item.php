<li><?php
$view = $data['message_array'];
$prefix = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX);

echo "<div class=\"{$data['message_class']}\"><a href=\"{$prefix}message/{$data['message']->guid}/\">{$view['title']}</a>\n";
echo "<br />" . sprintf($data['l10n']->get('created on %s'), strftime('%x %X', $data['message']->metadata->created)) . "\n";

if ($data['message']->sendStarted)
{
    echo ", " . sprintf($data['l10n']->get('sent on %s'), strftime('%x %X', $data['message']->sendStarted)) . "\n";
}

echo "</div>\n";
?></li>
<?php
// Available request data: comments, objectguid, comment, display_datamanager
$view = $data['display_datamanager']->get_content_html();
$comment = $data['comment'];

$rating = '';
if ($comment->rating > 0)
{
    $rating = ', ' . sprintf('rated %s', $comment->rating);
}
$object_link = midcom::get()->permalinks->create_permalink($comment->objectguid) . '#net_nehmer_comments_' . $comment->guid;

$created = $comment->metadata->published;

$published = sprintf(
    $data['l10n']->get('published by %s on %s.'),
    $view['author'],
    strftime('%x %X', $created));

if (   midcom::get()->auth->admin
   || (   midcom::get()->auth->user
       && $comment->can_do('midgard:delete')))
{
    $creator = $comment->metadata->creator;
    $created = $comment->metadata->created;

    $user = midcom::get()->auth->get_user($creator);
    if ($user)
    {
        $username = "{$user->name} ({$user->username})";
    }
    else
    {
        $username = $data['l10n_midcom']->get('anonymous');
    }
    $ip = $comment->ip ?: '?.?.?.?';
    $metadata = sprintf($data['l10n']->get('creator: %s, created %s, source ip %s.'),
                        $username, strftime('%x %X', $created), $ip);
}
?>

<div style="clear:right;" class="net_nehmer_comments_comment">
    <div style="float:right;">
        <a href="&(object_link);"><?php echo $data['l10n']->get('go to parent object'); ?></a>
    </div>
    <h3 class="headline">&(view['title']);&(rating);</h3>

    <div class="published">
        &(published);
    </div>

    <div class="net_nehmer_comments_comment_toolbar">
        <?php echo $data['comment_toolbar']->render(); ?>
    </div>

<?php if (!empty($metadata))
{ ?>
    <div class="metadata">
        &(metadata);
    </div>

<?php } ?>

    <div class="content">&(view['content']:h);</div>
</div>
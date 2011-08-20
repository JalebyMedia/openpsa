<?php
$_MIDCOM->auth->require_admin_user();

// Ensure this is not buffered
$_MIDCOM->cache->content->enable_live_mode();
while(@ob_end_flush())

midcom::get()->disable_limits();

echo "<h1>Invalidating task caches:</h1>\n";

$qb = org_openpsa_projects_task_dba::new_query_builder();
$qb->add_constraint('orgOpenpsaObtype', '=', ORG_OPENPSA_OBTYPE_TASK);
$tasks = $qb->execute();

foreach ($tasks as $task)
{
    $start = microtime(true);
    echo "Invalidating cache for task #{$task->id} {$task->title}... \n";
    flush();
    if ($task->update_cache())
    {
        $time_consumed = round(microtime(true) - $start, 2);
        echo "OK ({$time_consumed} secs, task has {$task->reportedHours}h reported)";
    }
    else
    {
        echo "ERROR: " . midcom_connection::get_error_string();
    }
    echo "<br />\n";
}
?>
<p>All done</p>

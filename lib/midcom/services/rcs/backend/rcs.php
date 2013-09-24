<?php
/**
 * @author tarjei huse
 * @package midcom.services.rcs
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * @package midcom.services.rcs
 */
class midcom_services_rcs_backend_rcs implements midcom_services_rcs_backend
{
    /**
     * GUID of the current object
     */
    private $_guid;

    /**
     * Cached revision history for the object
     */
    private $_history;

    private $_config;

    public function __construct(&$object, &$config)
    {
        $this->_config = $config;
        $this->_guid = $object->guid;
    }

    private function _generate_rcs_filename($guid)
    {
        // Keep files organized to subfolders to keep filesystem sane
        $dirpath = $this->_config->get_rcs_root() . "/{$guid[0]}/{$guid[1]}";
        if (!file_exists($dirpath))
        {
            debug_add("Directory {$dirpath} does not exist, attempting to create", MIDCOM_LOG_WARN);
            mkdir($dirpath, 0777, true);
        }
        $filename = "{$dirpath}/{$guid}";

        return $filename;
    }

    /**
     * Save a new revision
     *
     * @param object object to be saved
     * @return boolean true on success.
     */
    public function update(&$object, $updatemessage = null)
    {
        // Store user identifier and IP address to the update string
        if (midcom::get('auth')->user)
        {
            $update_string = midcom::get('auth')->user->id . "|{$_SERVER['REMOTE_ADDR']}";
        }
        else
        {
            $update_string = "NOBODY|{$_SERVER['REMOTE_ADDR']}";
        }

        // Generate update message if needed
        if (!$updatemessage)
        {
            if (midcom::get('auth')->user !== null)
            {
                $updatemessage = sprintf("Updated on %s by %s", strftime("%x %X"), midcom::get('auth')->user->name);
            }
            else
            {
                $updatemessage = sprintf("Updated on %s.", strftime("%x %X"));
            }
        }
        $update_string .= "|{$updatemessage}";

        $result = $this->rcs_update($object, $update_string);

        // The methods return basically what the RCS unix level command returns, so nonzero value is error and zero is ok...
        return ($result == 0);
    }

    /**
     * This function takes an object and updates it to RCS, it should be
     * called just before $object->update(), if the type parameter is omitted
     * the function will use GUID to determine the type, this makes an
     * extra DB query.
     *
     * @param string root of rcs directory.
     * @param object object to be updated.
     * @return int :
     *      0 on success
     *      3 on missing object->guid
     *      nonzero on error in one of the commands.
     */
    public function rcs_update ($object, $message)
    {
        $status = null;

        if (empty($object->guid))
        {
            debug_add("Missing GUID, returning error");
            return 3;
        }

        $filename = $this->_generate_rcs_filename($object->guid);
        $rcsfilename =  "{$filename},v";

        if (!file_exists($rcsfilename))
        {
            $message = str_replace('|Updated ', '|Created ', $message);
            // The methods return basically what the RCS unix level command returns, so nonzero value is error and zero is ok...
            return $this->rcs_create($object, $message);
        }

        $command = 'co -q -f -l ' . escapeshellarg($filename);
        $status = $this->exec($command);

        $data = $this->rcs_object2data($object);

        $this->rcs_writefile($object->guid, $data);
        $command = 'ci -q -m' . escapeshellarg($message) . " {$filename}";
        $status = $this->exec($command);

        chmod ($rcsfilename, 0770);

        return $status;
    }

   /**
    * Get the object of a revision
    *
    * @param string revision identifier of revision wanted
    * @return array array representation of the object
    */
    public function get_revision($revision)
    {
        if (empty($this->_guid))
        {
            return array();
        }
        $filepath = $this->_generate_rcs_filename($this->_guid);

        // , must become . to work. Therefore this:
        str_replace(',', '.', $revision );
        // this seems to cause problems:
        //settype ($revision, "float");

        $command = 'co -q -f -r' . escapeshellarg(trim($revision)) .  " {$filepath} 2>/dev/null";
        $this->exec($command);

        $data = $this->rcs_readfile($this->_guid);

        $mapper = new midcom_helper_xml();
        $revision = $mapper->data2array($data);

        $command = "rm -f {$filepath}";
        $this->exec($command);

        return $revision;
    }

    /**
     * Check if a revision exists
     *
     * @param string  version
     * @return booleann true if exists
     */
    public function version_exists($version)
    {
        $history = $this->list_history();
        return array_key_exists($version, $history);
    }

    /**
     * Get the previous versionID
     *
     * @param string version
     * @return string versionid before this one or empty string.
     */
    public function get_prev_version($version)
    {
        $versions = $this->list_history_numeric();

        if (   !in_array($version, $versions)
            || $version === end($versions))
        {
            return '';
        }

        $mode = end($versions);

        while( $mode
            && $mode !== $version)
        {
            $mode = prev($versions);

            if ($mode === $version)
            {
                return next($versions);
            }
        }

        return '';
    }

    /**
     * Mirror method for get_prev_version()
     *
     * @param string $version
     * @return mixed
     */
    public function get_previous_version($version)
    {
        return $this->get_prev_version($version);
    }

    /**
     * Get the next versionID
     *
     * @param string version
     * @return string versionid before this one or empty string.
     */
    public function get_next_version($version)
    {
        $versions = $this->list_history_numeric();

        if (   !in_array($version, $versions)
            || $version === current($versions))
        {
            return '';
        }

        $mode = current($versions);

        while( $mode
            && $mode !== $version)
        {
            $mode = next($versions);

            if ($mode === $version)
            {
                return prev($versions);
            }
        }

        return '';
    }

    /**
     * This function returns a list of the revisions as a
     * key => value par where the key is the index of the revision
     * and the value is the revision id.
     * Order: revision 0 is the newest.
     *
     * @return array
     */
    public function list_history_numeric()
    {
        $revs = $this->list_history();
        return array_keys($revs);
    }

    /**
     * Lists the number of changes that has been done to the object
     *
     * @return array list of changeids
     */
    public function list_history()
    {
        if (empty($this->_guid))
        {
            return array();
        }

        if (is_null($this->_history))
        {
            $filepath = $this->_generate_rcs_filename($this->_guid);
            $this->_history = $this->rcs_gethistory($filepath);
        }

        return $this->_history;
    }

    /* it is debatable to move this into the object when it resides nicely in a libary... */

    private function rcs_parse_history_entry($entry)
    {
        // Create the empty history array
        $history = array
        (
            'revision' => null,
            'date'     => null,
            'lines'    => null,
            'user'     => null,
            'ip'       => null,
            'message'  => null,
        );

        // Revision number is in format
        // revision 1.11
        $history['revision'] = substr($entry[0], 9);

        // Entry metadata is in format
        // date: 2006/01/10 09:40:49;  author: www-data;  state: Exp;  lines: +2 -2
        // NOTE: Time here appears to be stored as UTC according to http://parand.com/docs/rcs.html
        $metadata_array = explode(';', $entry[1]);
        foreach ($metadata_array as $metadata)
        {
            $metadata = trim($metadata);
            if (substr($metadata, 0, 5) == 'date:')
            {
                $history['date'] = strtotime(substr($metadata, 6));
            }
            elseif (substr($metadata, 0, 6) == 'lines:')
            {
                $history['lines'] = substr($metadata, 7);
            }
        }

        // Entry message is in format
        // user:27b841929d1e04118d53dd0a45e4b93a|84.34.133.194|Updated on Tue 10.Jan 2006 by admin kw
        $message_array = explode('|', $entry[2]);
        if (count($message_array) == 1)
        {
            $history['message'] = $message_array[0];
        }
        else
        {
            if ($message_array[0] != 'Object')
            {
                $history['user'] = $message_array[0];
            }
            $history['ip']   = $message_array[1];
            $history['message'] = $message_array[2];
        }
        return $history;
    }

    /*
     * the functions below are mostly rcs functions moved into the class. Someday I'll get rid of the
     * old files...
     */
    /**
     * Get a list of the object's history
     *
     * @param string objectid (usually the guid)
     * @return array list of revisions and revision comment.
     */
    private function rcs_gethistory($what)
    {
        $history = $this->rcs_exec('rlog', $what . ',v');
        $revisions = array();
        $lines = explode("\n", $history);

        for ($i = 0; $i < count($lines); $i++)
        {
            if (substr($lines[$i], 0, 9) == "revision ")
            {
                $history_entry[0] = $lines[$i];
                $history_entry[1] = $lines[$i+1];
                $history_entry[2] = $lines[$i+2];
                $history = $this->rcs_parse_history_entry($history_entry);

                $revisions[$history['revision']] = $history;

                $i += 3;

                while (   $i < count($lines)
                       && substr($lines[$i], 0, 4) != '----'
                       && substr($lines[$i], 0, 5) != '=====')
                {
                     $i++;
                }
            }
        }
        return $revisions;
    }

    /**
     * execute a command
     *
     * @param string $command The command to execute
     * @param string $filename The file to operate on
     * @return string command result.
     */
    private function rcs_exec($command, $filename)
    {
        if (!is_readable($filename))
        {
            debug_add('file ' . $filename . ' is not readable, returning empty result', MIDCOM_LOG_INFO);
            return '';
        }
        $fh = popen($command . ' "' . $filename . '" 2>&1', "r");
        $ret = "";
        while ($reta = fgets($fh, 1024))
        {
            $ret .= $reta;
        }
        pclose($fh);

        return $ret;
    }

    /**
     * Writes $data to file $guid, does not return anything.
     */
    private function rcs_writefile ($guid, $data)
    {
        if (!is_writable($this->_config->get_rcs_root()))
        {
            return false;
        }
        if (empty($guid))
        {
            return false;
        }
        $filename = $this->_generate_rcs_filename($guid);
        file_put_contents($filename, $data);
    }

    /**
     * Reads data from file $guid and returns it.
     *
     * @param string guid
     * @return string xml representation of guid
     */
    private function rcs_readfile ($guid)
    {
        if (empty($guid))
        {
            return '';
        }
        $filename = $this->_generate_rcs_filename($guid);

        if (!file_exists($filename))
        {
            return '';
        }
        return file_get_contents($filename);
    }

    /**
     * Make xml out of an object.
     *
     * @param midcom_core_dbaobject $object
     * @return xmldata
     */
    private function rcs_object2data(midcom_core_dbaobject $object)
    {
        $mapper = new midcom_helper_xml();
        $result = $mapper->object2data($object);
        if ($result)
        {
            return $result;
        }
        debug_add("Objectmapper returned false.");
        return false;
    }

    /**
     * This function takes an object and adds it to RCS, it should be
     * called just after $object->create(). Remember that you first need
     * to mgd_get the object since $object->create() returns only the id,
     * one way of doing this is:
     * @param object $object object to be saved
     * @param string $description changelog comment.-
     * @return int :
     *      0 on success
     *      3 on missing object->guid
     *      nonzero on error in one of the commands.
     */
    private function rcs_create(midcom_core_dbaobject $object, $description)
    {
        $status = null;

        $data = $this->rcs_object2data($object);

        if (empty($object->guid))
        {
            return 3;
        }
        $this->rcs_writefile($object->guid, $data);
        $filepath = $this->_generate_rcs_filename($object->guid);

        $command = 'ci -q -i -t-' . escapeshellarg($description) . ' -m' . escapeshellarg($description) . " {$filepath}";

        $status = $this->exec($command);

        $filename = $filepath . ",v";

        if (file_exists($filename))
        {
            chmod ($filename, 0770);
        }
        return $status;
    }

    private function exec($command)
    {
        $status = null;
        $output = null;

        // Always append stderr redirect
        $command .= ' 2>&1';

        debug_add("Executing '{$command}'");

        try
        {
            @exec($command, $output, $status);
        }
        catch (Exception $e)
        {
            debug_add($e->getMessage());
        }

        if ($status === 0)
        {
            // Unix exit code 0 means all ok...
            return $status;
        }

        debug_add("Command '{$command}' returned with status {$status}, see debug log for output", MIDCOM_LOG_WARN);
        debug_print_r('Got output: ', $output);
        // any other exit codes means some sort of error
        return $status;
    }

    /**
     * Get a html diff between two versions.
     *
     * @param string latest_revision id of the latest revision
     * @param string oldest_revision id of the oldest revision
     * @return array array with the original value, the new value and a diff -u
     */
    public function get_diff($oldest_revision, $latest_revision, $renderer_style = 'inline')
    {
        $oldest = $this->get_revision($oldest_revision);
        $newest = $this->get_revision($latest_revision);

        $return = array();
        $oldest = array_intersect_key($oldest, $newest);
        foreach ($oldest as $attribute => $oldest_value)
        {
            if (is_array($oldest_value))
            {
                continue;
                // Skip
            }

            $return[$attribute] = array
            (
                'old' => $oldest_value,
                'new' => $newest[$attribute]
            );

            if ($oldest_value != $newest[$attribute])
            {
                $lines1 = explode ("\n", $oldest_value);
                $lines2 = explode ("\n", $newest[$attribute]);

                // Ignore deprecation warnings caused by Text_Diff
                $old_value = error_reporting(E_ALL & ~E_STRICT & ~E_DEPRECATED);
                $diff = new Text_Diff($lines1, $lines2);

                if ($renderer_style == 'unified')
                {
                    $renderer = new Text_Diff_Renderer_unified();
                }
                else
                {
                    $renderer = new Text_Diff_Renderer_inline();
                }

                if (!$diff->isEmpty())
                {
                    // Run the diff
                    $return[$attribute]['diff'] = $renderer->render($diff);

                    if ($renderer_style == 'inline')
                    {
                        // Modify the output for nicer rendering
                        $return[$attribute]['diff'] = str_replace('<del>', "<span class=\"deleted\" title=\"removed in {$latest_revision}\">", $return[$attribute]['diff']);
                        $return[$attribute]['diff'] = str_replace('</del>', '</span>', $return[$attribute]['diff']);
                        $return[$attribute]['diff'] = str_replace('<ins>', "<span class=\"inserted\" title=\"added in {$latest_revision}\">", $return[$attribute]['diff']);
                        $return[$attribute]['diff'] = str_replace('</ins>', '</span>', $return[$attribute]['diff']);
                    }
                }
                error_reporting($old_value);
            }
        }

        return $return;
    }

    /**
     * Get the comment of one revision.
     *
     * @param string revison id
     * @return string comment
     */
    public function get_comment($revision)
    {
        $this->list_history();
        return $this->_history[$revision];
    }

    /**
     * Restore an object to a certain revision.
     *
     * @param string id of revision to restore object to.
     * @return boolean true on success.
     */
    public function restore_to_revision($revision)
    {
        $new = $this->get_revision($revision);

        try
        {
            $object = midcom::get('dbfactory')->get_object_by_guid($this->_guid);
        }
        catch (midcom_error $e)
        {
            debug_add("{$this->_guid} could not be resolved to object", MIDCOM_LOG_ERROR);
            return false;
        }
        $mapper = new midcom_helper_xml();
        $object = $mapper->data2object($new, $object);

        $object->set_rcs_message("Reverted to revision {$revision}");

        return $object->update();
    }
}
?>
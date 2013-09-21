<?php
/**
 * @package midcom.db
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM level replacement for the Midgard Attachment record with framework support.
 *
 * @package midcom.db
 */
class midcom_db_attachment extends midcom_core_dbaobject
{
    public $__midcom_class_name__ = __CLASS__;
    public $__mgdschema_class_name__ = 'midgard_attachment';

    /**
     * Internal tracking state variable, holds the file handle of any open
     * attachment.
     *
     * @var resource
     */
    private $_open_handle = null;

    /**
     * Internal tracking state variable, true if the attachment has a handle opened in write mode
     */
    var $_open_write_mode = false;

    public function __construct($id = null)
    {
        $this->_use_rcs = false;
        $this->_use_activitystream = false;
        parent::__construct($id);
    }

    function get_parent_guid_uncached()
    {
        return $this->parentguid;
    }

    /**
     * Returns the Parent of the Attachment, which is identified by the table/id combination
     * in the attachment record. The table in question is used to identify the object to
     * use. If multiple objects are registered for a given table, the first matching class
     * returned by the dbfactory is used (which is usually rather arbitrary).
     *
     * @return MidgardObject Parent object.
     */
    public static function get_parent_guid_uncached_static($guid, $classname = __CLASS__)
    {
        $mc = new midgard_collector('midgard_attachment', 'guid', $guid);
        $mc->set_key_property('parentguid');
        $mc->execute();
        $link_values = $mc->list_keys();
        if (empty($link_values))
        {
            return null;
        }
        return key($link_values);
    }

    /**
     * Opens the attachment for file IO, the semantics match the original
     * mgd_open_attachment call. Returns a filehandle that can be used with the
     * usual PHP file functions if successful, the handle has to be closed with
     * the close() method when you no longer need it, don't let it fall over
     * the end of the script.
     *
     * <b>Important Note:</b> It is important to use the close() member function of
     * this class to close the file handle, not just fclose(). Otherwise, the upgrade
     * notification switches will fail.
     *
     * @param string $mode The mode which should be used to open the attachment, same as
     *     the mode parameter of the PHP fopen call. This defaults to write access (see
     *     mgd_open_attachmentl for details).
     * @return resource A file handle to the attachment if successful, false on failure.
     */
    function open($mode = 'default')
    {
        if (! $this->id)
        {
            debug_add('Cannot open a non-persistent attachment.', MIDCOM_LOG_WARN);
            debug_print_r('Object state:', $this);
            return false;
        }

        if ($this->_open_handle !== null)
        {
            debug_add("Warning, the Attachment {$this->id} already had an open file handle, we close it implicitly.", MIDCOM_LOG_WARN);
            @fclose($this->_open_handle);
            $this->_open_handle = null;
        }
        $blob = new midgard_blob($this->__object);
        if ($mode = 'default')
        {
            $this->_open_write_mode = true;
            $handle = $blob->get_handler();
        }
        else
        {
            /* WARNING, read mode not supported by midgard_blob! */
            $this->_open_write_mode = ($mode{0} != 'r');
            $handle = @fopen($blob->get_path(), $mode);
        }

        if (!$handle)
        {
            debug_add("Failed to open attachment with mode {$mode}, last Midgard error was: " . midcom_connection::get_error_string(), MIDCOM_LOG_WARN);
        }

        $this->_open_handle = $handle;

        return $handle;
    }

    /**
     * This function reads the file and returns its contents
     *
     * @return string
     */
    public function read()
    {
        $attachment = new midgard_attachment($this->guid);
        $blob = new midgard_blob($attachment);

        $contents = $blob->read_content();

        return $contents;
    }

    /**
     * This function closes the open write handle obtained by the open() call again.
     * It is required to call this function instead of a simple fclose to ensure proper
     * upgrade notifications.
     */
    function close()
    {
        if ($this->_open_handle === null)
        {
            debug_add("Tried to close non-open attachment {$this->id}", MIDCOM_LOG_WARN);
            return;
        }

        fclose ($this->_open_handle);
        $this->_open_handle = null;

        if ($this->_open_write_mode)
        {
            // We need to update the attachment now, this cannot be done in the Midgard Core
            // at this time.
            if (! $this->update())
            {
                debug_add("Failed to update attachment {$this->id}", MIDCOM_LOG_WARN);
                return;
            }

            $this->file_to_cache();
        }
    }

    /**
     * Rewrite a filename to URL safe form
     *
     * @param string $filename file name to rewrite
     * @param boolean $force_single_extension force file to single extension (defaults to true)
     * @return string rewritten filename
     * @todo add possibility to use the file utility to determine extension if missing.
     */
    public static function safe_filename($filename, $force_single_extension = true)
    {
        $filename = basename(trim($filename));
        if ($force_single_extension)
        {
            $regex = '/^(.*)(\..*?)$/';
        }
        else
        {
            $regex = '/^(.*?)(\..*)$/';
        }
        if (preg_match($regex, $filename, $ext_matches))
        {
            $name = $ext_matches[1];
            $ext = $ext_matches[2];
        }
        else
        {
            $name = $filename;
            $ext = '';
        }
        return midcom::get('serviceloader')->load('midcom_core_service_urlgenerator')->from_string($name) . $ext;
    }

    /**
     * Get the path to the document in the static cache
     *
     * @return string
     */
    public function get_cache_path()
    {
        if (!midcom::get('config')->get('attachment_cache_enabled'))
        {
            return null;
        }

        // Copy the file to the static directory
        $cacheroot = midcom::get('config')->get('attachment_cache_root');
        $subdir = substr($this->guid, 0, 1);
        if (!file_exists("{$cacheroot}/{$subdir}"))
        {
            mkdir("{$cacheroot}/{$subdir}", 0777, true);
        }

        $filename = "{$cacheroot}/{$subdir}/{$this->guid}_{$this->name}";

        return $filename;
    }

    public static function get_url($attachment, $name = null)
    {
        if (is_string($attachment))
        {
            $guid = $attachment;
            if (null === $name)
            {
                $mc = self::new_collector('guid', $guid);
                $names = $mc->get_values('name');
                $name = array_pop($names);
            }
        }
        else if (is_a($attachment, 'midcom_db_attachment'))
        {
            $guid = $attachment->guid;
            $name = $attachment->name;
        }
        else
        {
            throw new midcom_error('Invalid attachment identifier');
        }

        if (midcom::get('config')->get('attachment_cache_enabled'))
        {
            $subdir = substr($guid, 0, 1);

            if (file_exists(midcom::get('config')->get('attachment_cache_root') . '/' . $subdir . '/' . $guid . '_' . $name))
            {
                return midcom::get('config')->get('attachment_cache_url') . '/' . $subdir . '/' . $guid . '_' . urlencode($name);
            }
        }

        if (    midcom::get('config')->get('midcom_compat_ragnaroek')
             && is_object($attachment))
        {
            $nap = new midcom_helper_nav();
            $parent = $nap->resolve_guid($attachment->parentguid);
            if (   is_array($parent)
                && $parent[MIDCOM_NAV_TYPE] == 'node')
            {
                //Serve from topic
                return $parent[MIDCOM_NAV_ABSOLUTEURL] . urlencode($name);
            }
        }

        // Use regular MidCOM attachment server
        return midcom_connection::get_url('self') . 'midcom-serveattachmentguid-' . $guid . '/' . urlencode($name);
    }

    function file_to_cache()
    {
        // Check if the attachment can be read anonymously
        if (!midcom::get('config')->get('attachment_cache_enabled'))
        {
            return;
        }

        if (!$this->can_do('midgard:read', 'EVERYONE'))
        {
            debug_add("Attachment {$this->name} ({$this->guid}) is not publicly readable, not caching.");
            return;
        }

        $filename = $this->get_cache_path();

        if (!$filename)
        {
            debug_add("Failed to generate cache path for attachment {$this->name} ({$this->guid}), not caching.");
            return;
        }

        if (   file_exists($filename)
            && is_link($filename))
        {
            debug_add("Attachment {$this->name} ({$this->guid}) is already in cache as {$filename}, skipping.");
            return;
        }

        // Then symlink the file
        $blob = new midgard_blob($this->__object);

        if (@symlink($blob->get_path(), $filename))
        {
            debug_add("Symlinked attachment {$this->name} ({$this->guid}) as {$filename}.");
            return;
        }

        // Symlink failed, actually copy the data
        $fh = $this->open('r');
        if (!$fh)
        {
            debug_add("Failed to cache attachment {$this->name} ({$this->guid}), opening failed.");
            return;
        }

        $data = '';
        while (!feof($fh))
        {
            $data .= fgets($fh);
        }
        fclose($fh);
        $this->_open_handle = null;

        file_put_contents($filename, $data);

        debug_add("Symlinking attachment {$this->name} ({$this->guid}) as {$filename} failed, data copied instead.");
    }

    /**
     * Simple wrapper for stat() on the blob object.
     *
     * @return mixed Either a stat array as for stat() or false on failure.
     */
    function stat()
    {
        if (!$this->id)
        {
            debug_add('Cannot open a non-persistent attachment.', MIDCOM_LOG_WARN);
            debug_print_r('Object state:', $this);
            return false;
        }

        $blob = new midgard_blob($this->__object);

        $path = $blob->get_path();
        if (!file_exists($path))
        {
            debug_add("File {$path} that blob {$this->guid} points to cannot be found", MIDCOM_LOG_WARN);
            return false;
        }

        return stat($path);
    }

    /**
     * Internal helper, computes an MD5 string which is used as an attachment location.
     * It should be random enough, even if the algorithm used does not match the one
     * Midgard uses. If the location already exists, it will iterate until an unused
     * location is found.
     *
     * @return string An unused attachment location.
     */
    private function _create_attachment_location()
    {
        $location_in_use = true;
        $location = '';

        while ($location_in_use)
        {
            $base = get_class($this);
            $base .= microtime();
            $base .= $_SERVER['SERVER_NAME'];
            $base .= $_SERVER['REMOTE_ADDR'];
            $base .= $_SERVER['REMOTE_PORT'];
            $name = strtolower(md5($base));
            $location = strtoupper(substr($name, 0, 1) . '/' . substr($name, 1, 1) . '/') . $name;

            // Check uniqueness
            $qb = midcom_db_attachment::new_query_builder();
            $qb->add_constraint('location', '=', $location);
            $result = $qb->count_unchecked();

            if ($result == 0)
            {
                $location_in_use = false;
            }
            else
            {
                debug_add("Location {$location} is in use, retrying");
            }
        }

        debug_add("Created this location: {$location}");
        return $location;
    }

    /**
     * Simple creation event handler which fills out the location field if it
     * is still empty with a location generated by _create_attachment_location().
     *
     * @return boolean True if creation may commence.
     */
    public function _on_creating()
    {
        if (empty($this->mimetype))
        {
            $this->mimetype = 'application/octet-stream';
        }

        $this->location = $this->_create_attachment_location();

        return true;
    }

    function update_cache()
    {
        // Check if the attachment can be read anonymously
        if (   midcom::get('config')->get('attachment_cache_enabled')
            && !$this->can_do('midgard:read', 'EVERYONE'))
        {
            // Not public file, ensure it is removed
            $subdir = substr($this->guid, 0, 1);
            $filename = midcom::get('config')->get('attachment_cache_root') . "/{$subdir}/{$this->guid}_{$this->name}";
            if (file_exists($filename))
            {
                @unlink($filename);
            }
        }
    }

    /**
     * Updated callback, triggers watches on the parent(!) object.
     */
    public function _on_updated()
    {
        $this->update_cache();
    }

    /**
     * Deleted callback, triggers watches on the parent(!) object.
     */
    public function _on_deleted()
    {
        if (midcom::get('config')->get('attachment_cache_enabled'))
        {
            // Remove attachment cache
            $filename = $this->get_cache_path();
            if (file_exists($filename))
            {
                @unlink($filename);
            }
        }
    }

    /**
     * Updates the contents of the attachments with the contents given.
     *
     * @param mixed $source File contents.
     * @return boolean Indicating success.
     */
    function copy_from_memory($source)
    {
        $dest = $this->open();
        if (! $dest)
        {
            debug_add('Could not open attachment for writing, last Midgard error was: ' . midcom_connection::get_error_string(), MIDCOM_LOG_WARN);
            return false;
        }

        fwrite($dest, $source);

        $this->close();
        return true;
    }

    /**
     * Updates the contents of the attachments with the contents of the resource identified
     * by the filehandle passed.
     *
     * @param resource $source The handle to read from.
     * @return boolean Indicating success.
     */
    function copy_from_handle($source)
    {
        $dest = $this->open();
        if (! $dest)
        {
            debug_add('Could not open attachment for writing, last Midgard error was: ' . midcom_connection::get_error_string(), MIDCOM_LOG_WARN);
            return false;
        }

        stream_copy_to_stream($source, $dest);

        $this->close();
        return true;
    }

    /**
     * Updates the contents of the attachments with the contents of the file specified.
     * This is a wrapper for copy_from_handle.
     *
     * @param string $filename The file to read.
     * @return boolean Indicating success.
     */
    function copy_from_file($filename)
    {
        $source = @fopen ($filename, 'r');
        if (! $source)
        {
            debug_add('Could not open file for reading.' . midcom_connection::get_error_string(), MIDCOM_LOG_WARN);
            midcom::get('debug')->log_php_error(MIDCOM_LOG_WARN);
            return false;
        }
        $result = $this->copy_from_handle($source);
        fclose($source);
        return $result;
    }
}
?>

<?php
/**
 * @package midcom.services
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * This is the handling class of the cron service. When executed, it checks all component
 * manifests for cron jobs and runs them sequentially. The components are processed in the
 * order they are returned by the component loader, the jobs of a single component are run
 * in the order they are listed in the configuration array.
 *
 * <b>Cron Job configuration</b>
 *
 * Each cron job is defined by an associative array containing the following keys:
 *
 * - <i>string handler</i> holds the full class name which should handle the cron job invocation,
 *   it will be defined by the responsible component.
 * - <i>int recurrence</i> must be one of MIDCOM_CRON_* constants.
 * - <i>string component (INTERNAL)</i> holds the name of the component this Cron job is associated with.
 *   This key is created automatically.
 *
 * The Cron service uses <i>customdata</i> section of the manifest, using the key <i>midcom.services.cron</i>
 * as you might have guessed. So, an example cron entry could look like this:
 *
 * <code>
 * 'customdata' => Array
 * (
 *     'midcom.services.cron' => Array
 *     (
 *         Array
 *         (
 *             'handler' => 'net_nehmer_static_cron_test',
 *             'recurrence' => MIDCOM_CRON_MINUTE,
 *         )
 *     ),
 * ),
 * </code>
 *
 * A simple (and useless) handler class would look like this:
 *
 * <code>
 * <?php
 * class net_nehmer_static_cron_test extends midcom_baseclasses_components_cron_handler
 * {
 *     function _on_initialize()
 *     {
 *         return true;
 *     }
 *
 *     function _on_execute()
 *     {
 *         $this->print_error("Executing...");
 *         $this->print_error(strftime('%x %X'));
 *     }
 * }
 * </code>
 *
 * <b>Cron Job implementation suggestions</b>
 *
 * You should keep output to stdout to an absolute minimum. Normally, no output whatsoever
 * should be made, as the cron service itself is invoked using some kind of Cron Daemon. Only
 * if you output nothing, no status mail will be generated by cron.
 *
 * <b>Launching MidCOM Cron from a System Cron</b>
 *
 * You need to request the midcom-exec-midcom/cron.php page of your website to have cron running.
 * Lynx or the GET command line tools can be used, for example, to retrieve the cron page:
 *
 * <pre>
 * lynx -source http://your.site.com/midcom-exec-midcom/cron.php
 * GET http://your.site.com/midcom-exec-midcom/cron.php
 * </pre>
 *
 * The script produces no output unless anything goes wrong.
 *
 * @package midcom.services
 */
class midcom_services_cron
{
    /**
     * The list of jobs to run. See the class introduction for a more precise definition of
     * these keys.
     *
     * @var midcom_baseclasses_components_cron_handler[]
     */
    private $_jobs = [];

    /**
     * The recurrence rule to use, one of the MIDCOM_CRON_* constants (MIDCOM_CRON_MINUTE, MIDCOM_CRON_HOUR, MIDCOM_CRON_DAY).
     * Set in the constructor
     *
     * @var int
     */
    private $_recurrence = MIDCOM_CRON_MINUTE;

    /**
     * Jobs specific to the MidCOM core not covered by any component. (Services
     * use this facility for example.)
     *
     * @var Array
     * @todo Factor this out into its own configuration file.
     */
    private $_midcom_jobs = [
        [
            'handler' => 'midcom_cron_loginservice',
            'recurrence' => MIDCOM_CRON_HOUR,
        ],
        [
            'handler' => 'midcom_cron_purgedeleted',
            'recurrence' => MIDCOM_CRON_DAY,
        ],
    ];

    /**
     * Constructor.
     */
    public function __construct($recurrence = MIDCOM_CRON_MINUTE)
    {
        $this->_recurrence = $recurrence;
    }

    /**
     * Load and validate all registered jobs.
     * After this call, all required handler classes will be available.
     *
     * @param array $data The job configurations
     */
    public function load_jobs(array $data)
    {
        foreach ($data as $component => $jobs) {
            // First, verify the component is loaded
            if (   $component != 'midcom'
                && !midcom::get()->componentloader->load_graceful($component)) {
                $msg = "Failed to load the component {$component}. See the debug level log for further information, skipping this component.";
                debug_add($msg, MIDCOM_LOG_ERROR);
                echo "ERROR: {$msg}\n";
                continue;
            }

            foreach ($jobs as $job) {
                try {
                    if ($this->_validate_job($job)) {
                        $job['component'] = $component;
                        $this->_jobs[] = $job;
                    }
                } catch (midcom_error $e) {
                    $e->log(MIDCOM_LOG_ERROR);
                    debug_print_r('Got this job declaration:', $job);
                    echo "ERROR: Failed to register a job for {$component}: " . $e->getMessage() . "\n";
                }
            }
        }
        return $this->_jobs;
    }

    /**
     * Check a jobs definition for validity.
     *
     * @param array $job The job to register.
     * @return boolean Indicating validity.
     */
    private function _validate_job(array $job)
    {
        if (!array_key_exists('handler', $job)) {
            throw new midcom_error("No handler declaration.");
        }
        if (!array_key_exists('recurrence', $job)) {
            throw new midcom_error("No recurrence declaration.");
        }
        if (!class_exists($job['handler'])) {
            throw new midcom_error("Handler class {$job['handler']} is not available.");
        }
        switch ($job['recurrence']) {
            case MIDCOM_CRON_MINUTE:
            case MIDCOM_CRON_HOUR:
            case MIDCOM_CRON_DAY:
                break;

            default:
                throw new midcom_error("Invalid recurrence.");
        }

        return $job['recurrence'] == $this->_recurrence;
    }

    /**
     * This is the main cron handler function.
     */
    public function execute()
    {
        if (empty($this->_jobs)) {
            $data = midcom::get()->componentloader->get_all_manifest_customdata('midcom.services.cron');
            $data['midcom'] = $this->_midcom_jobs;
            $this->load_jobs($data);
        }
        array_map([$this, '_execute_job'], $this->_jobs);
    }

    /**
     * Executes the given job.
     *
     * @param array $job The job to execute.
     */
    private function _execute_job(array $job)
    {
        debug_print_r('Executing job:', $job);

        $handler = new $job['handler']();
        if (!$handler->initialize($job)) {
            $msg = "Failed to execute a job for {$job['component']}: Handler class failed to initialize.";
            debug_add($msg, MIDCOM_LOG_WARN);
            return;
        }
        $handler->execute();
    }
}

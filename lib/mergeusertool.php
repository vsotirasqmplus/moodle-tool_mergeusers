<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Utility file.
 *
 * The effort of all given authors below gives you this current version of the file.
 *
 * @package    tool
 * @subpackage mergeusers
 * @author     Nicolas Dunand <Nicolas.Dunand@unil.ch>
 * @author     Mike Holzer
 * @author     Forrest Gaston
 * @author     Juan Pablo Torres Herrera
 * @author     Jordi Pujol-Ahulló <jordi.pujol@urv.cat>,  SREd, Universitat Rovira i Virgili
 * @author     John Hoopes <hoopes@wisc.edu>, University of Wisconsin - Madison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(dirname(dirname(__DIR__)))) . '/config.php');
try {
    require_login();
} catch (coding_exception | require_login_exception | moodle_exception $e) {
    die($e->getMessage());
}
global $CFG;

require_once($CFG->dirroot . '/lib/clilib.php');
require_once(__DIR__ . '/autoload.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/mergeusers/lib.php');
require_once(__DIR__ . '/../locallib.php');

/**
 * Lifecycle:
 * <ol>
 *   <li>Once: <code>$mut = new MergeUserTool();</code></li>
 *   <li>N times: <code>$mut->merge($from, $to);</code> Passing two objects with at least
 *   two attributes ('id' and 'username') on each, this will merge the user $from into the
 *   user $to, so that the $from user will be empty of activity.</li>
 * </ol>
 *
 * @author Jordi Pujol-Ahulló
 */
class MergeUserTool {
    /**
     * @var array associative array showing the user-related fields per database table,
     * without the $CFG->prefix on each.
     */
    protected $userfieldspertable;

    /**
     * @var array string array with all known database table names to skip in analysis,
     * without the $CFG->prefix on each.
     */
    protected $tablestoskip;

    /**
     * @var array string array with the current skipped tables with the $CFG->prefix on each.
     */
    protected $tablesskipped;

    /**
     * @var array associative array with special cases for tables with compound indexes,
     * without the $CFG->prefix on each.
     */
    protected $tableswithcompindex;

    /**
     * @var array array with table names (without $CFG->prefix) and the list of field names
     * that are related to user.id. The key 'default' is the default for any non matching table name.
     */
    protected $userfieldnames;

    /**
     * @var tool_mergeusers_logger logger for merging users.
     */
    protected $logger;

    /**
     * @var array associative array (tablename => classname) with the
     * TableMerger tools to process all database tables.
     */
    protected $tablemergers;

    /**
     * @var array list of table names processed by TableMerger's.
     */
    protected $tablesprocbytblmerge;

    /**
     * @var bool if true then never commit the transaction, used for testing.
     */
    protected $alwaysrollback;

    /**
     * @var bool if true then write out all sql, used for testing.
     */
    protected $debugdb;

    /**
     * @var array Warning messages
     */
    protected $warnings;

    /**
     * Initializes
     *
     * @param tool_mergeusers_config|null $config local configuration.
     * @param tool_mergeusers_logger|null $logger logger facility to save results of mergings.
     *
     * @throws ReflectionException
     * @throws dml_exception
     * @throws moodle_exception
     * @noinspection PhpUndefinedFieldInspection
     * @global       object $CFG
     */
    public function __construct(tool_mergeusers_config $config = null, tool_mergeusers_logger $logger = null) {
        $this->logger = (is_null($logger)) ? new tool_mergeusers_logger() : $logger;
        $config = (is_null($config)) ? tool_mergeusers_config::instance() : $config;

        $this->checktransactionsupport();

        // These are tables we don't want to modify due to logging or security reasons.
        // We flip key<-->value to accelerate lookups.
        $this->tablestoskip = array_flip($config->exceptions);
        $excluded = explode(',', get_config('tool_mergeusers', 'excluded_exceptions'));
        $excluded = array_flip($excluded);
        if (!isset($excluded['none'])) {
            foreach ($excluded as $exclude => $nonused) {
                unset($this->tablestoskip[$exclude]);
                unset($nonused);
            }
        }

        // These are special cases, corresponding to tables with compound indexes that need a special treatment.
        $this->tableswithcompindex = $config->compoundindexes;

        // Initializes user-related field names.
        $this->userfieldnames = $config->userfieldnames;

        // Load available TableMerger tools.
        $tablemergers = [];
        $tablesprocbytblmerge = [];
        foreach ($config->tablemergers as $tablename => $class) {
            $tm = new $class();
            // Ensure any provided class is a class of TableMerger.
            if (!$tm instanceof TableMerger) {
                // Aborts execution by showing an error.
                if (CLI_SCRIPT) {
                    cli_error(
                            'Error: ' . __METHOD__ . ':: ' . mergusergetstring(
                                    'notablemergerclass', 'tool_mergeusers',
                                    $class
                            )
                    );
                } else {
                    print_error(
                            'notablemergerclass', 'tool_mergeusers',
                            new moodle_url('/admin/tool/mergeusers/index.php'), $class
                    );
                }
            }
            // Append any additional table to skip.
            $tablesprocbytblmerge = array_merge($tablesprocbytblmerge, $tm->gettablestoskip());
            $tablemergers[$tablename] = $tm;
        }
        $this->tablemergers = $tablemergers;
        $this->tablesprocbytblmerge = array_flip($tablesprocbytblmerge);

        $this->alwaysrollback = !empty($config->alwaysRollback);
        $this->debugdb = !empty($config->debugdb);

        // Initializes the list of fields and tables to check in the current database, given the local configuration.
        $this->init();
    }

    /**
     * Merges two users into one. User-related data records from user id $fromid are merged into the
     * user with id $toid.
     *
     * @param int $toid The user inheriting the data
     * @param int $fromid The user being replaced
     *
     * @return array An array(bool, array, int) having the following cases: if array(true, log, id)
     * users' merging was successful and log contains all actions done; if array(false, errors, id)
     * means users' merging was aborted and errors contain the list of errors.
     * The last id is the log id of the merging action for later visual revision.
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws moodle_exception
     * @global object $CFG
     * @global moodle_database $DB
     * @noinspection PhpUndefinedMethodInspection
     */
    public function merge(int $toid, int $fromid): array {
        list($success, $log) = $this->_merge($toid, $fromid);

        $eventpath = "\\tool_mergeusers\\event\\";
        $eventpath .= ($success) ? 'user_merged_success' : 'user_merged_failure';

        // Method Reference /lib/classes/event/base.php::create() .
        $event = $eventpath::create([
                        'context' => context_system::instance()
                    , 'other' => ['usersinvolved' => ['toid' => $toid, 'fromid' => $fromid]
                            , 'log' => $log]
                ]
        );
        $event->trigger();
        $logid = $this->logger->log($toid, $fromid, $success, $log);
        return [$success, $log, $logid];
    }

    private function _mergetry($fromid, $toid, &$actionlog, &$errormessages) {
        global $DB;
        try {
            // Processing each table name.
            $data = ['toid' => $toid, 'fromid' => $fromid,];
            foreach ($this->userfieldspertable as $tablename => $userfields) {
                $data['tableName'] = $tablename;
                $data['userFields'] = $userfields;
                if (isset($this->tableswithcompindex[$tablename])) {
                    $data['compoundIndex'] = $this->tableswithcompindex[$tablename];
                } else {
                    unset($data['compoundIndex']);
                }

                $tablemerger = (isset($this->tablemergers[$tablename])) ?
                        $this->tablemergers[$tablename] :
                        $this->tablemergers['default'];

                // Process the given $tableName.
                $tablemerger->merge($data, $actionlog, $errormessages);
            }

            $this->updategrades($toid, $fromid);
        } catch (Exception $e) {
            $errormessages[] = nl2br(
                    "Exception thrown when merging: '" . $e->getMessage() . '".' .
                    html_writer::empty_tag('br') . $DB->get_last_error() . html_writer::empty_tag('br') .
                    'Trace:' . html_writer::empty_tag('br') .
                    $e->getTraceAsString() . html_writer::empty_tag('br')
            );
        }
    }

    /**
     * Real method that performs the merging action.
     *
     * @param int $toid The user inheriting the data
     * @param int $fromid The user being replaced
     *
     * @return array An array(bool, array) having the following cases: if array(true, log)
     * users' merging was successful and log contains all actions done; if array(false, errors)
     * means users' merging was aborted and errors contain the list of errors.
     * @throws dml_transaction_exception
     * @global object $CFG
     * @global moodle_database $DB
     */
    private function _merge(int $toid, int $fromid): array {
        global $DB;

        // Initial checks.
        // Are they the same?
        if ($fromid == $toid) {
            // Yes. do nothing.
            return [false, [mergusergetstring('errorsameuser', 'tool_mergeusers')]];
        }

        // Ok, now we have to work;-) !
        // First of all... initialization!
        $errormessages = [];
        $actionlog = [];

        $starttime = time();
        $starttimestring = mergusergetstring('starttime', 'tool_mergeusers', userdate($starttime));
        $actionlog[] = $starttimestring;

        $transaction = $DB->start_delegated_transaction();

        $this->_mergetry($fromid, $toid, $actionlog, $errormessages);

        if ($this->alwaysrollback) {
            $transaction->rollback(new Exception('alwaysRollback option is set so rolling back transaction'));
        }

        // Concludes with true if no error.
        if (empty($errormessages)) {
            $transaction->allow_commit();

            // Add skipped tables as first action in log.
            $skippedtables = [];
            if (!empty($this->tablesskipped)) {
                $skippedtables[] = mergusergetstring('tableskipped', 'tool_mergeusers', implode(', ', $this->tablesskipped));
            }

            $finishtime = time();
            $actionlog[] = mergusergetstring('finishtime', 'tool_mergeusers', userdate($finishtime));
            $actionlog[] = mergusergetstring('timetaken', 'tool_mergeusers', $finishtime - $starttime);

            return [true, array_merge($skippedtables, $actionlog)];
        } else {
            try {
                // Thrown controlled exception.
                $transaction->rollback(new Exception(__METHOD__ . ':: Rolling back transcation.'));
            } catch (Exception $e) { // Do nothing, just for correctness.
                mtrace($e->getMessage());
            }
        }

        $finishtime = time();
        $errormessages[] = $starttimestring;
        $errormessages[] = mergusergetstring('timetaken', 'tool_mergeusers', $finishtime - $starttime);

        // Concludes with an array of error messages otherwise.
        return [false, $errormessages];
    }

    // 0 ****************** INTERNAL UTILITY METHODS ***********************************************.

    private function inittables($tablenames, &$userfieldspertable) {
        foreach ($tablenames as $tablename) {

            if (!trim($tablename)) {
                // This section should never be executed due to the way Moodle returns its resultsets.
                // Skipping due to blank table name.
                continue;
            } else {
                // Table specified to be excluded.
                if (isset($this->tablestoskip[$tablename])) {
                    $this->tablesskipped[$tablename] = $tablename;
                    continue;
                }
                // Table specified to be processed additionally by a TableMerger.
                if (isset($this->tablesprocbytblmerge[$tablename])) {
                    continue;
                }
            }

            // Detect available user-related fields among database tables.
            $userfields = (isset($this->userfieldnames[$tablename])) ?
                    $this->userfieldnames[$tablename] :
                    $this->userfieldnames['default'];

            $arrayuserfields = array_flip($userfields);
            $currentfields = $this->getcurrentuserfieldnames($tablename, $arrayuserfields);

            if (is_array($currentfields) && count($currentfields) > 0) {
                $userfieldspertable[$tablename] = $currentfields;
            }
        }

    }

    /**
     * Initializes the list of database table names and user-related fields for each table.
     *
     * @global object $CFG
     * @global moodle_database $DB
     */
    private function init() {
        global $DB;

        $userfieldspertable = [];

        // Name of tables comes without db prefix.
        $tablenames = $DB->get_tables(false);

        $this->inittables($tablenames, $userfieldspertable);

        $this->userfieldspertable = $userfieldspertable;

        $existingcompind = $this->tableswithcompindex;
        foreach ($this->tableswithcompindex as $tablename => $columns) {
            $chosencolumns = array_merge($columns['userfield'], $columns['otherfields']);

            $columnnames = [];
            foreach ($chosencolumns as $columnname) {
                $columnnames[$columnname] = 0;
            }

            $tablecolumns = $DB->get_columns($tablename, false);

            foreach ($tablecolumns as $column) {
                if (isset($columnnames[$column->name])) {
                    $columnnames[$column->name] = 1;
                }
            }

            // If we find some compound index with missing columns,
            // it is that loaded configuration does not corresponds to current database scheme
            // and this index does not apply.
            $found = array_sum($columnnames);
            if (count($columnnames) !== $found) {
                unset($existingcompind[$tablename]);
            }
        }

        // Update the attribute with the current existing compound indexes per table.
        $this->tableswithcompindex = $existingcompind;
    }

    /**
     * Checks whether the current database supports transactions.
     * If settings of this plugin are set up to allow only transactions,
     * this method aborts the execution. Otherwise, this method will return
     * true or false whether the current database supports transactions or not,
     * respectively.
     *
     * @return bool true if database transactions are supported. false otherwise.
     * @throws dml_exception
     * @throws moodle_exception
     * @throws ReflectionException
     */
    public function checktransactionsupport(): bool {
        global $CFG;

        $transsupported = tool_mergeusers_transactionssupported();
        $forceonlytrans = get_config('tool_mergeusers', 'transactions_only');

        if (!$transsupported && $forceonlytrans) {
            if (CLI_SCRIPT) {
                cli_error(
                        'Error: ' . __METHOD__ . ':: ' .
                        mergusergetstring(
                                'errortransactionsonly', 'tool_mergeusers',
                                $CFG->dbtype
                        )
                );
            } else {
                print_error(
                        'errortransactionsonly', 'tool_mergeusers',
                        new moodle_url('/admin/tool/mergeusers/index.php'), $CFG->dbtype
                );
            }
        }

        return $transsupported;
    }

    /**
     * Gets the matching fields on the given $tableName against the given $userFields.
     * string array with matching field names otherwise.
     *
     * @param string $tablename
     * @param array $userfields candidate user fields to check.
     *
     * @return array
     */
    private function getcurrentuserfieldnames(string $tablename, array $userfields): array {
        global $DB;
        $columns = $DB->get_columns($tablename, false);
        $usercolumns = [];
        foreach ($columns as $column) {
            if (isset($userfields[$column->name])) {
                $usercolumns[$column->name] = $column->name;
            }
        }
        return $usercolumns;
    }

    /**
     * Update all of the target user's grades.
     *
     * @param int $toid User id
     * @param int $fromid
     *
     * @throws       coding_exception
     * @throws       dml_exception
     * @noinspection SqlDialectInspection
     */
    private function updategrades(int $toid, int $fromid) {
        global $DB, $CFG;
        include_once($CFG->libdir . '/gradelib.php');

        $sql = "SELECT DISTINCT gi.id, gi.iteminstance, gi.itemmodule, gi.courseid
                FROM {grade_grades} gg
                INNER JOIN {grade_items} gi on gg.itemid = gi.id
                WHERE itemtype = 'mod' AND (gg.userid = :toid OR gg.userid = :fromid)";

        $iteminstances = $DB->get_records_sql($sql, ['toid' => $toid, 'fromid' => $fromid]);

        foreach ($iteminstances as $iteminstance) {
            $cm = null;
            $activity = $DB->get_record(
                    $iteminstance->itemmodule,
                    ['id' => $iteminstance->iteminstance]
            );
            if ($activity) {
                if ($activity->id) {
                    $cm = get_coursemodule_from_instance(
                            $iteminstance->itemmodule,
                            $activity->id, $iteminstance->courseid
                    );
                }
                if ($cm) {
                    $activity->modname = $iteminstance->itemmodule;
                    $activity->cmidnumber = $cm->idnumber;
                    grade_update_mod_grades($activity, $toid);
                } else {
                    $this->warnings[] = "Can not find course {$iteminstance->courseid} "
                            . "module {$iteminstance->itemmodule}";
                }
            } else {
                $this->warnings[] = "Can not find {$iteminstance->itemmodule}" .
                        " activity with id {$iteminstance->iteminstance}";
            }
        }
    }
}

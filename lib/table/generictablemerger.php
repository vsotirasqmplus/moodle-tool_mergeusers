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
 * Generic implementation of the TableMerger.
 *
 * @package    tool
 * @subpackage mergeusers
 * @author     Jordi Pujol-Ahulló <jordi.pujol@urv.cat>,  SREd, Universitat Rovira i Virgili
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../../locallib.php');
/**
 * Generic implementation of a TableMerger
 *
 * @author jordi
 */
class GenericTableMerger implements TableMerger {
    const CHUNK_SIZE = 500;

    /**
     * Sets that in case of conflict, data related to new user is kept.
     * Otherwise (when false), data related to old user is kept.
     *
     * @var int
     */
    protected $newidtomaintain;

    /**
     * GenericTableMerger constructor.
     *
     * @throws dml_exception
     */
    public function __construct() {
        $this->newidtomaintain = get_config('tool_mergeusers', 'uniquekeynewidtomaintain');
    }

    /**
     * The given TableMerger can assist the merging of the users in
     * a table, but affecting to multiple tables. If so, return an
     * array with the list of table names to skip.
     *
     * @return array List of database table names without the $CFG->prefix.
     * Returns an empty array when nothing to do.
     */
    public function gettablestoskip(): array {
        return []; // Empty array when doing nothing.
    }

    /**
     * Merges the records related to the given users given in $data,
     * updating/appending the list of $errorMessages and $actionLog.
     *
     * @param array $data array with the necessary data for merging records.
     * @param array $errormessages list of error messages.
     * @param array $actionlog
     *
     * @throws dml_exception
     */
    public function merge(array $data, array &$errormessages, array &$actionlog) {
        foreach ($data['userFields'] as $fieldname) {
            $recordstoupdate = $this->get_records_to_be_updated($data, $fieldname);
            if (count($recordstoupdate) == 0) {
                // This userid is not present in these table and field names.
                continue;
            }

            $keys = array_keys($recordstoupdate); // Get the 'id' field from the result set.
            $recordstomodify = array_combine($keys, $keys);

            if (isset($data['compoundIndex'])) {
                $this->mergecompoundindex($data, $fieldname,
                        $this->getotherfieldsoncompoundindex($fieldname, $data['compoundIndex']),
                        $recordstomodify,
                        $actionlog, $actionlog);
            }

            $this->updateallrecords($data, $recordstomodify, $fieldname, $actionlog, $actionlog);
        }
    }

    /*     * ****************** UTILITY METHODS ***************************** */

    /**
     * Both users may appear in the same table under the same database index or so,
     * making some kind of conflict on Moodle and the database model. For simplicity, we always
     * use "compound index" to refer to it below.
     *
     * The merging operation for these cases are treated as follows:
     *
     * Possible scenarios:
     *
     * <ul>
     *   <li>$currentId only appears in a given compound index: we have to update it.</li>
     *   <li>$newId only appears in a given compound index: do nothing, skip.</li>
     *   <li>$currentId and $newId appears in the given compound index: delete the record for the $currentId.</li>
     * </ul>
     *
     * This function extracts the records' ids that have to be updated to the $newId, appearing only the
     * $currentId, and deletes the records for the $currentId when both appear.
     *
     * @param array $data array with the details of merging
     * @param string $userfield table's field name that refers to the user id.
     * @param array $otherfields table's field names that refers to the other members of the compound
     *                                          index.
     * @param array $recordstomodify array with current $table's id to update.
     * @param array $actionlog Array where to append the list of actions done.
     * @param array $errormessages Array where to append any error occurred.
     *
     * @throws dml_exception
     * @global object $CFG
     * @global moodle_database $DB
     */
    protected function mergecompoundindex(array $data, string $userfield, array $otherfields,
            array &$recordstomodify, array &$actionlog,
            array &$errormessages) {
        global $DB;

        $otherfieldsstr = implode(', ', $otherfields);
        $sql = 'SELECT id, ' . $userfield . ', ' . $otherfieldsstr .
                ' FROM {' . $data['tableName'] . '} ' .
                ' WHERE ' . $userfield . ' IN ( ?, ?)';
        $result = $DB->get_records_sql($sql, [$data['fromid'], $data['toid']]);

        $itemarr = [];
        $idstoremove = [];
        foreach ($result as $id => $resobj) {
            $keyfromother = [];
            foreach ($otherfields as $of) {
                $keyfromother[] = $resobj->$of;
            }
            $keyfromotherstr = implode('-', $keyfromother);
            $itemarr[$keyfromotherstr][$resobj->$userfield] = $id;
        }

        $this->mergecomopmindex($recordstomodify, $itemarr, $data, $idstoremove);

        // We know that idsToRemove have always to be removed and NOT to be updated.
        foreach ($idstoremove as $id) {
            if (isset($recordstomodify[$id])) {
                unset($recordstomodify[$id]);
            }
        }

        $this->cleanrecordsoncompoundindex($data, $idstoremove, $actionlog, $errormessages);

    }

    /**
     * @param $recordstomodify
     * @param $itemarr
     * @param $data
     */
    private function mergecomopmindex(&$recordstomodify, & $itemarr, & $data, & $idstoremove){
        foreach ($itemarr as $otherinfo) {
            // If we have only one result and it is from the current user => update record.
            if (count($otherinfo) == 1) {
                if (isset($otherinfo[$data['fromid']])) {
                    $recordstomodify[$otherinfo[$data['fromid']]] = $otherinfo[$data['fromid']];
                }
            } else { // Both users appears in the group.
                // Confirm both records exist, preventing problems from inconsistent data in database.
                if (isset($otherinfo[$data['toid']]) && isset($otherinfo[$data['fromid']])) {
                    $idstoremove[$otherinfo[$data['fromid']]] = $otherinfo[$data['fromid']];
                }
            }
        }

    }

    /**
     * Processes accordingly the cleaning up of records after a compound index is already processed.
     *
     * This implementation execute an SQL DELETE of all $idsToRemove. Subclasses may redefine this
     * behavior accordingly.
     *
     * @param array $data array with details of merging.
     * @param array $idstoremove array with ids of records to delete.
     * @param array $actionlog array of actions being performed for merging.
     * @param array $errormessages array with found errors while merging users' data.
     *
     * @throws dml_exception
     * @global object $CFG
     * @global moodle_database $DB
     */
    protected function cleanrecordsoncompoundindex(array $data, array $idstoremove,
            array &$actionlog, array &$errormessages) {
        if (empty($idstoremove)) {
            return;
        }

        $chunks = array_chunk($idstoremove, self::CHUNK_SIZE);
        foreach ($chunks as $someidstoremove) {
            $this->cleanrecords($data, $someidstoremove, $actionlog, $errormessages);
        }
    }

    /**
     * @param $data
     * @param $idstoremove
     * @param $actionlog
     * @param $errormessages
     *
     * @throws dml_exception
     */
    protected function cleanrecords($data, $idstoremove, &$actionlog, &$errormessages) {
        global $CFG, $DB;

        if (empty($idstoremove)) {
            return;
        }

        $tablename = $CFG->prefix . $data['tableName'];
        $idsgobyebye = implode(', ', $idstoremove);
        $sql = 'DELETE FROM ' . $tablename . ' WHERE id IN (' . $idsgobyebye . ')';

        if ($DB->execute($sql)) {
            $actionlog[] = $sql;
        } else {
            // An error occurred during DB query.
            $errormessages[] = mergusergetstring('tableko', 'tool_mergeusers',
                            $data['tableName']) . ': ' . $DB->get_last_error();
        }
        unset($idsgobyebye);
    }

    /**
     * Updates the table, replacing the user.id for the $data['toid'] on all
     * records specified by the ids on $recordsToModify.
     *
     * @param array $data array with details of merging.
     * @param array $recordstomodify list of record ids to update with $toid.
     * @param string $fieldname field name of the table to update.
     * @param array $actionlog list of performed actions.
     * @param array $errormessages list of error messages.
     *
     * @throws dml_exception
     */
    protected function updateallrecords(array $data, array $recordstomodify, string $fieldname,
            array &$actionlog, array &$errormessages) {
        if (count($recordstomodify) == 0) {
            // If no records, do nothing ;-).
            return;
        }

        $chunks = array_chunk($recordstomodify, self::CHUNK_SIZE);
        foreach ($chunks as $chunk) {
            $this->updaterecords($data, $chunk, $fieldname, $actionlog, $errormessages);
        }
    }

    /**
     * @param array $data
     * @param array $recordstomodify
     * @param string $fieldname
     * @param array $actionlog
     * @param array $errormessages
     *
     * @throws dml_exception
     */
    protected function updaterecords(array $data, array $recordstomodify, string $fieldname, array &$actionlog,
            array &$errormessages) {
        global $CFG, $DB;
        $tablename = $CFG->prefix . $data['tableName'];
        $idstring = implode(', ', $recordstomodify);
        $updaterecords = 'UPDATE ' . $tablename . ' ' .
                ' SET ' . $fieldname . " = '" . $data['toid'] .
                "' WHERE " . self::PRIMARY_KEY . ' IN (' . $idstring . ')';

        try {
            if (!$DB->execute($updaterecords)) {
                $errormessages[] = mergusergetstring('tableko', 'tool_mergeusers', $data['tableName']) .
                        ': ' . $DB->get_last_error();
            }
            $actionlog[] = $updaterecords;
        } catch (Exception $e) {
            // If we get here, we have found a unique index on a user-id related column.
            // Therefore, there will be only a single record from one or other user.
            $useridtoclean = ($this->newidtomaintain) ? $data['fromid'] : $data['toid'];
            $deleterecord = 'DELETE FROM ' . $tablename .
                    ' WHERE ' . $fieldname . " = '" . $useridtoclean . "'";

            if (!$DB->execute($deleterecord)) {
                $errormessages[] = mergusergetstring('tableko', 'tool_mergeusers', $data['tableName']) .
                        ': ' . $DB->get_last_error();
            }
            $actionlog[] = $deleterecord;
        }
    }

    /**
     * Gets the fields name on a compound index case, excluding the given $userField.
     * Therefore, if there are multiple user-related fields in a compound index,
     * return the rest of the column names except the given $userField. Otherwise,
     * it returns simply the 'otherfields' array from the $compoundIndex definition.
     *
     * @param string $userfield current user-related field being analyzed.
     * @param array $compoundindex related config data for the compound index.
     *
     * @return array an array with the other field names of the compound index.
     */
    protected function getotherfieldsoncompoundindex(string $userfield, array $compoundindex): array {
        // We can alternate column names when both fields are user-related.
        if (count($compoundindex['userfield']) > 1) {
            $all = array_merge($compoundindex['userfield'], $compoundindex['otherfields']);
            $all = array_flip($all);
            unset($all[$userfield]);
            return array_flip($all);
        }
        // Default behavior.
        return $compoundindex['otherfields'];
    }

    /**
     * @param $data
     * @param $fieldname
     *
     * @return ?array
     * @throws dml_exception
     */
    protected function get_records_to_be_updated($data, $fieldname): ?array {
        global $DB;
        return $DB->get_records_sql('SELECT ' . self::PRIMARY_KEY .
                ' FROM {' . $data['tableName'] . '} WHERE ' .
                $fieldname . " = '" . $data['fromid'] . "'");
    }

}

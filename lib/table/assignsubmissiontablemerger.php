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
 * @author    Daniel Tomé <danieltomefer@gmail.com>
 * @copyright 2018 Servei de Recursos Educatius (http://www.sre.urv.cat)
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../duplicateddata/assignsubmissionduplicateddatamerger.php');
require_once(__DIR__ . '/../db/dbassignsubmission.php');

class AssignSubmissionTableMerger extends GenericTableMerger {

    private $findassignsubs;
    private $duplicateddatamerger;

    /**
     * AssignSubmissionTableMerger constructor.
     *
     * @throws dml_exception
     */
    public function __construct() {
        parent::__construct();
        $this->findassignsubs = new db_assign_submission();
        $this->duplicateddatamerger = new AssignSubmissionDuplicatedDataMerger();
    }

    /**
     * @param array $data
     * @param string $userfield
     * @param array $otherfields
     * @param array $recordstomodify
     * @param array $actionlog
     * @param array $errormessages
     *
     * @throws dml_exception
     * @throws coding_exception
     */
    public function mergeccompoundindex(array $data, string $userfield, array $otherfields,
            array &$recordstomodify, array &$actionlog, array &$errormessages
    ) {

        $fromuserid = $data['fromid'];
        $touserid = $data['toid'];
        $assignstocheck = $recordstomodify;
        $recordstomodify = [];
        $assignsubstoremove = [];
        mtrace($userfield . ' ' . implode(', ', $otherfields));
        foreach ($assignstocheck as $assignid) {
            $olduserlatestsub = $this->findassignsubs->latest_from_assign_and_user($assignid, $fromuserid);
            $newuserlatestsub = $this->findassignsubs->latest_from_assign_and_user($assignid, $touserid);

            if (!empty($newuserlatestsub)) {
                $duplicateddata = $this->duplicateddatamerger->merge($olduserlatestsub, $newuserlatestsub);
                $recordstomodify += $duplicateddata->to_modify();
                $assignsubstoremove += $duplicateddata->to_remove();
                continue;
            }

            if ($oldusersubmissions = $this->findassignsubs->all_from_assign_and_user($assignid, $fromuserid)) {
                $assignsubstomod = array_keys($oldusersubmissions);
                $recordstomodify += array_combine($assignsubstomod, $assignsubstomod);
            }
        }

        foreach ($assignsubstoremove as $assignsubmissionid) {
            if (isset($recordstomodify[$assignsubmissionid])) {
                unset($recordstomodify[$assignsubmissionid]);
            }
        }

        $this->cleanrecordsoncompoundindex($data, $assignsubstoremove, $actionlog, $errormessages);
    }

    /**
     * @param $data
     * @param $fieldname
     *
     * @return array
     * @throws dml_exception
     */
    protected function getrecordstobeupdated($data, $fieldname): ?array {
        global $DB;
        // Assign submissions may have attempts. We need a unique list of assignment ids.
        return $DB->get_records($data['tableName'], [$fieldname => $data['fromid']], '', 'DISTINCT assignment');
    }
}

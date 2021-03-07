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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once(__DIR__ . '/locallib.php');

/**
 * TableMerger to process quiz_attempts table.
 *
 * Quiz attempts are a complex entity in that they also span multiple tables into the question engine
 * and so, if both users have attempted a quiz, quiz_attempts and quiz_grades tables have to be updated
 * correspondingly.
 *
 *
 * There are 3 possible ways for quiz attempts to occur:
 *
 * 1.  The old user only attempts the quiz
 *      - In this case the quiz attempt is transferred over through the $recordsToModify array
 *      - Normal merging on compound index will process it naturally,
 *        using $toid to set the userid in the quiz grades table.
 * 2. The new user only attempts the quiz
 *      - In this case it won't matter, no processing is needed
 * 3. Both users attempt the quiz. There are 4 different kind of actions to perform:
 *      - ACTION_REMAIN: Nothing is done: no deletion, no update; quiz attempts remain related to each user.
 *      - ACTION_RENUMBER: Moves attempts from old user to be the first attempts of the new user.
 *        Quiz operations are performed to normalize this new scenario.
 *      - ACTION_DELETE_FROM_SOURCE: Deletes quiz_attempts records from the old user attempts.
 *        This means that the attempts to have into account will be only the last ones (those made
 *        with the new user). Behavior suggested by John Hoopes (well, John proposed do nothing
 *        with those attempts, leaving them related to the old user; we ).
 *      - ACTION_DELETE_FROM_TARGET: Deletes quiz_attempts records from the new user attempts.
 *        This means that the old user's attempts are left, and removed those from the new user
 *        as if the new user was cheating. Behaviour suggested by Nicolas Dunand.
 *
 * @package    tool
 * @subpackage mergeusers
 * @author     John Hoopes <hoopes@wisc.edu>, 2014 University of Wisconsin - Madison
 * @author     Jordi Pujol-Ahulló <jordi.pujol@urv.cat>,  SREd, Universitat Rovira i Virgili
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class QuizAttemptsMerger extends GenericTableMerger {

    /**
     * @var string When cleaning up records, this action deletes records from old user.
     */
    const ACTION_DELETE_FROM_SOURCE = 'delete_fromid';

    /**
     * @var string When cleaning up records, this action deletes records from new user.
     */
    const ACTION_DELETE_FROM_TARGET = 'delete_toid';

    /**
     * @var string When cleaning up records, this action does not delete records,
     * but renumbers attempts.
     */
    const ACTION_RENUMBER = 'renumber';

    /**
     * @var string Quiz attempts remain related to each user, without merging nor deleting them.
     */
    const ACTION_REMAIN = 'remain';

    /**
     * @var string current defined action.
     */
    protected $action;

    /**
     * Loads the current action from settings to perform when cleaning records.
     * QuizAttemptsMerger constructor.
     *
     * @throws dml_exception
     */
    public function __construct() {
        $this->action = get_config('tool_mergeusers', 'quizattemptsaction');
        parent::__construct();
    }

    /**
     * This TableMerger processes quiz_attempts accordingly, regrading when
     * necessary. So that tables quiz_grades and quiz_grades_history
     * have to be omitted from processing by other TableMergers.
     *
     * @return array
     */
    public function gettablestoskip(): array {
        return ['quiz_grades', 'quiz_grades_history'];
    }

    /**
     * Merges the records related to the given users given in $data,
     * updating/appending the list of $errorMessages and $actionLog.
     *
     * @param array $data array with the necessary data for merging records.
     * @param array $errormessages list of error messages.
     * @param array $actionlog
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws Exception
     */
    public function merge(array $data, array &$errormessages, array &$actionlog) {
        switch ($this->action) {
            case self::ACTION_REMAIN:
                $tables = $data['tableName'] . ', ' . implode(', ', $this->gettablestoskip());
                $actionlog[] = mergusergetstring('qa_action_remain_log', 'tool_mergeusers', $tables);
                break;
            case self::ACTION_DELETE_FROM_SOURCE:
                parent::merge($data, $actionlog, $actionlog);
                break;
            case self::ACTION_DELETE_FROM_TARGET:
                parent::merge($data, $actionlog, $actionlog);
                break;
            case self::ACTION_RENUMBER:
                $this->renumber($data, $actionlog, $actionlog);
                break;
            default:
                throw new Exception('Unexpected value');
        }
    }

    /**
     * Merges the records related to the given users given in $data,
     * updating/appending the list of $errorMessages and $actionLog,
     * by having the union of all attempts and being renumbered by
     * the timestart of each attempt.
     *
     * @param array $data array with the necessary data for merging records.
     * @param array $actionlog list of action performed.
     * @param array $errormessages list of error messages.
     *
     * @throws dml_exception
     */
    protected function renumber(array $data, array &$actionlog, array &$errormessages) {
        global $CFG, $DB;

        $tablename = $CFG->prefix . $data['tableName'];

        // We want to find all quiz attempts made from both users if any.
        $sql = 'SELECT *
FROM ' . $tablename . '
WHERE userid IN (?, ?)
ORDER BY quiz ASC, timestart ASC';

        $allattempts = $DB->get_records_sql($sql, [$data['fromid'], $data['toid']]);

        // When there are attempts, check what we have to do with them.
        if ($allattempts) {

            $toid = $data['toid'];
            $update = ['UPDATE ' . $tablename . ' SET ', ' WHERE id = '];

            // List of quiz ids necessary to recalculate.
            $quizzes = [];
            // List of attempts organized by quiz id.
            $attemptsbyquiz = [];
            // List of users that have attempts per quiz.
            $userids = [];

            // Organize all attempts by quiz and userid.
            foreach ($allattempts as $attempt) {
                $attemptsbyquiz[$attempt->quiz][] = $attempt;
                $userids[$attempt->quiz][$attempt->userid] = $attempt->userid;
            }

            // Processing attempts quiz by quiz.
            foreach ($attemptsbyquiz as $quiz => $attempts) {

                // Do nothing when there is only the target user.
                if (count($userids[$quiz]) === 1 && isset($userids[$quiz][$toid])) {
                    // All attempts are for the target user only; do nothing.
                    continue;
                }

                // Now we know that we have to gather all attempts and renumber them
                // by their timestart.
                //
                // In order to prevent key collisions for (userid, quiz and attempt),
                // we adopt the following procedure:
                //
                // 1. Renumber all attempts updating their attempt to $max + $nattempt.
                // 2. Update all above attempts to subtract $max to their attempt value.
                //
                // In step 1. we have $max set to the total number of attempts from both
                // users, and $nattempt is just an incremental value.
                //
                // In step 2. we renumber all attempts to start from 1 by just subtracting
                // the $max value to their attempt column.
                //
                //
                // total number of attempts from both users.
                $max = count($attempts);
                // Update the list of quiz ids to be recalculated its grade.
                $quizzes[$quiz] = $quiz;
                // Number of attempt when renumbering.
                $nattempt = 1;

                // Renumber all attempts and updating userid when necessary.
                // All attempts have an offset of $max in their attempt column.

                $this->renum($attempts, $toid, $max, $update, $data, $errormessages, $nattempt, $actionlog);

                // Remove the offset of $max from their attempt column to make
                // them start by 1 as expected.
                $updateall = 'UPDATE ' . $tablename .
                        " SET attempt = attempt - $max " .
                        " WHERE quiz = $quiz AND userid = $toid";

                if ($DB->execute($updateall)) {
                    $actionlog[] = $updateall;
                } else {
                    $errormessages[] = mergusergetstring('tableko', 'tool_mergeusers', $data['tableName']) .
                            ': ' . $DB->get_last_error();
                }
            }

            // Recalculate grades for updated quizzes.
            $this->updateallquizzes($data, $quizzes, $actionlog);
        }
    }

    /**
     * @param $attempts
     * @param $toid
     * @param $max
     * @param $update
     * @param $data
     * @param $errormessages
     * @param $nattempt
     * @throws dml_exception
     */
    private function renum($attempts, $toid, $max, $update, & $data, & $errormessages, $nattempt, & $actionlog) {
        global $DB;
        foreach ($attempts as $attempt) {

            $sets = [];
            if ($attempt->userid != $toid) {
                $sets[] = 'userid = ' . $toid;
            }
            $sets[] = 'attempt = ' . ($max + $nattempt);

            $updatesql = $update[0] . implode(', ', $sets) . $update[1] . $attempt->id;
            if ($DB->execute($updatesql)) {
                $actionlog[] = $updatesql;
            } else {
                $errormessages[] = mergusergetstring('tableko', 'tool_mergeusers', $data['tableName']) .
                        ': ' . $DB->get_last_error();
            }

            $nattempt++;

        }

    }

    /**
     * Overriding the default implementation to add a final task: updateQuizzes.
     *
     * @param array $data array with details of merging.
     * @param array $recordstomodify list of record ids to update with $toid.
     * @param string $fieldname field name of the table to update.
     * @param array $actionlog list of performed actions.
     * @param array $errormessages list of error messages.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function updateallrecords(array $data, array $recordstomodify, string $fieldname, array &$actionlog,
            array &$errormessages) {
        parent::updateallrecords($data, $recordstomodify, $fieldname, $actionlog, $errormessages);
        $this->updateallquizzes($data, $recordstomodify, $actionlog);
    }

    /**
     * Recalculate grades for any affected quiz.
     *
     * @param array $data array with attributes, like 'tableName'
     * @param array $ids ids of the table to be updated, and so, to update quiz grades.
     * @param array $actionlog
     *
     * @noinspection PhpUnusedParameterInspection*@global moodle_database $DB
     * @throws       dml_exception
     */
    protected function updateallquizzes(array $data, array $ids, array &$actionlog) {
        if (empty($ids)) {
            unset($data);
            // If no ids... do nothing.
            return;
        }
        $chunks = array_chunk($ids, static::CHUNK_SIZE);
        foreach ($chunks as $chunk) {
            $this->updatequizzes($chunk, $actionlog);
        }
    }

    /**
     * @param array $ids
     * @param array $actionlog
     *
     * @throws dml_exception
     */
    protected function updatequizzes(array $ids, array &$actionlog) {
        global $DB;

        $idsstr = "'" . implode("', '", $ids) . "'";

        $sqlquizzes = "
            SELECT * FROM {quiz} q
                    WHERE id IN ($idsstr)
        ";

        $quizzes = $DB->get_records_sql($sqlquizzes);

        if ($quizzes) {
            $actionlog[] = mergusergetstring('qa_grades', 'tool_mergeusers', implode(', ', array_keys($quizzes)));
            foreach ($quizzes as $quiz) {
                // URL https://moodle.org/mod/forum/discuss.php?d=258979 .
                // Recalculate grades for affected quizzes.
                quiz_update_all_final_grades($quiz);
            }
        }
    }
}

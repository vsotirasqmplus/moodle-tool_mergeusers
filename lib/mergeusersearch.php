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
require_login();
global $CFG;

require_once($CFG->dirroot . '/lib/clilib.php');
require_once(__DIR__ . '/autoload.php');
require_once(__DIR__ . '/../locallib.php');
/**
 * A class to perform user search and lookup (verification)
 *
 * @author John Hoopes <hoopes@wisc.edu>
 */
class MergeUserSearch {

    /**
     * Searches the user table based on the input.
     *
     * @param mixed $input input
     * @param string $searchfield The field to search on.  empty string means all fields
     *
     * @return       array $results the results of the search
     * @throws       dml_exception
     * @noinspection SqlDialectInspection
     */
    public function search_users($input, string $searchfield): array {
        global $DB;

        switch ($searchfield) {
            case 'id': // Search on id field.

                $params = ['userid' => $input];
                $sql = 'SELECT * FROM {user} WHERE id = :userid';

                break;
            case 'username': // Search on username.

                $params = ['username' => '%' . $input . '%'];
                $sql = 'SELECT * FROM {user} WHERE username LIKE :username';

                break;
            case 'firstname': // Search on firstname.

                $params = ['firstname' => '%' . $input . '%'];
                $sql = 'SELECT * FROM {user} WHERE firstname LIKE :firstname';

                break;
            case 'lastname': // Search on lastname.

                $params = ['lastname' => '%' . $input . '%'];
                $sql = 'SELECT * FROM {user} WHERE lastname LIKE :lastname';

                break;
            case 'email': // Search on email.

                $params = ['email' => '%' . $input . '%'];
                $sql = 'SELECT * FROM {user} WHERE email LIKE :email';

                break;
            case 'idnumber': // Search on idnumber.

                $params = ['idnumber' => '%' . $input . '%'];
                $sql = 'SELECT * FROM {user} WHERE idnumber LIKE :idnumber';

                break;
            default: // Search on all fields by default.

                $params = ['userid' => $input, 'username' => '%' . $input . '%',
                        'firstname' => '%' . $input . '%', 'lastname' => '%' . $input . '%',
                        'email' => '%' . $input . '%', 'idnumber' => '%' . $input . '%'];

                $sql =
                        'SELECT *
                    FROM {user}
                    WHERE
                        id = :userid OR
                        username LIKE :username OR
                        firstname LIKE :firstname OR
                        lastname LIKE :lastname OR
                        email LIKE :email OR
                        idnumber LIKE :idnumber';

                break;
        }

        $ordering = ' ORDER BY lastname, firstname';

        return $DB->get_records_sql($sql . $ordering, $params);
    }

    /**
     * Verifies whether or not a user exists based upon the user information
     * to verify and the column that matches that information
     *
     * @param mixed $userinfo The identifying information about the user
     * @param string $column The column name to verify against.  (should not be direct user input)
     *
     * @return array
     *      (
     *          0 => Either NULL or the user object.  Will be NULL if not valid user,
     *          1 => Message for invalid user to display/log
     *      )
     * @throws coding_exception
     */
    public function verify_user($userinfo, string $column): array {
        global $DB;
        $message = '';
        try {
            $user = $DB->get_record('user', [$column => $userinfo], '*', MUST_EXIST);
        } catch (Exception $e) {
            $message = mergusergetstring('invaliduser', 'tool_mergeusers') . '(' . $column . '=>' . $userinfo . '): ' . $e->getMessage();
            $user = null;
        }

        return [$user, $message];
    }

}

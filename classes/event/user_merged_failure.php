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
 * The user_merged_failure event.
 *
 * @package    tool
 * @subpackage mergeusers
 * @author     Gerard Cuello Adell <gerard.urv@gmail.com>
 * @copyright  2016 Servei de Recursos Educatius (http://www.sre.urv.cat)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_mergeusers\event;

use coding_exception;
use lang_string;

defined('MOODLE_INTERNAL') || die();

/**
 * Class user_merged_failure called when merging user accounts has gone wrong.
 *
 * @package   tool_mergeusers
 * @author    Gerard Cuello Adell <gerard.urv@gmail.com>
 * @copyright 2016 Servei de Recursos Educatius (http://www.sre.urv.cat)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_merged_failure extends user_merged {

    /**
     * @return lang_string|string
     * @throws coding_exception
     */
    public static function get_name() {
        return get_string('eventusermergedfailure', 'tool_mergeusers');
    }

    /**
     * @return string
     */
    public static function get_legacy_eventname(): string {
        return 'merging_failed';
    }

    /**
     * @return string
     */
    public function get_description(): string {
        return "The user {$this->userid} tried to merge all user-related data records
            from '{$this->other['usersinvolved']['fromid']}' into '{$this->other['usersinvolved']['toid']}' but failed";
    }

}

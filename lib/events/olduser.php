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
 * @package    tool
 * @subpackage mergeusers
 * @author     Jordi Pujol-Ahulló <jordi.pujol@urv.cat>
 * @author     John Hoopes <hoopes@wisc.edu>, University of Wisconsin - Madison
 * @copyright  2013 Servei de Recursos Educatius (http://www.sre.urv.cat)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;
// 0 /** @noinspection PhpIncludeInspection */.
require_once($CFG->libdir . '/gdlib.php');

/**
 * Suspend the old user, by suspending its account, and updating the profile picture.
 * to a generic one.
 *
 * @param object $event stdClass with all event data.
 *
 * @return       bool
 * @throws       dml_exception
 * @global       moodle_database $DB
 * @noinspection PhpUnused
 */
function tool_mergeusers_old_user_suspend(object $event): bool {
    global $DB;

    $oldid = $event->other['usersinvolved']['fromid'];

    // Check configuration to see if the old user gets suspended.
    $enabled = (int) get_config('tool_mergeusers', 'suspenduser');
    if ($enabled !== 1) {
        return true;
    }

    // 1. update suspended flag.
    $olduser = new stdClass();
    $olduser->id = $oldid;
    $olduser->suspended = 1;
    $olduser->timemodified = time();
    try {
        $DB->update_record('user', $olduser);
    } catch (dml_exception $e) {
        mtrace($e->getMessage());
        return false;
    }

    // 2. update profile picture.
    // get source, common image.
    $fullpath = dirname(dirname(__DIR__)) . '/pix/suspended.jpg';
    if (!file_exists($fullpath)) {
        return false; // Do nothing; aborting, given that the image does not exist.
    }

    // Put the common image as the profile picture.
    $context = context_user::instance($oldid);
    if (($newrev = process_new_icon($context, 'user', 'icon', 0, $fullpath))) {
        try {
            $DB->set_field('user', 'picture', $newrev, ['id' => $oldid]);
        } catch (dml_exception $e) {
            mtrace($e->getMessage());
            return false;
        }
    }

    return true;
}

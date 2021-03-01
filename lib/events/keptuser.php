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
 * @copyright  2019 Servei de Recursos Educatius (http://www.sre.urv.cat)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Ensure kept user is not suspended.
 *
 * @param object $event stdClass with all event data.
 *
 * @return bool
 * @noinspection PhpUnused
 */
function tool_mergeusers_make_kept_user_as_not_suspended(object $event): bool
{
	global $DB;

	$userid = $event->other['usersinvolved']['toid'];

	$userkept = new stdClass();
	$userkept->id = $userid;
	$userkept->suspended = 0;
	$userkept->timemodified = time();
	try {
		$DB->update_record('user', $userkept);
	} catch(dml_exception $e) {
		return FALSE;
	}

	return TRUE;
}

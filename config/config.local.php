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
 * @copyright  2013 Servei de Recursos Educatius (http://www.sre.urv.cat)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/*
 * This is the default settings for the correct behaviour of the plugin, given the knowledge base
 * of our experience.
 *
 * Your local Moodle instance may need additional adjusts. Please, do not modify this file.
 * Instead, create or edit in the same directory than this "config.php" a file named
 * "config.local.php" to add/replace elements of the default configuration.
 */
return [

    // Gathering tool.
        'gathering' => 'CLIGathering',

    // Database tables to be excluded from normal processing.
    // You normally will add tables. Be very cautious if you delete any of them.
        'exceptions' => [
                'user_preferences',
                'user_private_key',
                'user_info_data',
                'my_pages',
                'plagiarism_turnitin_users'
        ],

    // List of compound indexes.
    // This list may vary from Moodle instance to another, given that the Moodle version,
    // local changes and non-core plugins may add new special cases to be processed.
    // Put in 'userfield' all column names related to a user (i.e., user.id), and all the rest column names
    // into 'otherfields'.
    // See README.txt for details on special cases.
    // Table names must be without $CFG->prefix.
        'compoundindexes' => [
                'grade_grades' => [
                        'userfield' => ['userid'],
                        'otherfields' => ['itemid'],
                ],
                'groups_members' => [
                        'userfield' => ['userid'],
                        'otherfields' => ['groupid'],
                ],
                'journal_entries' => [
                        'userfield' => ['userid'],
                        'otherfields' => ['journal'],
                ],
                'course_completions' => [
                        'userfield' => ['userid'],
                        'otherfields' => ['course'],
                ],
                'message_contacts' => [ // Both fields are user.id values.
                        'userfield' => ['userid', 'contactid'],
                        'otherfields' => [],
                ],
                'role_assignments' => [
                        'userfield' => ['userid'],
                        'otherfields' => ['contextid', 'roleid'], // I mdl_roleassi_useconrol_ix (not unique).
                ],
                'user_lastaccess' => [
                        'userfield' => ['userid'],
                        'otherfields' => ['courseid'], // I mdl_userlast_usecou_ui (unique).
                ],
                'quiz_attempts' => [
                        'userfield' => ['userid'],
                        'otherfields' => ['quiz', 'attempt'], // I mdl_quizatte_quiuseatt_uix (unique).
                ],
                'cohort_members' => [
                        'userfield' => ['userid'],
                        'otherfields' => ['cohortid'],
                ],
                'certif_completion' => [  // I mdl_certcomp_ceruse_uix (unique).
                        'userfield' => ['userid'],
                        'otherfields' => ['certifid'],
                ],
                'course_modules_completion' => [ // I mdl_courmoducomp_usecou_uix (unique).
                        'userfield' => ['userid'],
                        'otherfields' => ['coursemoduleid'],
                ],
                'scorm_scoes_track' => [ // I mdl_scorscoetrac_usescosco_uix (unique).
                        'userfield' => ['userid'],
                        'otherfields' => ['scormid', 'scoid', 'attempt', 'element'],
                ],
                'assign_grades' => [ // UNIQUE KEY mdl_assigrad_assuseatt_uix.
                        'userfield' => ['userid'],
                        'otherfields' => ['assignment', 'attemptnumber'],
                ],
                'badge_issued' => [ // I unique key mdl_badgissu_baduse_uix.
                        'userfield' => ['userid'],
                        'otherfields' => ['badgeid'],
                ],
                'assign_submission' => [ // I unique key mdl_assisubm_assusegroatt_uix.
                        'userfield' => ['userid'],
                        'otherfields' => ['assignment', 'groupid', 'attemptnumber'],
                ],
                'wiki_pages' => [ // I unique key mdl_wikipage_subtituse_uix.
                        'userfield' => ['userid'],
                        'otherfields' => ['subwikiid', 'title'],
                ],
                'wiki_subwikis' => [ // I unique key mdl_wikisubw_wikgrouse_uix.
                        'userfield' => ['userid'],
                        'otherfields' => ['wikiid', 'groupid'],
                ],
                'user_enrolments' => [
                        'userfield' => ['userid'],
                        'otherfields' => ['enrolid'],
                ],
                'assign_user_flags' => [ // They are actually a unique key, but not in DDL.
                        'userfield' => ['userid'],
                        'otherfields' => ['assignment'],
                ],
                'assign_user_mapping' => [ // They are actually a unique key, but not in DDL.
                        'userfield' => ['userid'],
                        'otherfields' => ['assignment'],
                ],
        ],

    // List of column names per table, where their content is a user.id.
    // These are necessary for matching passed by userids in these column names.
    // In other words, only column names given below will be search for matching user ids.
    // The key 'default' will be applied for any non matching table name.
        'userfieldnames' => [
                'logstore_standard_log' => ['userid', 'relateduserid'],
                'message_contacts' => ['userid', 'contactid'], // Compound index.
                'message' => ['useridfrom', 'useridto'],
                'message_read' => ['useridfrom', 'useridto'],
                'question' => ['createdby', 'modifiedby'],
                'default' => ['authorid', 'reviewerid', 'userid', 'user_id', 'id_user', 'user'], // May appear compound index.
        ],

    // TableMergers to process each database table.
    // 'default' is applied when no specific TableMerger is specified.
        'tablemergers' => [
                'default' => 'GenericTableMerger',
                'quiz_attempts' => 'QuizAttemptsMerger',
                'assign_submission' => 'AssignSubmissionTableMerger',
        ],

        'alwaysRollback' => false,
        'debugdb' => false,
];

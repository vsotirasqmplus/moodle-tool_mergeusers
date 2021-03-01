<?php /** @noinspection PhpPossiblePolymorphicInvocationInspection */
/** @noinspection PhpIncludeInspection */

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
 * Version information
 *
 * @package    tool
 * @subpackage mergeusers
 * @author     Nicolas Dunand <Nicolas.Dunand@unil.ch>
 * @author     Mike Holzer
 * @author     Forrest Gaston
 * @author     Juan Pablo Torres Herrera
 * @author     Jordi Pujol-Ahulló, Sred, Universitat Rovira i Virgili
 * @author     John Hoopes <hoopes@wisc.edu>, University of Wisconsin - Madison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require('../../../config.php');

global $CFG;
global $PAGE;
global $SESSION;

// Report all PHP errors
error_reporting(E_ALL);
ini_set('display_errors', 'On');

require_once($CFG->libdir . '/blocklib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/accesslib.php');
require_once($CFG->libdir . '/weblib.php');

require_once('./index_form.php');
require_once(__DIR__ . '/lib/autoload.php');

$fromuser = NULL;
$touser = NULL;
$numessage = NULL;
try {
	require_login();
	require_capability('tool/mergeusers:mergeusers', context_system::instance());

	admin_externalpage_setup('tool_mergeusers_merge');
// Get possible posted params
	$option = optional_param('option', NULL, PARAM_TEXT);
	if(!$option) {
		if(optional_param('clearselection', FALSE, PARAM_TEXT)) {
			$option = 'clearselection';
		} else if(optional_param('mergeusers', FALSE, PARAM_TEXT)) {
			$option = 'mergeusers';
		}
	}

// Define the form
	$mergeuserform = new mergeuserform();
	$renderer = $PAGE->get_renderer('tool_mergeusers');

	$data = $mergeuserform->get_data();

//may abort execution if database not supported, for security
	$mut = new MergeUserTool();
// Search tool for searching for users and verifying them
	$mus = new MergeUserSearch();
} catch(Exception $e) {
	$option = NULL;
	$mus = NULL;
	$mut = NULL;
	$renderer = NULL;
	$mergeuserform = NULL;
	$data = NULL;
}

// If there was a custom option submitted (by custom form) then use that option
// instead of main form's data
if(!empty($option)) {
	switch($option) {
		// one or two users are selected: save them into session.
		case 'saveselection':
			//get and verify the userids from the selection form using the verify_user function (second field is column)
			// As parameters are optional, there should be conditional execution
			try {
				$olduser = optional_param('olduser', NULL, PARAM_INT);
				$newuser = optional_param('newuser', NULL, PARAM_INT);
				if($olduser)
					list($olduser, $oumessage) = $mus->verify_user($olduser, 'id');
				if($newuser)
					list($newuser, $numessage) = $mus->verify_user($newuser, 'id');
			} catch(coding_exception $e) {
				$olduser = NULL;
				$newuser = NULL;
				$oumessage = NULL;
			}

			if($olduser === NULL && $newuser === NULL) {
				try {
					/** @noinspection PhpPossiblePolymorphicInvocationInspection */
					$renderer->mu_error(get_string('no_saveselection', 'tool_mergeusers'));
				} catch(coding_exception $e) {
				}
				exit(); // end execution for error
			}

			if(empty($SESSION->mut)) {
				$SESSION->mut = new stdClass();
			}

			// Store saved selection in session for display on index page, requires logic to not overwrite existing session
			//   data, unless a "new" old, or "new" new is specified
			// If session old user already has a user and we have a "new" old user, replace the session's old user
			if(empty($SESSION->mut->olduser) || !empty($olduser)) {
				$SESSION->mut->olduser = $olduser;
			}

			// If session new user already has a user and we have a "new" new user, replace the session's new user
			if(empty($SESSION->mut->newuser) || !empty($newuser)) {
				$SESSION->mut->newuser = $newuser;
			}

			/** @noinspection PhpPossiblePolymorphicInvocationInspection */
			$step = (!empty($SESSION->mut->olduser) && !empty($SESSION->mut->newuser)) ?
				$renderer::INDEX_PAGE_CONFIRMATION_STEP :
				$renderer::INDEX_PAGE_SEARCH_STEP;

			/** @noinspection PhpPossiblePolymorphicInvocationInspection */
			echo $renderer->index_page($mergeuserform, $step);
			break;

		// remove any of the selected users to merge, and search for them again.
		case 'clearselection':
			$SESSION->mut = NULL;

			// Redirect back to index/search page for new selections or review selections
			$redirecturl = new moodle_url('/admin/tool/mergeusers/index.php');
			try {
				redirect($redirecturl, NULL, 0);
			} catch(moodle_exception $e) {
				echo 'Cannot Redirect';
			}
			break;

		// proceed with the merging and show results.
		case 'mergeusers':
			// Verify users once more just to be sure.  Both users should already be verified, but just an extra layer of security
			try {
				list($fromuser, $oumessage) = $mus->verify_user($SESSION->mut->olduser->id, 'id');
				list($touser, $numessage) = $mus->verify_user($SESSION->mut->newuser->id, 'id');
			} catch(coding_exception $e) {
				$oumessage = NULL;
				$fromuser = NULL;
				$touser = NULL;
				$numessage = NULL;
			}
			if($fromuser === NULL || $touser === NULL) {
				/** @noinspection PhpPossiblePolymorphicInvocationInspection */
				$renderer->mu_error($oumessage . '<br />' . $numessage);
				break; // break execution for error
			}

			// Merge the users
			$log = array();
			$success = TRUE;
			/** @noinspection PhpUnhandledExceptionInspection */
			list($success, $log, $logid) = $mut->merge($touser->id, $fromuser->id);

			// reset mut session
			$SESSION->mut = NULL;

			// render results page
			/** @noinspection PhpPossiblePolymorphicInvocationInspection */
			echo $renderer->results_page($touser, $fromuser, $success, $log, $logid);
			break;

		// we have both users to merge selected, but we want to change any of them.
		case 'searchusers':
			/** @noinspection PhpPossiblePolymorphicInvocationInspection */
			echo $renderer->index_page($mergeuserform, $renderer::INDEX_PAGE_SEARCH_STEP);
			break;

		// we have both users to merge selected, and in the search step, we
		// want to proceed with the merging of the currently selected users.
		case 'continueselection':
			/** @noinspection PhpPossiblePolymorphicInvocationInspection */
			echo $renderer->index_page($mergeuserform, $renderer::INDEX_PAGE_CONFIRMATION_STEP);
			break;

		// ops!
		default:
			/** @noinspection PhpUnhandledExceptionInspection */
			$renderer->mu_error(get_string('invalid_option', 'tool_mergeusers'));
			break;
	}
// Any submitted data?
} else if($data) {
	// If there is a search argument use this instead of advanced form
	if(!empty($data->searchgroup['searcharg'])) {

		/** @noinspection PhpUnhandledExceptionInspection */
		$search_users = $mus->search_users($data->searchgroup['searcharg'], $data->searchgroup['searchfield']);
		/** @noinspection PhpParamsInspection */
		/** @noinspection PhpUnhandledExceptionInspection */
		$user_select_table = new UserSelectTable($search_users, $renderer);

		/** @noinspection PhpPossiblePolymorphicInvocationInspection */
		echo $renderer->index_page($mergeuserform, $renderer::INDEX_PAGE_SEARCH_AND_SELECT_STEP, $user_select_table);

		// only run this step if there are both a new and old userids
	} else if(!empty($data->oldusergroup['olduserid']) && !empty($data->newusergroup['newuserid'])) {
		//get and verify the userids from the selection form using the verify_user function (second field is column)
		/** @noinspection PhpUnhandledExceptionInspection */
		list($olduser, $oumessage) = $mus->verify_user($data->oldusergroup['olduserid'], $data->oldusergroup['olduseridtype']);
		/** @noinspection PhpUnhandledExceptionInspection */
		list($newuser, $numessage) = $mus->verify_user($data->newusergroup['newuserid'], $data->newusergroup['newuseridtype']);

		if($olduser === NULL || $newuser === NULL) {
			/** @noinspection PhpPossiblePolymorphicInvocationInspection */
			$renderer->mu_error($oumessage . '<br />' . $numessage);
			exit(); // end execution for error
		}
		// Add users to session for review step
		if(empty($SESSION->mut)) {
			$SESSION->mut = new stdClass();
		}
		$SESSION->mut->olduser = $olduser;
		$SESSION->mut->newuser = $newuser;

		/** @noinspection PhpPossiblePolymorphicInvocationInspection */
		echo $renderer->index_page($mergeuserform, $renderer::INDEX_PAGE_SEARCH_AND_SELECT_STEP);
	} else {
		// simply show search form.
		/** @noinspection PhpPossiblePolymorphicInvocationInspection */
		echo $renderer->index_page($mergeuserform, $renderer::INDEX_PAGE_SEARCH_STEP);
	}
} else {
	// no form submitted data
	/** @noinspection PhpPossiblePolymorphicInvocationInspection */
	echo $renderer->index_page($mergeuserform, $renderer::INDEX_PAGE_SEARCH_STEP);
}

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
 * @package tool
 * @subpackage mergeusers
 * @author Jordi Pujol-Ahulló <jordi.pujol@urv.cat>
 * @copyright 2013 Servei de Recursos Educatius (http://www.sre.urv.cat)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once __DIR__ . '/autoload.php';

class Merger
{
	/**
	 * @var MergeUserTool instance of the tool.
	 */
	protected $mut;
	protected $logger;

	/**
	 * Initializes the MergeUserTool to process any incoming merging action through
	 * any Gathering instance.
	 *
	 * @param MergeUserTool $mut
	 */
	public function __construct(MergeUserTool $mut)
	{
		$this->mut = $mut;
		$this->logger = new tool_mergeusers_logger();

		// to catch Ctrl+C interruptions, we need this stuff.
		declare(ticks=1);

		if(extension_loaded('pcntl')) {
			pcntl_signal(SIGINT, [$this, 'aborting']);
		}
	}

	/**
	 * Called when aborting from command-line on Ctrl+C interruption.
	 *
	 * @param int $signo only SIGINT.
	 *
	 * @throws coding_exception
	 * @noinspection PhpMissingParamTypeInspection
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function aborting($signo)
	{
		if(defined("CLI_SCRIPT")) {
			echo "\n\n" . get_string('ok') . ", exit!\n\n";
		}
		exit(0); //quiting normally after all ;-)
	}

	/**
	 * This iterates over all merging actions from the given Gathering instance and tries to
	 * perform it. The result of every action is logged internally for future checking.
	 *
	 * @param Gathering $gathering List of merging actions.
	 *
	 * @throws coding_exception
	 * @throws dml_exception
	 * @throws dml_exception
	 * @noinspection PhpUnusedLocalVariableInspection
	 */
	public function merge(Gathering $gathering)
	{
		foreach($gathering as $action){
			list($success, $log, $id) = $this->mut->merge($action->toid, $action->fromid);

			// only shows results on cli script
			if(defined("CLI_SCRIPT")) {
				echo (($success) ? get_string("success") : get_string("error")) . ". Log id: " . $id . "\n\n";
			}
		}
		if(defined("CLI_SCRIPT")) {
            echo get_string('ok') .", exit!\n\n";
        }
    }
}

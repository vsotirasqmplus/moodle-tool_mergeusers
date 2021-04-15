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
 * @author     Vasileios Sotiras <v.sotiras@qmul.ac.uk>
 * @copyright  2021 Queen Mary University of London (http://qmul.ac.uk)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/*
 * This is the extended requirement for QM
 * Following some tests, I found out in order to add elements to the defaults
 * We need to add them with index numbers higher than the maximum default index
 * Otherwise the first elements here take zero as index and replace the defaults
 */
return [
        // Database tables to be excluded from normal processing.
        // You normally will add tables. Be very cautious if you delete any of them.
        'exceptions' => [
                4 => 'plagiarism_turnitin_users'
        ],
];

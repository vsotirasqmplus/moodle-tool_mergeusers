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
 * Version information
 *
 * @package    tool
 * @subpackage mergeusers
 * @author     Andrew Hancox <andrewdchancox@googlemail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_mergeusers_enrolments_testcase extends advanced_testcase {
    /**
     * Setup the test.
     *
     * @noinspection PhpIncludeInspection
     */
    public function setUp() {
        global $CFG;
        include_once "$CFG->dirroot/admin/tool/mergeusers/lib/mergeusertool.php";
        $this->resetAfterTest(true);
    }

    /**
     * Enrol two users on one unique course each and one shared course
     * then merge them.
     *
     * @group        tool_mergeusers
     * @group        tool_mergeusers_enrolments
     * @noinspection PhpUndefinedMethodInspection
     * @throws       coding_exception
     * @throws       dml_exception
     * @throws       dml_transaction_exception
     * @throws       moodle_exception
     * @throws       moodle_exception
     */
    public function test_mergeenrolments() {
        global $DB;

        // Setup two users to merge.
        $user_remove = $this->getDataGenerator()->create_user();
        $user_keep = $this->getDataGenerator()->create_user();

        // Create three courses.
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $course3 = $this->getDataGenerator()->create_course();

        $maninstance1 = $DB->get_record(
                'enrol',
                ['courseid' => $course1->id, 'enrol' => 'manual'],
                '*',
                MUST_EXIST
        );
        $maninstance2 = $DB->get_record(
                'enrol',
                ['courseid' => $course2->id, 'enrol' => 'manual'],
                '*',
                MUST_EXIST
        );
        $maninstance3 = $DB->get_record(
                'enrol',
                ['courseid' => $course3->id, 'enrol' => 'manual'],
                '*',
                MUST_EXIST
        );

        $manual = enrol_get_plugin('manual');

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);

        // Enrol $user_remove on course 1 + 2 and $user_keep on course 2 + 3.
        $manual->enrol_user($maninstance1, $user_remove->id, $studentrole->id);
        $manual->enrol_user($maninstance2, $user_remove->id, $studentrole->id);
        $manual->enrol_user($maninstance2, $user_keep->id, $studentrole->id);
        $manual->enrol_user($maninstance3, $user_keep->id, $studentrole->id);

        // Check initial state of enrolments for $user_remove.
        $courses = enrol_get_all_users_courses($user_remove->id);
        if (!is_array($courses)) {
            $courses = [$courses];
        }
        ksort($courses);
        $this->assertCount(2, $courses);
        $this->assertEquals([$course1->id, $course2->id], array_keys($courses));

        // Check initial state of enrolments for $user_keep.
        $courses = enrol_get_all_users_courses($user_keep->id);
        ksort($courses);
        $this->assertCount(2, $courses);
        $this->assertEquals([$course2->id, $course3->id], array_keys($courses));

        $mut = new MergeUserTool();
        $mut->merge($user_keep->id, $user_remove->id);

        // Check $user_remove is suspended.
        $user_remove = $DB->get_record('user', ['id' => $user_remove->id]);
        $this->assertEquals(1, $user_remove->suspended);

        // Check $user_keep is now enrolled on all three courses.
        $courses = enrol_get_all_users_courses($user_keep->id);
        ksort($courses);
        $this->assertCount(3, $courses);
        $this->assertEquals([$course1->id, $course2->id, $course3->id], array_keys($courses));
    }
}

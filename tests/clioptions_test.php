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
class tool_mergeusers_clioptions_testcase extends advanced_testcase {

    /**
     * @noinspection PhpIncludeInspection
     */
    public function setUp() {
        global $CFG;
        include_once("$CFG->dirroot/admin/tool/mergeusers/lib/mergeusertool.php");
        $this->resetAfterTest();
    }

    public function tearDown() {
        $config = tool_mergeusers_config::instance();
        unset($config->alwaysRollback);
        unset($config->debugdb);
    }

    /**
     * Test option to always rollback merges.
     *
     * @group        tool_mergeusers
     * @group        tool_mergeusers_clioptions
     * @noinspection PhpUndefinedMethodInspection
     * @throws       coding_exception
     * @throws       dml_exception
     * @throws       dml_transaction_exception
     * @throws       moodle_exception|ReflectionException
     */
    public function test_alwaysrollback() {
        global $DB;

        // Setup two users to merge.
        $userremove = $this->getDataGenerator()->create_user();
        $userkeep = $this->getDataGenerator()->create_user();

        $mut = new MergeUserTool();
        $mut->merge($userkeep->id, $userremove->id);

        // Check $user_remove is suspended.
        $userremove = $DB->get_record('user', ['id' => $userremove->id]);
        $this->assertEquals(1, $userremove->suspended);

        $userkeep = $DB->get_record('user', ['id' => $userkeep->id]);
        $this->assertEquals(0, $userkeep->suspended);

        $userremove2 = $this->getDataGenerator()->create_user();

        $config = tool_mergeusers_config::instance();
        // 0 /** @noinspection PhpUndefinedFieldInspection */.
        $config->alwaysRollback = true;

        $mut = new MergeUserTool($config);

        $this->expectException('Exception');
        $this->expectExceptionMessage('alwaysRollback option is set so rolling back transaction');
        $mut->merge($userkeep->id, $userremove2->id);
    }

    /**
     * Test option to always rollback merges.
     *
     * @group        tool_mergeusers
     * @group        tool_mergeusers_clioptions
     * @noinspection PhpUndefinedMethodInspection
     * @throws       coding_exception
     * @throws       dml_exception
     * @throws       dml_transaction_exception
     * @throws       moodle_exception|ReflectionException
     */
    public function test_debugdb() {
        global $DB;

        // Setup two users to merge.
        $userremove = $this->getDataGenerator()->create_user();
        $userkeep = $this->getDataGenerator()->create_user();

        $mut = new MergeUserTool();
        $mut->merge($userkeep->id, $userremove->id);
        $this->assertFalse($this->hasOutput());

        // Check $user_remove is suspended.
        $userremove = $DB->get_record('user', ['id' => $userremove->id]);
        $this->assertEquals(1, $userremove->suspended);

        $userkeep = $DB->get_record('user', ['id' => $userkeep->id]);
        $this->assertEquals(0, $userkeep->suspended);

        $userremove2 = $this->getDataGenerator()->create_user();

        $config = tool_mergeusers_config::instance();
        // 0 /** @noinspection PhpUndefinedFieldInspection */.
        $config->debugdb = true;

        $mut = new MergeUserTool($config);

        $mut->merge($userkeep->id, $userremove2->id);

        $this->expectOutputRegex('/Query took/');
    }
}

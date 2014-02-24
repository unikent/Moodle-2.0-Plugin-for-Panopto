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
 * PHPUnit data generator tests
 *
 * @package    block_panopto
 * @copyright  2014 University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * PHPUnit testcase
 *
 * @package    block_panopto
 * @copyright  2014 University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_panopto_panopto_testcase extends advanced_testcase {
	/**
	 * Test the observer.
	 */
    public function test_observer() {
        global $DB;

        $this->resetAfterTest(true);

        $c1 = $this->getDataGenerator()->create_course();
        $c1context = context_course::instance($c1->id);

        $c2 = $this->getDataGenerator()->create_course();
        $c2context = context_course::instance($c2->id);

        // Add a Panopto block to the first course, but not the second one.
        $DB->insert_record('block_instances', array(
        	'blockname' => 'panopto',
        	'parentcontextid' => $c1context->id,
        	'showinsubcontexts' => '0',
        	'pagetypepattern' => 'course-view-*',
        	'defaultregion' => 'side-post',
        	'defaultweight' => '0'
        ));

        $this->assertFalse($DB->record_exists('panopto_course_update_list', array("courseid" => $c1->id)));
        $this->assertFalse($DB->record_exists('panopto_course_update_list', array("courseid" => $c2->id)));

        // Enroll users on both courses.
        $user = $this->getDataGenerator()->create_user();

        // Setup.
        $role = $DB->get_record('role', array('shortname' => 'student'));
        $plugin = enrol_get_plugin('manual');

        // Enroll one.
        $instance = $DB->get_record('enrol', array(
            'courseid' => $c1->id,
            'enrol' => 'manual',
        ));
        $plugin->enrol_user($instance, $user->id, $role->id);

        // Check only c1 was added to the queue.
        $this->assertTrue($DB->record_exists('panopto_course_update_list', array("courseid" => $c1->id)));
        $this->assertFalse($DB->record_exists('panopto_course_update_list', array("courseid" => $c2->id)));

        // Enroll two.
        $instance = $DB->get_record('enrol', array(
            'courseid' => $c2->id,
            'enrol' => 'manual',
        ));
        $plugin->enrol_user($instance, $user->id, $role->id);

        // Check only c1 was added to the queue (again).
        $this->assertTrue($DB->record_exists('panopto_course_update_list', array("courseid" => $c1->id)));
        $this->assertFalse($DB->record_exists('panopto_course_update_list', array("courseid" => $c2->id)));
    }
}
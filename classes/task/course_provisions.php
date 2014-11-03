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
 * Panopto
 *
 * @package    block_panopto
 * @copyright  2014 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_panopto\task;

/**
 * Panopto Cron
 */
class course_provisions extends \core\task\scheduled_task
{
    public function get_name() {
        return "Panopto Course Provisioning";
    }

    public function execute() {
        global $DB;

        require_once(dirname(__FILE__) . '/../../lib/panopto_data.php');

        $data = new \panopto_data(null);

        if (empty($data->servername) || empty($data->instancename) || empty($data->applicationkey)) {
            return;
        }

        // Grab 25 updates.
        $ids = array();
        $rs = $DB->get_recordset('panopto_course_update_list', null, '', '*', 0, 25);
        foreach ($rs as $rec) {
            $ids[] = $rec->courseid;
            $data->moodle_course_id = $rec->courseid;

            // Does it still exist?
            if (!$DB->record_exists('course', array('id' => $rec->courseid))) {
                continue;
            }

            // Try to provision.
            $info = $data->get_provisioning_info();

            // Provision the course.
            $data->provision_course($info);
        }

        // Clear out the DB.
        if (!empty($ids)) {
            $DB->delete_records_list("panopto_course_update_list", "courseid", $ids);
        }

        return;
    }
}

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

namespace block_panopto\task;

/**
 * Global panopto re-sync.
 */
class global_sync extends \core\task\scheduled_task
{
    public function get_name() {
        return "Panopto Global Sync";
    }

    /**
     * Sync all courses.
     */
    public function execute() {
        global $DB;

        require_once(dirname(__FILE__) . '/../../lib/panopto_data.php');

        $records = $DB->get_records_sql('SELECT DISTINCT moodleid as id FROM {block_panopto_foldermap}');
        $count = count($records);
        foreach ($records as $course) {
            $panoptodata = new \panopto_data($course->id);
            if (empty($panoptodata->servername)) {
                continue;
            }

            // Provision the course.
            $provisioningdata = $panoptodata->get_provisioning_info();
            $panoptodata->provision_course($provisioningdata);
        }

        return true;
    }
}

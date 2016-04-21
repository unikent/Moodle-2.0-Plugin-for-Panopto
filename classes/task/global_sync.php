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

        $records = $DB->get_records_sql('SELECT moodleid as id FROM {block_panopto_foldermap} GROUP BY moodleid');
        $count = count($records);
        foreach ($records as $course) {
            $task = new \block_panopto\task\course_sync();
            $task->set_custom_data(array(
                'courseid' => $course->id
            ));

            \tool_adhoc\manager::queue_adhoc_task($task, 1024, 600, rand(1, $count * 5));
        }

        return true;
    }
}

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
 * Upgrade the Panopto block
 *
 * @global moodle_database $DB
 * @param int $oldversion
 * @param object $block
 */
function xmldb_block_panopto_upgrade($oldversion, $block) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2013122001) {
        // Define table panopto_course_update_list to be created.
        $table = new xmldb_table('panopto_course_update_list');

        // Adding fields to table panopto_course_update_list.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table panopto_course_update_list.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table panopto_course_update_list.
        $table->add_index('courseid_key', XMLDB_INDEX_UNIQUE, array('courseid'));

        // Conditionally launch create table for panopto_course_update_list.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Panopto savepoint reached.
        upgrade_block_savepoint(true, 2013122001, 'panopto');
    }

    if ($oldversion < 2014030400) {
        // Define field master to be added to block_panopto_foldermap.
        $table = new xmldb_table('block_panopto_foldermap');

        // Add the master field.
        $field = new xmldb_field('master', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'panopto_id');

        // Conditionally launch add field master.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add an index on panopto ID.
        $index = new xmldb_index('mpfm_pid', XMLDB_INDEX_NOTUNIQUE, array('panopto_id'));

        // Conditionally launch add index mpfm_pid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Set a single master for every currently mirrored course.
        $courses = $DB->get_records('block_panopto_foldermap');
        $map = array();
        foreach ($courses as $course) {
            if (isset($map[$course->panopto_id])) {
                $DB->set_field('block_panopto_foldermap', 'master', 0, array(
                    'id' => $course->id
                ));
                continue;
            }

            $map[$course->panopto_id] = $course;
        }

        // Panopto savepoint reached.
        upgrade_block_savepoint(true, 2014030400, 'panopto');
    }

    return true;
}


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
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2013122001) {
        // Define table block_panopto_updates to be created.
        $table = new xmldb_table('block_panopto_updates');

        // Adding fields to table block_panopto_updates.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_panopto_updates.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table block_panopto_updates.
        $table->add_index('courseid_key', XMLDB_INDEX_UNIQUE, array('courseid'));

        // Conditionally launch create table for block_panopto_updates.
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

    if ($oldversion < 2014110300) {

        // Define table panopto_course_update_list to be renamed to block_panopto_updates.
        $table = new xmldb_table('panopto_course_update_list');

        // Launch rename table for panopto_course_update_list.
        $dbman->rename_table($table, 'block_panopto_updates');

        // Panopto savepoint reached.
        upgrade_block_savepoint(true, 2014110300, 'panopto');
    }

    // Skylar says blame Panopto.
    if ($oldversion < 2015012000) {
        $table = new xmldb_table('block_panopto_foldermap');

        // Define field panopto_server to be added to block_panopto_foldermap.
        $field = new xmldb_field('panopto_server', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null, 'panopto_id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->set_field('block_panopto_foldermap', 'panopto_server', $CFG->block_panopto_server_name, null);
        }

        // Define field panopto_app_key to be added to block_panopto_foldermap.
        $field = new xmldb_field('panopto_app_key', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null, 'panopto_server');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->set_field('block_panopto_foldermap', 'panopto_app_key', $CFG->block_panopto_application_key, null);
        }

        // Panopto savepoint reached.
        upgrade_block_savepoint(true, 2015012000, 'panopto');
    }


        if ($oldversion < 2015020300) {

                // Define field publisher_mapping to be added to block_panopto_foldermap.
        $table = new xmldb_table('block_panopto_foldermap');
        $field = new xmldb_field('publisher_mapping', XMLDB_TYPE_CHAR, '20', null, null, null, '1', 'panopto_app_key');

        // Conditionally launch add field publisher_mapping.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field creator_mapping to be added to block_panopto_foldermap.
        $table = new xmldb_table('block_panopto_foldermap');
        $field = new xmldb_field('creator_mapping', XMLDB_TYPE_CHAR, '20', null, null, null, '3,4', 'publisher_mapping');

        // Conditionally launch add field creator_mapping.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }


        // Panopto savepoint reached.
        upgrade_block_savepoint(true, 2015020300, 'panopto');
    }

    return true;
}


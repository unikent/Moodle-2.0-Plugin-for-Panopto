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
 * Scripts used for upgrading database when upgrading block from an older version
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2015 with contributions from Spenser Jones (sjones@ambrose.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_block_panopto_upgrade($oldversion = 0) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2014121502) {

        // Add db fields for servername and application key per course.
        if (isset($CFG->block_panopto_server_name)) {
            $oldservername = $CFG->block_panopto_server_name;
        }
        if (isset($CFG->block_panopto_application_key)) {
            $oldappkey = $CFG->block_panopto_application_key;
        }

        // Define field panopto_server to be added to block_panopto_foldermap.
        $table = new xmldb_table('block_panopto_foldermap');
        $field = new xmldb_field('panopto_server', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null, 'panopto_id');

        // Conditionally launch add field panopto_server.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            if (isset($oldservername)) {
                $DB->set_field('block_panopto_foldermap', 'panopto_server', $oldservername, null);
            }
        }

        // Define field panopto_app_key to be added to block_panopto_foldermap.
        $table = new xmldb_table('block_panopto_foldermap');
        $field = new xmldb_field('panopto_app_key', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null, 'panopto_server');

        // Conditionally launch add field panopto_app_key.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            if (isset($oldappkey)) {
                $DB->set_field('block_panopto_foldermap', 'panopto_app_key', $oldappkey, null);
            }
        }

        // Panopto savepoint reached.
        upgrade_block_savepoint(true, 2014121502, 'panopto');
    }

    if ($oldversion < 2015012901) {

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
        upgrade_block_savepoint(true, 2015012901, 'panopto');
    }

    if ($oldversion < 2015070800) {
        // Grab all courses with a Panopto block.
        $courses = $DB->get_records_sql("SELECT DISTINCT moodleid as id FROM {block_panopto_foldermap}");

        foreach ($courses as $course) {
            $panoptodata = new \panopto_data($course->id);
            $panoptodata->default_provision();
        }

        // Panopto savepoint reached.
        upgrade_block_savepoint(true, 2015070800, 'panopto');
    }

    if ($oldversion < 2015090700) {
        // Define table block_panopto_eula to be created.
        $table = new xmldb_table('block_panopto_eula');

        // Adding fields to table block_panopto_eula.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
        $table->add_field('version', XMLDB_TYPE_INTEGER, '3', null, null, null, '1');

        // Adding keys to table block_panopto_eula.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('k_userid', XMLDB_KEY_UNIQUE, array('userid'));

        // Conditionally launch create table for block_panopto_eula.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Panopto savepoint reached.
        upgrade_block_savepoint(true, 2015090700, 'panopto');
    }

    if ($oldversion < 2015090800) {
        $ar = $DB->get_field('role', 'id', array(
            'shortname' => 'panopto_academic'
        ));

        $nar = $DB->get_field('role', 'id', array(
            'shortname' => 'panopto_non_academic'
        ));

        // Upgrade roles.
        $upgradeset = array();
        list($sql, $params) = $DB->get_in_or_equal(array($ar, $nar));
        $ras = $DB->get_records_select('role_assignments', 'roleid ' . $sql, $params);
        foreach ($ras as $ra) {
            $upgradeset[] = array(
                'userid' => $ra->userid,
                'version' => $ra->roleid == $ar ? \block_panopto\eula::VERSION_ACADEMIC : \block_panopto\eula::VERSION_NON_ACADEMIC
            );
        }

        $DB->insert_records('block_panopto_eula', $upgradeset);

        // Delete the old roles.
        delete_role($ar);
        delete_role($nar);

        // Panopto savepoint reached.
        upgrade_block_savepoint(true, 2015090800, 'panopto');
    }

    return true;
}

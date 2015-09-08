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
 * Panopto Block
 *
 * @package    block_panopto
 * @category   block
 * @copyright  University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_panopto;

defined('MOODLE_INTERNAL') || die();

/**
 * Panopto utils
 *
 * @package    block_panopto
 * @copyright  University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class util {
    /**
     * Ensures a role is created, then returns it.
     */
    public static function get_role($shortname) {
        global $DB, $CFG;

        static $map = array(
            "panopto_creator" => array(
                "Course Creator (Panopto)",
                "panopto_creator",
                "Panopto Course Creator",
                "user"
            )
        );

        if (!isset($map[$shortname])) {
            throw new \moodle_exception("Invalid Panopto role '$shortname'!");
        }

        // Create this if it doesnt exist.
        if (!$DB->record_exists('role', array('shortname' => $shortname))) {
            require_once($CFG->libdir . "/accesslib.php");
            call_user_func_array("create_role", $map[$shortname]);
        }

        return $DB->get_record('role', array(
            'shortname' => $shortname
        ));
    }
}

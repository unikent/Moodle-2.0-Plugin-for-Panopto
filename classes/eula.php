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
 * Panopto block.
 *
 * @package    block_panopto
 * @category   block
 * @copyright  University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_panopto;

require_once(dirname(__FILE__) . '/../lib/panopto_data.php');

/**
 * User interface for Panopto.
 * Performs EULA checks.
 *
 * @package    block_panopto
 * @category   block
 * @copyright  University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class eula
{
    const VERSION_ACADEMIC = 1;
    const VERSION_NON_ACADEMIC = 2;

    /**
     * Has the given user signed the EULA?
     */
    public static function has_signed($userid = null, $version = null) {
        global $DB, $USER;

        if ($userid == null) {
            $userid = $USER->id;
        }

        $params = array("userid" => $userid);
        if ($version != null) {
            $params['version'] = $version;
        }

        return $DB->record_exists('block_panopto_eula', $params);
    }

    /**
     * Sign the agreement.
     */
    public static function sign($courseid, $userid, $version = null) {
        global $DB;

        $params = array("userid" => $userid);

        // Do we have an existing signature?
        $record = $DB->get_record('block_panopto_eula', $params);
        if ($record) {
            $record->version = $version;
            return $DB->update_record('block_panopto_eula', $record);
        }

        if ($version != null) {
            $params['version'] = $version;
        }

        if ($DB->insert_record('block_panopto_eula', $params, false)) {
            $user = $DB->get_record('user', ['id' => $userid]);

            // Create the unlisted folder.
            $panoptodata = new \panopto_data($courseid);
            $panoptodata->provision_user_folder($user);

            return true;
        }

        return false;
    }
}
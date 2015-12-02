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
 * @package block_panopto
 * @copyright  Panopto 2009 - 2015 with contributions from Spenser Jones (sjones@ambrose.edu) and by Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_panopto\task;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/../../lib/panopto_data.php');

/**
 * Panopto "update user" task.
 */
class update_user extends \core\task\adhoc_task {

    public function get_component() {
        return 'block_panopto';
    }

    public function execute() {
        $eventdata = (array) $this->get_custom_data();

        $panopto = new \panopto_data($eventdata['courseid']);
        if (empty($panopto->servername)) {
            // Course not provisioned yet!
            return;
        }

        $enrolmentinfo = $this->get_info_for_enrolment_change($panopto, $eventdata['relateduserid'], $eventdata['contextid']);

        // Kent change.
        if ($enrolmentinfo['role'] != 'Viewer' && $eventdata['eventtype'] != 'enrol_remove' && !\block_panopto\eula::has_signed($eventdata['relateduserid'])) {
            return;
        }
        // End Kent change.

        switch ($eventdata['eventtype']) {
            case 'enrol_add':
                $panopto->add_course_user($enrolmentinfo['role'], $enrolmentinfo['userkey']);
                break;

            case 'enrol_remove':
                // Better be sure..
                if (!$enrolmentinfo['role']) {
                    $panopto->remove_course_user("Creator/Publisher", $enrolmentinfo['userkey']);
                    $panopto->remove_course_user("Viewer", $enrolmentinfo['userkey']);
                } else {
                    $panopto->change_user_role($enrolmentinfo['role'], $enrolmentinfo['userkey']);
                }
                break;

            case 'role':
                $panopto->change_user_role($enrolmentinfo['role'], $enrolmentinfo['userkey']);
                break;
        }
    }

    /**
     * Return the correct role for a user, given a context.
     */
    private function get_role_from_context($contextid, $userid) {
        $context = \context::instance_by_id($contextid);

        if (has_capability('block/panopto:provision_aspublisher', $context, $userid)) {
            if (has_capability('block/panopto:provision_asteacher', $context, $userid)) {
                return "Creator/Publisher";
            } else {
                return "Publisher";
            }
        } else if (has_capability('block/panopto:provision_asteacher', $context, $userid)) {
            return "Creator";
        } else if (is_enrolled($context, $userid)) {
            return "Viewer";
        }

        return null;
    }

    /**
     * Return user info for this event.
     */
    private function get_info_for_enrolment_change($panopto, $relateduserid, $contextid) {
        global $DB;

        // DB userkey is "[instancename]\\[username]". Get username and use it to create key.
        $user = get_complete_user_data('id', $relateduserid);
        $username = $user->username;
        $userkey = $panopto->panopto_decorate_username($username);

        // Get contextID to determine user's role.
        $role = $this->get_role_from_context($contextid, $relateduserid);

        return array(
            "role" => $role,
            "userkey" => $userkey
        );
    }

}

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
 * @copyright  Panopto 2009 - 2015 /With contributions from Spenser Jones (sjones@ambrose.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
if (empty($CFG)) {
    require_once("../../config.php");
}
require_once($CFG->libdir . '/dmllib.php');
require_once("block_panopto_lib.php");
require_once("panopto_soap_client.php");

/**
 * Panopto data object. Contains info required for provisioning a course with Panopto.
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2015
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class panopto_data {

    public $instancename;
    public $moodlecourseid;
    public $servername;
    public $applicationkey;
    public $soapclient;
    public $sessiongroupid;
    public $uname;

    public function __construct($moodlecourseid) {
        global $USER, $CFG;

        // Fetch global settings from DB.
        $this->instancename = $CFG->block_panopto_instance_name;

        // Get servername and application key specific to moodle course if ID is specified.
        if (isset($moodlecourseid)) {
            $this->servername = self::get_panopto_servername($moodlecourseid);
            $this->applicationkey = self::get_panopto_app_key($moodlecourseid);
        }

        // Fetch current Panopto course mapping if we have a Moodle course ID.
        // Course will be null initially for batch-provisioning case.
        if (!empty($moodlecourseid)) {
            $this->moodlecourseid = $moodlecourseid;
            $this->sessiongroupid = self::get_panopto_course_id($moodlecourseid);
        }
    }

    /**
     * Kent addition.
     */
    public function default_provision() {
        global $CFG;

        for ($x = 0; $x < 10; $x++) {
            $cfgservername = 'block_panopto_server_name' . ($x + 1);
            $cfgappkey = 'block_panopto_application_key' . ($x + 1);

            if (!empty($CFG->$cfgservername) && !empty($CFG->$cfgappkey)) {
                $this->servername = $CFG->$cfgservername;
                $this->applicationkey = $CFG->$cfgappkey;

                $provisioningdata = $this->get_provisioning_info();
                return $this->provision_course($provisioningdata);
            }
        }
    }

    /**
     * Returns SystemInfo.
     */
    public function get_system_info() {
        
        // If no soap client for this instance, instantiate one.
        if (!isset($this->soapclient)) {
            $this->soapclient = self::instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
        }

        return $this->soapclient->get_system_info();
    }

    /**
     * Create the Panopto course and populate its ACLs.
     */
    public function provision_course($provisioninginfo) {
        global $DB;

        // If no soap client for this instance, instantiate one.
        if (!isset($this->soapclient)) {
            $this->soapclient = self::instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
        }

        $courseinfo = $this->soapclient->provision_course($provisioninginfo);

        // Kent Change.
        $instructors = array();
        if (isset($provisioninginfo->Publishers)) {
            $instructors = array_merge($instructors, $provisioninginfo->Publishers);
        }

        if (isset($provisioninginfo->Instructors)) {
            $instructors = array_merge($instructors, $provisioninginfo->Instructors);
        }

        foreach ($instructors as $instructor) {
            $this->provision_user_folder($instructor->UserKey);
        }
        // End Kent Change.

        if (!empty($courseinfo) && !empty($courseinfo->PublicID)) {
            self::set_panopto_course_id($this->moodlecourseid, $courseinfo->PublicID);
            self::set_panopto_server_name($this->moodlecourseid, $this->servername);
            self::set_panopto_app_key($this->moodlecourseid, $this->applicationkey);
            
            //If old role mappings exists, do not remap. Otherwise, set role mappings to defaults
            $mappings = self::get_course_role_mappings($this->moodlecourseid);
            if (empty($mappings['creator']) && empty($mappings['publisher'])) {
                self::set_course_role_mappings($this->moodlecourseid, array('1'), array('3','4'));
            }
        }

        return $courseinfo;
    }

    /**
     * Provision one user folder.
     * Kent change.
     */
    public function provision_user_folder($fullkey) {
        global $DB;

        $userkey = explode("\\", $fullkey);
        $user = $DB->get_record('user', array(
            'username' => $userkey[1]
        ));

        if (!$user) {
            debugging("Couldn't find user: $userkey! ($fullkey)");
            return;
        }

        $instructor = new \stdClass();
        $instructor->UserKey = $fullkey;
        $instructor->FirstName = $user->firstname;
        $instructor->LastName = $user->lastname;
        $instructor->Email = $user->email;

        $folder = new \stdClass();
        $folder->ShortName = '';
        $folder->LongName = $user->username . "'s unlisted recordings";
        $folder->ExternalCourseID = $this->instancename . ":" . $user->username;
        $folder->Instructors = array($instructor);

        $this->soapclient->provision_course($folder);
    }

    /**
     *  Fetch course name and membership info from DB in preparation for provisioning operation.
     */
    public function get_provisioning_info() {
        global $DB;

        // Kent Change
        $provisioninginfo = new \stdClass();
        $parole = \block_panopto\util::get_role('panopto_academic');
        $panrole = \block_panopto\util::get_role('panopto_non_academic');
        // End Change

        $provisioninginfo->ShortName = $DB->get_field('course', 'shortname', array('id' => $this->moodlecourseid));
        $provisioninginfo->LongName = $DB->get_field('course', 'fullname', array('id' => $this->moodlecourseid));
        $provisioninginfo->ExternalCourseID = $this->instancename . ":" . $this->moodlecourseid;
        $provisioninginfo->Server = $this->servername;
        $coursecontext = context_course::instance($this->moodlecourseid, MUST_EXIST);

        // Lookup table to avoid adding instructors as Viewers as well as Creators.
        $publisherhash = array();
        $instructorhash = array();

        $publishers = get_users_by_capability($coursecontext, 'block/panopto:provision_aspublisher');

        if (!empty($publishers)) {
            $provisioninginfo->Publishers = array();
            foreach ($publishers as $publisher) {
                // Kent change.
                if (!user_has_role_assignment($publisher->id, $parole->id) && !user_has_role_assignment($publisher->id, $panrole->id)) {
                    continue;
                }
                // End Kent change.

                $publisherinfo = new stdClass;
                $publisherinfo->UserKey = $this->panopto_decorate_username($publisher->username);
                $publisherinfo->FirstName = $publisher->firstname;
                $publisherinfo->LastName = $publisher->lastname;
                $publisherinfo->Email = $publisher->email;

                array_push($provisioninginfo->Publishers, $publisherinfo);

                $publisherhash[$publisher->username] = true;
            }
        }

        // moodle/course:update capability will include admins along with teachers, course creators, etc.
        // Could also use moodle/legacy:teacher, moodle/legacy:editingteacher, etc. if those turn out to be more appropriate.
        // File edited - new capability added to access.php to identify instructors without including all site admins etc.
        // New capability used to identify instructors for provisioning.
        $instructors = get_users_by_capability($coursecontext, 'block/panopto:provision_asteacher');

        if (!empty($instructors)) {
            $provisioninginfo->Instructors = array();
            foreach ($instructors as $instructor) {
                // Kent change.
                if (!user_has_role_assignment($instructor->id, $parole->id) && !user_has_role_assignment($instructor->id, $panrole->id)) {
                    continue;
                }
                // End Kent change.

                $instructorinfo = new stdClass;
                $instructorinfo->UserKey = $this->panopto_decorate_username($instructor->username);
                $instructorinfo->FirstName = $instructor->firstname;
                $instructorinfo->LastName = $instructor->lastname;
                $instructorinfo->Email = $instructor->email;

                array_push($provisioninginfo->Instructors, $instructorinfo);

                $instructorhash[$instructor->username] = true;
            }
        }

        /*
         * Give all enrolled users at least student-level access. Instructors will be filtered out below.
         * Use get_enrolled_users because, as of Moodle 2.0, capability moodle/course:view no longer corresponds to a participant list.
         */
        // Kent Change
        $students = get_users_by_capability($coursecontext, 'block/panopto:panoptoviewer');
        // End Change

        if (!empty($students)) {
            $provisioninginfo->Students = array();
            foreach ($students as $student) {
                if (array_key_exists($student->username, $instructorhash)) {
                    continue;
                }
                if (array_key_exists($student->username, $publisherhash)) {
                    continue;
                }
                $studentinfo = new stdClass;
                $studentinfo->UserKey = $this->panopto_decorate_username($student->username);
                $studentinfo->FirstName = $student->firstname;
                $studentinfo->LastName = $student->lastname;
                $studentinfo->Email = $student->email;

                array_push($provisioninginfo->Students, $studentinfo);
            }
        }

        return $provisioninginfo;
    }

    /**
     *  Get courses visible to the current user.
     */
    public function get_courses() {
        if (!isset($this->soapclient)) {
            $this->soapclient = self::instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
        }

        $coursesresult = $this->soapclient->get_courses();
        $courses = array();
        if (!empty($coursesresult->CourseInfo)) {
            $courses = $coursesresult->CourseInfo;

            // Single-element return set comes back as scalar, not array (?).
            if (!is_array($courses)) {
                $courses = array($courses);
            }
        }

        return $courses;
    }

    /**
     * Get info about the currently mapped course.
     */
    public function get_course() {
        // If no soap client for this instance, instantiate one.
        if (!isset($this->soapclient)) {
            $this->soapclient = self::instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
        }

        return $this->soapclient->get_course($this->sessiongroupid);
    }

    /**
     * Get ongoing Panopto sessions for the currently mapped course.
     */
    public function get_live_sessions() {
        $livesessionsresult = $this->soapclient->get_live_sessions($this->sessiongroupid);

        $livesessions = array();
        if (!empty($livesessionsresult->SessionInfo)) {
            $livesessions = $livesessionsresult->SessionInfo;
            
            // Single-element return set comes back as scalar, not array (?).
            if (!is_array($livesessions)) {
                $livesessions = array($livesessions);
            }
        }

        return $livesessions;
    }

    /**
     * Get recordings available to view for the currently mapped course.
     */
    public function get_completed_deliveries() {
        $completeddeliveriesresult = $this->soapclient->get_completed_deliveries($this->sessiongroupid);

        $completeddeliveries = array();
        if (!empty($completeddeliveriesresult->DeliveryInfo)) {
            $completeddeliveries = $completeddeliveriesresult->DeliveryInfo;
            
            
            // Single-element return set comes back as scalar, not array (?)
            if (!is_array($completeddeliveries)) {
                $completeddeliveries = array($completeddeliveries);
            }
        }

        return $completeddeliveries;
    }

    /**
     * Instance method caches Moodle instance name from DB (vs. block_panopto_lib version).
     */
    public function panopto_decorate_username($moodleusername) {
        return ($this->instancename . "\\" . $moodleusername);
    }

    /**
     * We need to retrieve the current course mapping in the constructor, so this must be static.
     */
    public static function get_panopto_course_id($moodlecourseid) {
        global $DB;
        return $DB->get_field('block_panopto_foldermap', 'panopto_id', array('moodleid' => $moodlecourseid));
    }

    /**
     *  Retrieve the servername for the current course
     */
    public static function get_panopto_servername($moodlecourseid) {
        global $DB;
        return $DB->get_field('block_panopto_foldermap', 'panopto_server', array('moodleid' => $moodlecourseid));
    }

    /**
     *  Retrieve the app key for the current course
     */
    public static function get_panopto_app_key($moodlecourseid) {
        global $DB;
        return $DB->get_field('block_panopto_foldermap', 'panopto_app_key', array('moodleid' => $moodlecourseid));
    }

    /**
     * Get the current role mappings set for the current course from the db.
     */
    public static function get_course_role_mappings($moodlecourseid) {
        global $DB;
        
        // Get publisher roles as string and explode to array.
        $pubrolesraw = $DB->get_field('block_panopto_foldermap', 'publisher_mapping', array('moodleid' => $moodlecourseid));
        $pubroles = explode(",", $pubrolesraw);

        // Get creator roles as string, then explode to array.
        $createrolesraw = $DB->get_field('block_panopto_foldermap', 'creator_mapping', array('moodleid' => $moodlecourseid));
        $creatorroles = explode(",", $createrolesraw);
        
        return array("publisher" => $pubroles, "creator" => $creatorroles);
    }

    /**
     *  Set the Panopto ID in the db for the current course
     *  Called by Moodle block instance config save method, so must be static.
     */
    public static function set_panopto_course_id($moodlecourseid, $sessiongroupid) {
        global $DB;
        if ($DB->get_records('block_panopto_foldermap', array('moodleid' => $moodlecourseid))) {
            return $DB->set_field('block_panopto_foldermap', 'panopto_id', $sessiongroupid, array('moodleid' => $moodlecourseid));
        } else {
            $row = (object) array('moodleid' => $moodlecourseid, 'panopto_id' => $sessiongroupid);
            return $DB->insert_record('block_panopto_foldermap', $row);
        }
    }

    /**
     * Set the Panopto server name in the db for the current course
     */
    public static function set_panopto_server_name($moodlecourseid, $panoptoservername) {
        global $DB;
        if ($DB->get_records('block_panopto_foldermap', array('moodleid' => $moodlecourseid))) {
            return $DB->set_field('block_panopto_foldermap', 'panopto_server', $panoptoservername, array('moodleid' => $moodlecourseid));
        } else {
            $row = (object) array('moodleid' => $moodlecourseid, 'panopto_server' => $panoptoservername);
            return $DB->insert_record('block_panopto_foldermap', $row);
        }
    }

    /**
     * Set the Panopto app key associated with the current course on the db
     */
    public static function set_panopto_app_key($moodlecourseid, $panoptoappkey) {
        global $DB;
        if ($DB->get_records('block_panopto_foldermap', array('moodleid' => $moodlecourseid))) {
            return $DB->set_field('block_panopto_foldermap', 'panopto_app_key', $panoptoappkey, array('moodleid' => $moodlecourseid));
        } else {
            $row = (object) array('moodleid' => $moodlecourseid, 'panopto_app_key' => $panoptoappkey);
            return $DB->insert_record('block_panopto_foldermap', $row);
        }
    }

    /**
     * Set the selected Panopto role mappings for the current course on the db
     */
    public static function set_course_role_mappings($moodlecourseid, $publisherroles, $creatorroles) {
        global $DB;

        // Implode roles to string.
        $publisherrolestring = implode(',', $publisherroles);

        if ($DB->get_records('block_panopto_foldermap', array('moodleid' => $moodlecourseid))) {
            $pubsuccess = $DB->set_field('block_panopto_foldermap', 'publisher_mapping', $publisherrolestring, array('moodleid' => $moodlecourseid));
        } else {
            $row = (object) array('moodleid' => $moodlecourseid, 'publisher_mapping' => $publisherrolestring);
            $pubsuccess = $DB->insert_record('block_panopto_foldermap', $row);
        }

        // Implode roles to string.
        $creatorrolestring = implode(',', $creatorroles);

        if ($DB->get_records('block_panopto_foldermap', array('moodleid' => $moodlecourseid))) {
            $csuccess = $DB->set_field('block_panopto_foldermap', 'creator_mapping', $creatorrolestring, array('moodleid' => $moodlecourseid));
        } else {
            $row = (object) array('moodleid' => $moodlecourseid, 'creator_mapping' => $creatorrolestring);
            $csuccess = $DB->insert_record('block_panopto_foldermap', $row);
        }
    }

    /**
     * Get list of available courses from db based on user's access level on course
     */
    public function get_course_options() {
        $coursesbyaccesslevel = array("Creator" => array(), "Viewer" => array(), "Public" => array());

        $panoptocourses = $this->get_courses();
        if (!empty($panoptocourses)) {
            foreach ($panoptocourses as $courseinfo) {
                array_push($coursesbyaccesslevel[$courseinfo->Access], $courseinfo);
            }

            $options = array();
            foreach (array_keys($coursesbyaccesslevel) as $accesslevel) {
                $courses = $coursesbyaccesslevel[$accesslevel];
                $group = array();
                foreach ($courses as $courseinfo) {
                    $displayname = s($courseinfo->DisplayName);
                    $group[$courseinfo->PublicID] = $displayname;
                }
                $options[$accesslevel] = $group;
            }
        } else if (isset($panoptocourses)) {
            $options = array('Error' => array('-- No Courses Available --'));
        } else {
            $options = array('Error' => array('!! Unable to retrieve course list !!'));
        }

        return array('courses' => $options, 'selected' => $this->sessiongroupid);
    }

    /**
     * Add a user enrollment to the current course
     */
    public function add_course_user($role, $userkey) {
        // Kent Change.
        switch ($role) {
            case "Creator/Publisher":
                $this->add_course_user_soap_call("Publisher", $userkey);
                $this->add_course_user_soap_call("Creator", $userkey);
                $this->provision_user_folder($userkey);
            break;

            case "Publisher":
                $this->add_course_user_soap_call("Publisher", $userkey);
                $this->provision_user_folder($userkey);
            break;

            case "Creator":
                $this->add_course_user_soap_call("Creator", $userkey);
                $this->provision_user_folder($userkey);
            break;

            default:
                $this->add_course_user_soap_call($role, $userkey);
            break;
        }
        // End Kent Change.
    }

    /**
     * Makes SOAP call for add_course_user function
     */
    private function add_course_user_soap_call($role, $userkey) {

        if (!isset($this->soapclient)) {
            $this->soapclient = $this->instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
        }

        try {
            $result = $this->soapclient->add_user_to_course($this->sessiongroupid, $role, $userkey);
        } catch (Exception $e) {
            debugging("Error: " . $e->getMessage());
            debugging("Code: " . $e->getCode());
            debugging("Line: " . $e->getLine());
        }
        return $result;
    }

    /**
     * Remove a user's enrollment from the current course
     */
    public function remove_course_user($role, $userkey) {
        // If user has both publisher and creator roles, remove both.
        if ($role == "Creator/Publisher") {
            $this->remove_course_user_soap_call("Publisher", $userkey);
            $this->remove_course_user_soap_call("Creator", $userkey);
        } else {
            $this->remove_course_user_soap_call($role, $userkey);
        }
    }

    /**
     * Makes SOAP call for remove_course_user function
     */
    private function remove_course_user_soap_call($role, $userkey) {
        if (!isset($this->soapclient)) {
            $this->soapclient = $this->instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
        }

        try {
            $result = $this->soapclient->remove_user_from_course($this->sessiongroupid, $role, $userkey);
        } catch (Exception $e) {
            debugging("Error: " . $e->getMessage());
            debugging("Code: " . $e->getCode());
            debugging("Line: " . $e->getLine());
        }
        return $result;
    }

    /**
     * Change an enrolled user's role in the current course
     */
    public function change_user_role($role, $userkey) {

        // If user is to have both creator and publisher roles, change his current role to publisher, and add a creator role.
        if ($role == "Creator/Publisher") {
            $this->change_user_role_soap_call("Publisher", $userkey);
            $this->add_course_user_soap_call("Creator", $userkey);
        } else {
            $this->change_user_role_soap_call($role, $userkey);
        }
    }

    /**
     * Makes SOAP call for remove_course_user function
     */
    private function change_user_role_soap_call($role, $userkey) {
        if (!isset($this->soapclient)) {
            $this->soapclient = $this->instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
        }

        try {
            $result = $this->soapclient->change_user_role($this->sessiongroupid, $role, $userkey);
        } catch (Exception $e) {
            debugging("Error: " . $e->getMessage());
            debugging("Code: " . $e->getCode());
            debugging("Line: " . $e->getLine());
        }
        return $result;
    }

    /**
     * Used to instantiate a soap client for a given instance of panopto_data.
     * Should be called only the first time a soap client is needed for an instance.
     */
    public function instantiate_soap_client($username, $servername, $applicationkey) {
        global $USER;
        if (empty($username)) {
            if (isset($USER->username)) {
                $username = $USER->username;
            } else {
                $username = "guest";
            }
            $this->uname = $username;
        }
       
        // Compute web service credentials for current user.
        $apiuseruserkey = panopto_decorate_username($username);
        $apiuserauthcode = panopto_generate_auth_code($apiuseruserkey . "@" . $this->servername, $this->applicationkey);

        // Instantiate our SOAP client.
        return new panopto_soap_client($this->servername, $apiuseruserkey, $apiuserauthcode);
    }

}

/* End of file panopto_data.php */
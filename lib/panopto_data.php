<?php
/* Copyright Panopto 2009 - 2013 / With contributions from Spenser Jones (sjones@ambrose.edu)
 * 
 * This file is part of the Panopto plugin for Moodle.
 * 
 * The Panopto plugin for Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * The Panopto plugin for Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with the Panopto plugin for Moodle.  If not, see <http://www.gnu.org/licenses/>.
 */

global $CFG;
if(empty($CFG)) {
    require_once("../../config.php");
}
require_once ($CFG->libdir . '/dmllib.php');

require_once("block_panopto_lib.php");
require_once("PanoptoSoapClient.php");

class panopto_data {
    var $instancename;

    var $moodle_course_id;

    var $servername;
    var $applicationkey;

    var $soap_client;

    var $sessiongroup_id;

    function __construct($moodle_course_id) {
        global $USER, $CFG;

        // Fetch global settings from DB
        $this->instancename = $CFG->block_panopto_instance_name;
        $this->servername = $CFG->block_panopto_server_name;
        $this->applicationkey = $CFG->block_panopto_application_key;

        if(!empty($this->servername)) {
            if(isset($USER->username)) {
                $username = $USER->username;
            } else {
                $username = "guest";
            }

            // Compute web service credentials for current user.
            $apiuser_userkey = panopto_decorate_username($username);
            $apiuser_authcode = panopto_generate_auth_code($apiuser_userkey . "@" . $this->servername);

            // Instantiate our SOAP client.
            $this->soap_client = new PanoptoSoapClient($this->servername, $apiuser_userkey, $apiuser_authcode);
        }

        // Fetch current CC course mapping if we have a Moodle course ID.
        // Course will be null initially for batch-provisioning case.
        if(!empty($moodle_course_id)) {
            $this->moodle_course_id = $moodle_course_id;
            $this->sessiongroup_id = panopto_data::get_panopto_course_id($moodle_course_id);
        }
    }

    // returns SystemInfo
    function get_system_info() {
        return $this->soap_client->GetSystemInfo();
    }

    // Create the Panopto course and populate its ACLs.
    function provision_course($provisioning_info) {
        $course_info = $this->soap_client->ProvisionCourse($provisioning_info);

        if(!empty($course_info) && !empty($course_info->PublicID)) {
            panopto_data::set_panopto_course_id($this->moodle_course_id, $course_info->PublicID);
        }

        return $course_info;
    }

    // Kent Change
    // Provision folders for each of a courses instructors
    public function provision_user_folders($provisioning_info) {
        global $CFG;

        // Dont do this for 2012
        if ($CFG->kent->distribution === "2012") {
            return null;
        }

        if (empty($provisioning_info->Instructors)) {
            return array();
        }

        $folder_infos = array();

        foreach ($provisioning_info->Instructors as $instructor) {
            $instructor_folder = new stdClass;
            $userkey = explode("\\", $instructor->UserKey);
            $instructor_folder->ShortName = '';
            $instructor_folder->LongName = $userkey[1] . "'s unlisted recordings";
            $instructor_folder->ExternalCourseID = $this->instancename . ":" . $userkey[1];

            $instructor_folder->Instructors = array();
            $instructor_folder->Instructors[] = $instructor;

            $folder_infos[] = $this->soap_client->ProvisionCourse($instructor_folder);
        }

        return $folder_infos;
    }
    // End Change

    // Kent Change
    /**
     * Create a shared folder between multiple courses
     */
    public function provision_shared_folder($shortname, $longname, $courses) {
        if (empty($courses)) {
            throw new \moodle_exception("You must specify one or more courses!");
        }

        // First, extract a set of provisioning information for each course.
        $provisioning_infos = array();
        {
            $saved_id = $this->moodle_course_id;
            foreach ($courses as $course) {
                $this->moodle_course_id = $course->id;
                $provisioning_infos[] = $this->get_provisioning_info();
            }

            $this->moodle_course_id = $saved_id;
        }

        // Now merge it all into one giant block.
        $provisioning_info = new stdClass;
        $provisioning_info->ShortName = $shortname;
        $provisioning_info->LongName = $longname;
        // There must be a primary course, which is unfortunate.
        $provisioning_info->ExternalCourseID = $this->instancename . ":" . $this->moodle_course_id;

        // Merge Instructors in.
        {
            $provisioning_info->Instructors = array();
            foreach ($provisioning_infos as $pi) {
                foreach ($pi->Instructors as $instructor) {
                    if (!isset($provisioning_info->Instructors[$instructor->UserKey])) {
                        $provisioning_info->Instructors[$instructor->UserKey] = $instructor;
                    }
                }
            }

            // Reset the keys.
            $provisioning_info->Instructors = array_values($provisioning_info->Instructors);
        }

        // Merge Students in.
        {
            $provisioning_info->Students = array();
            foreach ($provisioning_infos as $pi) {
                foreach ($pi->Students as $student) {
                    if (!isset($provisioning_info->Students[$student->UserKey])) {
                        $provisioning_info->Students[$student->UserKey] = $student;
                    }
                }
            }

            // Reset the keys.
            $provisioning_info->Students = array_values($provisioning_info->Students);
        }

        // Create the "course" in Panopto.
        return $this->soap_client->ProvisionCourse($provisioning_info);
    }
    // End Change

    // Fetch course name and membership info from DB in preparation for provisioning operation.
    function get_provisioning_info($master = true) {
        // Kent Change
        global $DB, $CFG;
        // End Change

        // Kent Change
        $provisioning_info = new stdClass;
        $provisioning_info->Instructors = array();
        $provisioning_info->Students = array();
        // End Change

        // Kent Change
        // Support course linking if this is the master course in a chain.
        if ($master) {
            $panoptoid = self::get_panopto_course_id($this->moodle_course_id);
            $params = array(
                'panopto_id' => $panoptoid,
                'master' => 1
            );

            // only set new id if in db already
            if ($DB->record_exists('block_panopto_foldermap', $params)) {
                // Select the master and provision that instead.
                $this->moodle_course_id = $DB->get_field('block_panopto_foldermap', 'moodleid', array(
                    'panopto_id' => $panoptoid,
                    'master' => 1
                ));
            }
        }
        // End Change

        $provisioning_info->ShortName = $DB->get_field('course', 'shortname', array('id' => $this->moodle_course_id));
        $provisioning_info->LongName = $DB->get_field('course', 'fullname', array('id' => $this->moodle_course_id));
        $provisioning_info->ExternalCourseID = $this->instancename . ":" . $this->moodle_course_id;

        $course_context = context_course::instance($this->moodle_course_id, MUST_EXIST);

        // Lookup table to avoid adding instructors as Viewers as well as Creators.
        $instructor_hash = array();

        // moodle/course:update capability will include admins along with teachers, course creators, etc.
        // Could also use moodle/legacy:teacher, moodle/legacy:editingteacher, etc. if those turn out to be more appropriate.
        // Kent Change
        $instructors = get_users_by_capability($course_context, 'block/panopto:panoptocreator');
        // End Change

        if (!empty($instructors)) {
            // Kent Change
            $ar = \block_panopto\util::get_role('panopto_academic');
            $nar = \block_panopto\util::get_role('panopto_non_academic');
            // End Change

            foreach ($instructors as $instructor) {
                // Kent Change
                if ($CFG->kent->distribution !== "2012" &&
					!user_has_role_assignment($instructor->id, $ar->id, context_system::instance()->id) && 
                    !user_has_role_assignment($instructor->id, $nar->id, context_system::instance()->id)) {
                    continue;
                }
                // End Change

                $instructor_info = new stdClass;
                $instructor_info->UserKey = $this->panopto_decorate_username($instructor->username);
                $instructor_info->FirstName = $instructor->firstname;
                $instructor_info->LastName = $instructor->lastname;
                $instructor_info->Email = $instructor->email;

                array_push($provisioning_info->Instructors, $instructor_info);

                $instructor_hash[$instructor->username] = true;
            }
        }

        // Give all enrolled users at least student-level access. Instructors will be filtered out below.
        // Use get_enrolled_users because, as of Moodle 2.0, capability moodle/course:view no longer corresponds to a participant list.
        // Kent Change
        $students = get_users_by_capability($course_context, 'block/panopto:panoptoviewer');
        $provisioning_info->Students = array();
        // End Change

        if (!empty($students)) {
            foreach ($students as $student) {
                if (array_key_exists($student->username, $instructor_hash)) {
                    continue;
                }

                $student_info = new stdClass;
                $student_info->UserKey = $this->panopto_decorate_username($student->username);

                array_push($provisioning_info->Students, $student_info);
            }
        }


        // Kent Change
        // We also want to check for "related" courses, e.g. courses that display this course's block.
        if ($master) {
            $courses = $DB->get_records('block_panopto_foldermap', array(
                'panopto_id' => self::get_panopto_course_id($this->moodle_course_id)
            ));
            foreach ($courses as $course) {
                if ($course->moodleid == $this->moodle_course_id) {
                    continue;
                }

                // Add in students and lecturers from this course.
                $tmp_data = new panopto_data($course->moodleid);
                $info = $tmp_data->get_provisioning_info(false);

                // First Instructors.
                foreach ($info->Instructors as $instructor) {
                    // If they are an instructor for the parent course, leave it.
                    // If not, add them in as a student ('viewer').
                    if (!in_array($instructor, $provisioning_info->Instructors) && !in_array($instructor, $provisioning_info->Students)) {
                        array_push($provisioning_info->Students, $instructor);
                    }

                    $instructor_hash[$instructor->UserKey] = true;
                }

                // Then Students.
                foreach ($info->Students as $student) {
                    if (array_key_exists($student->UserKey, $instructor_hash)) {
                        continue;
                    }

                    if (!in_array($student, $provisioning_info->Students)) {
                        array_push($provisioning_info->Students, $student);
                    }
                }
            }
        }
        // End Change

        return $provisioning_info;
    }

    // Get courses visible to the current user.
    function get_courses() {
        $courses_result = $this->soap_client->GetCourses();
        $courses = array();
        if(!empty($courses_result->CourseInfo)) {
            $courses = $courses_result->CourseInfo;
            // Single-element return set comes back as scalar, not array (?)
            if(!is_array($courses)) {
                $courses = array($courses);
            }
        }
        	
        return $courses;
    }

    // Get info about the currently mapped course.
    function get_course() {
        return $this->soap_client->GetCourse($this->sessiongroup_id);
    }

    // Get ongoing Panopto sessions for the currently mapped course.
    function get_live_sessions() {
        $live_sessions_result = $this->soap_client->GetLiveSessions($this->sessiongroup_id);

        $live_sessions = array();
        if(!empty($live_sessions_result->SessionInfo)) {
            $live_sessions = $live_sessions_result->SessionInfo;
            // Single-element return set comes back as scalar, not array (?)
            if(!is_array($live_sessions)) {
                $live_sessions = array($live_sessions);
            }
        }

        return $live_sessions;
    }

    // Get recordings available to view for the currently mapped course.
    function get_completed_deliveries() {
        $completed_deliveries_result = $this->soap_client->GetCompletedDeliveries($this->sessiongroup_id);

        $completed_deliveries = array();
        if(!empty($completed_deliveries_result->DeliveryInfo)) {
            $completed_deliveries = $completed_deliveries_result->DeliveryInfo;
            // Single-element return set comes back as scalar, not array (?)
            if(!is_array($completed_deliveries)) {
                $completed_deliveries = array($completed_deliveries);
            }
        }

        return $completed_deliveries;
    }

    // Instance method caches Moodle instance name from DB (vs. block_panopto_lib version).
    function panopto_decorate_username($moodle_username) {
        return ($this->instancename . "\\" . $moodle_username);
    }

    // We need to retrieve the current course mapping in the constructor, so this must be static.
    static function get_panopto_course_id($moodle_course_id) {
        global $DB;
        return $DB->get_field('block_panopto_foldermap', 'panopto_id', array('moodleid' => $moodle_course_id));
    }

    // Called by Moodle block instance config save method, so must be static.
    static function set_panopto_course_id($moodle_course_id, $sessiongroup_id, $master = true) {
        global $DB;

        if ($DB->get_records('block_panopto_foldermap', array('moodleid' => $moodle_course_id))) {
            // Todo - Skylar doesnt like this as it disregards multiple blocks.
            return  $DB->set_field('block_panopto_foldermap', 'master', $master, array('moodleid' => $moodle_course_id)) &&
                    $DB->set_field('block_panopto_foldermap', 'panopto_id', $sessiongroup_id, array('moodleid' => $moodle_course_id));
        }

        return $DB->insert_record('block_panopto_foldermap', array(
            'moodleid' => $moodle_course_id,
            'panopto_id' => $sessiongroup_id,
            'master' => $master ? 1 : 0
        ));
    }

    function get_course_options() {
        $courses_by_access_level = array("Creator" => array(), "Viewer" => array(), "Public" => array());

        $panopto_courses = $this->get_courses();
        if(!empty($panopto_courses)) {
            foreach($panopto_courses as $course_info) {
                array_push($courses_by_access_level[$course_info->Access], $course_info);
            }

            $options = array();
            foreach(array_keys($courses_by_access_level) as $access_level) {
                $courses = $courses_by_access_level[$access_level];
                $group = array();
                foreach($courses as $course_info) {
                    $display_name = s($course_info->DisplayName);
                    $group[$course_info->PublicID] = $display_name;
                }
                $options[$access_level] = $group;
            }
        }
        else if(isset($panopto_courses)) {
            $options = array('Error' => array('-- No Courses Available --'));
        } else {
            $options = array('Error' => array('!! Unable to retrieve course list !!'));
        }

        return array('courses' => $options, 'selected' => $this->sessiongroup_id);
    }
}
/* End of file panopto_data.php */
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

    var $uname;

    function __construct($moodle_course_id) {
        global $USER, $CFG;

        // Fetch global settings from DB
        $this->instancename = $CFG->block_panopto_instance_name;

        //get servername and application key specific to moodle course if ID is specified
		if(isset($moodle_course_id)){
		$this->servername = panopto_data::get_panopto_servername($moodle_course_id);
		$this->applicationkey = panopto_data::get_panopto_app_key($moodle_course_id);
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
    	//If no soap client for this instance, instantiate one
    	if(!isset($this->soap_client)){
    		$this->soap_client = panopto_data::instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
    	}

    	return $this->soap_client->GetSystemInfo();
    }

    // Create the Panopto course and populate its ACLs.
    function provision_course($provisioning_info) {
    	//If no soap client for this instance, instantiate one
    	if(!isset($this->soap_client)){

    		$this->soap_client = panopto_data::instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
    	}
        $course_info = $this->soap_client->ProvisionCourse($provisioning_info);
        
        
        if(!empty($course_info) && !empty($course_info->PublicID)) {
            panopto_data::set_panopto_course_id($this->moodle_course_id, $course_info->PublicID);
            panopto_data::set_panopto_server_name($this->moodle_course_id, $this->servername);
            panopto_data::set_panopto_app_key($this->moodle_course_id, $this->applicationkey);
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

    	//If no soap client for this instance, instantiate one
    	if(!isset($this->soap_client)){
    		$this->soap_client = panopto_data::instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
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

    	//If no soap client for this instance, instantiate one
    	if(!isset($this->soap_client)){
    		$this->soap_client = panopto_data::instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
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
                $masterid = $DB->get_field('block_panopto_foldermap', 'moodleid', array(
                    'panopto_id' => $panoptoid,
                    'master' => 1
                ));

                // Check the course exists.
                if ($DB->record_exists('course', array('id' => $masterid))) {
                	$this->moodle_course_id = $masterid;
                }
            }
        }
        // End Change

        $provisioning_info->ShortName = $DB->get_field('course', 'shortname', array('id' => $this->moodle_course_id));
        $provisioning_info->LongName = $DB->get_field('course', 'fullname', array('id' => $this->moodle_course_id));
        $provisioning_info->ExternalCourseID = $this->instancename . ":" . $this->moodle_course_id;
        $provisioning_info->Server = $this->servername;
        $course_context = context_course::instance($this->moodle_course_id, MUST_EXIST);

        // Lookup table to avoid adding instructors as Viewers as well as Creators.
        $publisher_hash = array();
        $instructor_hash = array();

        $publishers = get_users_by_capability($course_context, 'block/panopto:provision_aspublisher');

        if(!empty($publishers)) {
            $provisioning_info->Publishers = array();
            foreach($publishers as $publisher) {
                $publisher_info = new stdClass;
                $publisher_info->UserKey = $this->panopto_decorate_username($publisher->username);
                $publisher_info->FirstName = $publisher->firstname;
                $publisher_info->LastName = $publisher->lastname;
                $publisher_info->Email = $publisher->email;

                array_push($provisioning_info->Publishers, $publisher_info);

                $publisher_hash[$publisher->username] = true;
            }
            
        }
        

        // moodle/course:update capability will include admins along with teachers, course creators, etc.
        // Could also use moodle/legacy:teacher, moodle/legacy:editingteacher, etc. if those turn out to be more appropriate.
        // File edited - new capability added to access.php to identify instructors without including all site admins etc.
        // New capability used to identify instructors for provisioning.
        $instructors = get_users_by_capability($course_context, 'block/panopto:provision_asteacher');

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
                if (array_key_exists($student->username, $publisher_hash)) {
                    continue;
                }

                $student_info = new stdClass;
                $student_info->UserKey = $this->panopto_decorate_username($student->username);
                $student_info->FirstName = $student->firstname;
                $student_info->LastName = $student->lastname;
                $student_info->Email = $student->email;

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
                
                // Check the course exists.
                if (!$DB->record_exists('course', array('id' => $course->moodleid))) {
                	continue;
                }

                // Add in students and lecturers from this course.
                $tmp_data = new panopto_data($course->moodleid);
                $info = $tmp_data->get_provisioning_info(false);

                // First Instructors.
                foreach ($info->Instructors as $instructor) {
                    // If they are an instructor for the parent course, leave it.
                    // If not, add them in as a student ('viewer').
                    if (!in_array($instructor, $provisioning_info->Instructors) &&
                        !in_array($instructor, $provisioning_info->Publishers) &&
                        !in_array($instructor, $provisioning_info->Students)) {
                        array_push($provisioning_info->Instructors, $instructor);
                    }

                    $instructor_hash[$instructor->UserKey] = true;
                }

                // Then Publishers.
                foreach ($info->Publishers as $publisher) {
                    // If they are an instructor for the parent course, leave it.
                    // If not, add them in as a student ('viewer').
                    if (!in_array($publisher, $provisioning_info->Instructors) &&
                        !in_array($publisher, $provisioning_info->Publishers) &&
                        !in_array($publisher, $provisioning_info->Students)) {
                        array_push($provisioning_info->Publishers, $publisher);
                    }

                    $publisher_hash[$publisher->UserKey] = true;
                }

                // Then Students.
                foreach ($info->Students as $student) {
                    if (array_key_exists($student->UserKey, $instructor_hash)) {
                        continue;
                    }
                    
                    if (array_key_exists($student->UserKey, $publisher_hash)) {
                        continue;
                    }

                    if (!in_array($student, $provisioning_info->Instructors) &&
                        !in_array($student, $provisioning_info->Publishers) &&
                        !in_array($student, $provisioning_info->Students)) {
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
    	if(!isset($this->soap_client)){
    		$this->soap_client = panopto_data::instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
    	}

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
    	//If no soap client for this instance, instantiate one
    	if(!isset($this->soap_client)){
    		$this->soap_client = panopto_data::instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
    	}

        return $this->soap_client->GetCourse($this->sessiongroup_id);
    }

    // Get ongoing Panopto sessions for the currently mapped course.
    function get_live_sessions() {
    	//If no soap client for this instance, instantiate one
    	if(!isset($this->soap_client)){
    		$this->soap_client = panopto_data::instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
    	}

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
        //If no soap client for this instance, instantiate one
        if(!isset($this->soap_client)){
            $this->soap_client = panopto_data::instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
        }

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

    static function get_panopto_servername($moodle_course_id) {
    	global $DB;
    	return $DB->get_field('block_panopto_foldermap', 'panopto_server', array('moodleid' => $moodle_course_id));
    }

    static function get_panopto_app_key($moodle_course_id) {
    	global $DB;
    	return $DB->get_field('block_panopto_foldermap', 'panopto_app_key', array('moodleid' => $moodle_course_id));
    }
    
    static function get_course_role_mappings($moodle_course_id) {
    	global $DB;
        //get publisher roles as string and explode to array
        $pubrolesraw =  $DB->get_field('block_panopto_foldermap', 'publisher_mapping', array('moodleid' => $moodle_course_id));
        $pubroles = explode("," , $pubrolesraw);
       
        //get creator roles as string, then explode to array       
        $createrolesraw =  $DB->get_field('block_panopto_foldermap', 'creator_mapping', array('moodleid' => $moodle_course_id));
        $creatorroles = explode(",", $createrolesraw);
        return array("publisher" => $pubroles, "creator" => $creatorroles);
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

    static function set_panopto_server_name($moodle_course_id, $panopto_servername) {
    	global $DB;
    	if($DB->get_records('block_panopto_foldermap', array('moodleid' => $moodle_course_id))) {
    		return $DB->set_field('block_panopto_foldermap', 'panopto_server', $panopto_servername, array('moodleid' => $moodle_course_id));
    	} else {
    		$row = (object) array('moodleid' => $moodle_course_id, 'panopto_server' => $panopto_servername);
    		return $DB->insert_record('block_panopto_foldermap', $row);
    	}
    }

    static function set_panopto_app_key($moodle_course_id, $panopto_appkey) {
    	global $DB;
    	if($DB->get_records('block_panopto_foldermap', array('moodleid' => $moodle_course_id))) {
    		return $DB->set_field('block_panopto_foldermap', 'panopto_app_key', $panopto_appkey, array('moodleid' => $moodle_course_id));
    	} else {
    		$row = (object) array('moodleid' => $moodle_course_id, 'panopto_app_key' => $panopto_appkey);
    		return $DB->insert_record('block_panopto_foldermap', $row);
    	}
    }

        static function set_course_role_mappings($moodle_course_id, $publisherroles, $creatorroles) {
    	global $DB;
    	
                  //implode roles to string
                   $publisher_role_string = implode(',', $publisherroles);

                  if($DB->get_records('block_panopto_foldermap', array('moodleid' => $moodle_course_id))) {
                    $pubsuccess = $DB->set_field('block_panopto_foldermap', 'publisher_mapping', $publisher_role_string, array('moodleid' => $moodle_course_id));
                } else {
                $row = (object) array('moodleid' => $moodle_course_id, 'publisher_mapping' => $publisher_role_string);
                $pubsuccess = $DB->insert_record('block_panopto_foldermap', $row);
                
                }

                  //implode roles to string
                   $creator_role_string = implode(',', $creatorroles);

                  if($DB->get_records('block_panopto_foldermap', array('moodleid' => $moodle_course_id))) {
                   $csuccess = $DB->set_field('block_panopto_foldermap', 'creator_mapping', $creator_role_string, array('moodleid' => $moodle_course_id));
                    } else {
                    $row = (object) array('moodleid' => $moodle_course_id, 'creator_mapping' => $creator_role_string);
    		 $csuccess = $DB->insert_record('block_panopto_foldermap', $row);
    	}

        
                 
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

    function add_course_user($role, $userkey){
        if(!isset($this->soap_client)){

            $this->soap_client = $this->instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
        }
        
        $result;
        
        try{
            $result = $this->soap_client->AddUserToCourse($this->sessiongroup_id, $role, $userkey);
           
        }
        catch(Exception $e)
        {
            error_log("Error: " . $e->getMessage());
            error_log("Code: " . $e->getCode());
            error_log("Line: ". $e->getLine());
        }
        return $result;
    }

    function remove_course_user($role, $userkey){
        if(!isset($this->soap_client)){

            $this->soap_client = $this->instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
        }
        
        $result;
        
        try{
            $result = $this->soap_client->RemoveuserFromCourse($this->sessiongroup_id, $role, $userkey);
           
        }
        catch(Exception $e)
        {
            error_log("Error: " . $e->getMessage());
            error_log("Code: " . $e->getCode());
            error_log("Line: ". $e->getLine());
        }
        return $result;
    }
       
    function change_user_role($role, $userkey){
        
        
        if(!isset($this->soap_client)){
            
            $this->soap_client = $this->instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
        }
        
        $result;
        
        try{
            $result = $this->soap_client->ChangeUserRole($this->sessiongroup_id, $role, $userkey);
           
        }
        catch(Exception $e)
        {
            error_log("Error: " . $e->getMessage());
            error_log("Code: " . $e->getCode());
            error_log("Line: ". $e->getLine());
        }
        return $result;
    }

    //Used to instantiate a soap client for a given instance of panopto_data. Should be called only the first time a soap client is needed for an instance
    function instantiate_soap_client($username, $servername, $applicationkey){
        global $USER;
    	if(!empty($this->servername)) {
    		if(isset($USER->username)) {
    			$username = $USER->username;
    		} else {
    			$username = "guest";
    		}
    		$this->uname = $username;
    	}
    	// Compute web service credentials for current user.
    	$apiuser_userkey = panopto_decorate_username($username);
    	$apiuser_authcode = panopto_generate_auth_code($apiuser_userkey . "@" . $this->servername, $this->applicationkey);

    	// Instantiate our SOAP client.
    	return new PanoptoSoapClient($this->servername, $apiuser_userkey, $apiuser_authcode);
    }
}
/* End of file panopto_data.php */

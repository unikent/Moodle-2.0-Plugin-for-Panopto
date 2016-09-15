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
/**
 * Subclasses SoapClient and hand-crafts SOAP parameters to be compatible with ASP.NET web service in non-WSDL mode.
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2015
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
include (dirname(__FILE__) ."/soap_client_with_timeout.php");
class panopto_soap_client extends soap_client_with_timeout {
    public $authparams;
    // Older PHP SOAP clients fail to pass the SOAPAction header properly.
    // Store the current action so we can insert it in __doRequest.
    public $currentaction;
    public function __construct($servername, $apiuseruserkey, $apiuserauthcode) {
        // Instantiate SoapClient in non-WSDL mode.
        //Set call timeout to 5 minutes.
        parent::__construct
        (
            null,
            array(
                    'location' => "http://$servername/Panopto/Services/ClientData.svc",
                    'uri' => "http://services.panopto.com",
                    'timeout' => 300000
                )
        );
        // Cache web service credentials for all calls requiring authentication.
        $this->authparams = array("ApiUserKey" => $apiuseruserkey,
            "AuthCode" => $apiuserauthcode);
    }

    /**
     * Override SOAP action to work around bug in older PHP SOAP versions.
     */
    public function __doRequest($request, $location, $action, $version, $oneway = null) {
        return parent::__doRequest($request, $location, $this->currentaction, $version);
    }
    // Wrapper functions for Panopto ClientData web methods.
    /**
     * Call API function to provision a course with Panopto
     */
    public function provision_course($provisioninginfo) {
        return $this->call_web_method("ProvisionCourse", array("ProvisioningInfo" => $provisioninginfo));
    }


    // Wrapper functions for Panopto ClientData web methods.
    /**
     * Call API function to provision a course with Panopto
     */
    public function provision_course_with_options($provisioninginfo) {
        return $this->call_web_method("ProvisionCourseWithOptions", array("ProvisioningInfoWithOptions" => $provisioninginfo));
    }


    /**
    *Provisioning functions that clears members with a particular role from the acl of the course's folder.
    *These are used for paged provisioning when the user count for a course is over the threshold for a particular role.
    */

    public function provision_course_with_course_options($provisioninginfo, $options) {
        return $this->call_web_method("ProvisionCourseWithOptions", array("ProvisioningInfoWithCourseOptions" => array("ProvisioningInfo" => $provisioninginfo, "CourseOptions" => $options)));
    }



    //Function used to provision users to a course after the course itself has been provisioned. Used by
    //make_paged_call_provision_users.
    public function provision_users($userprovisioninginfo){
        return $this->call_web_method("ProvisionUsers", array("UserProvisioningInfo" => $userprovisioninginfo));
    }

/**
 * Call API funtion to get list of  Panopto courses
 */
    public function get_courses() {
        return $this->call_web_method("GetCourses");
    }
    /**
     *  Call API function to get a particular Panopto course based on passed in session group ID
     */
    public function get_course($sessiongroupid) {
        return $this->call_web_method("GetCourse", array("CoursePublicID" => $sessiongroupid));
    }
    /**
     * Call API function to get a list of live sessions course based on passed in session group ID
     */
    public function get_live_sessions($sessiongroupid) {
        return $this->call_web_method("GetLiveSessions", array("CoursePublicID" => $sessiongroupid));
    }
    /**
     * Call API function to get a list of live sessions course based on passed in session group ID
     */
    public function get_completed_deliveries($sessiongroupid) {
        return $this->call_web_method("GetCompletedDeliveries", array("CoursePublicID" => $sessiongroupid));
    }
    /**
     * API call to get system info
     */
    public function get_system_info() {
        // Empty param list, and false to not auto-add auth params.
        return $this->call_web_method("GetSystemInfo", array(), false);
    }
    /**
     *  Calls API function to enroll a user in a Panopto course
     */
    public function add_user_to_course($sessiongroupid, $role, $userkey) {
        try {
            return $this->call_web_method("AddUserToCourse", array("CoursePublicID" => $sessiongroupid, "Role" => $role, "UserKey" => $userkey));
        } catch (Exception $e) {
            error_log("Error:" . $e->getMessage());
            error_log("File: " . $e->getFile());
            error_log("Line: " . $e->getLine());
            error_log("Trace: " . $e->getTraceAsString());
        }
    }

    /**
     *  Calls API function to delete a user's enrollment from a course
     */
    public function remove_user_from_course($sessiongroupid, $role, $userkey) {
        try {
            return $this->call_web_method("RemoveUserFromCourse", array("CoursePublicID" => $sessiongroupid, "Role" => $role, "UserKey" => $userkey));
        } catch (Exception $e) {
            error_log("Error:" . $e->getMessage());
            error_log("File: " . $e->getFile());
            error_log("Line: " . $e->getLine());
            error_log("Trace: " . $e->getTraceAsString());
        }
    }
    /**
     *  Calls API function to change a user's enrollment in a course
     */
    public function change_user_role($sessiongroupid, $role, $userkey) {
        try {
            $ads = $this->call_web_method("ChangeUserRole", array("CoursePublicID" => $sessiongroupid, "Role" => $role, "UserKey" => $userkey));
        } catch (Exception $e) {
            error_log("Error:" . $e->getMessage());
            error_log("File: " . $e->getFile());
            error_log("Line: " . $e->getLine());
            error_log("Trace: " . $e->getTraceAsString());
        }
    }
    // Helper functions for calling Panopto ClientData web methods in non-WSDL mode.
    /**
     *  Helper method for making a call to the Panopto API
     */
    private function call_web_method($methodname, $namedparams = array(), $auth = true) {
        $soapvars = $this->get_panopto_soap_vars($namedparams);

        // Include API user and auth code params unless $auth is set to false.
        if ($auth) {
            $authvars = $this->get_panopto_soap_vars($this->authparams);
            $mergedvars = array_merge($soapvars, $authvars);
            $soapvars = $mergedvars;
        }
        // Store action for use in overridden __doRequest.
        $this->currentaction = "http://services.panopto.com/IClientDataService/$methodname";
        // Make the SOAP call via SoapClient::__soapCall.
        return parent::__soapCall($methodname, $soapvars);
    }
    /**
     * Convert an associative array into an array of SoapVars with name $key and value $value.
     */
    private function get_panopto_soap_vars($params) {
        // Screwy syntax to map an instance method taking two params over an associative array.
        return array_map(array("panopto_soap_client", "get_panopto_soap_var"), array_keys($params), array_values($params));
    }
    /**
     * Construct a scalar-valued SOAP param.
     */
    private function get_panopto_soap_var($name, $value) {
        if ($name == "ProvisioningInfo") {
            $soapvar = $this->get_provisioning_soap_var($value);
        }
        else if ($name == "ProvisioningInfoWithOptions")
        {
           $soapvar = $this->get_provisioning_soap_var_with_options($value);
        }
        else if ($name == "ProvisioningInfoWithCourseOptions")
        {
            $soapvar = $this->get_provisioning_soap_var_with_course_options($value["ProvisioningInfo"], $value["CourseOptions"]);
        }
        else if ($name == "UserProvisioningInfo")
        {
            $soapvar = $this->get_user_provisioning_soap_var($value);
        }
        else {
            $dataelement = $this->get_xml_data_element($name, $value);
            $soapvar = new SoapVar($dataelement, XSD_ANYXML);
        }
        return $soapvar;
    }
    /**
     * XML-encode value and wrap in tags with specified name.
     */
    private function get_xml_data_element($name, $value) {
        $valueescaped = htmlspecialchars($value);
        return "<ns1:$name>$valueescaped</ns1:$name>";
    }
    /**
     * Creates a SOAP var formatted correctly to use in the provision_course call
     */
    private function get_provisioning_soap_var($provisioninginfo) {
        $soapstruct = $this->get_formatted_provisioning_info($provisioninginfo);
        return new SoapVar($soapstruct, XSD_ANYXML);
    }

    /**
     * Creates a SOAP var formatted correctly to use in the provision_course call
     */
    private function get_provisioning_soap_var_with_options($provisioninginfo) {
        $soapstruct = $this->get_formatted_provisioning_info($provisioninginfo);
        $soapstruct .= "<ns1:UserOptions>";
        $soapstruct .= $this->get_xml_data_element("HasMailLectureNotifications", "false");
        $soapstruct .= "</ns1:UserOptions>";

        return new SoapVar($soapstruct, XSD_ANYXML);
    }

    private function get_provisioning_soap_var_with_course_options($provisioninginfo, $courseoptions)
    {
        $soapstruct = $this->get_formatted_provisioning_info($provisioninginfo);


         //If we don't want to provision users, add a CourseOptions array with "ProvisionUsers" set to false
        if($courseoptions != null)
        {
            $soapstruct .= "<ns1:CourseOptions>";
            foreach($courseoptions as $key => $value)
            {
                $soapstruct .= $this->get_xml_data_element($key, $value);
            }

            $soapstruct .= "</ns1:CourseOptions>";
        }

        $soapstruct .= "<ns1:UserOptions>";
        $soapstruct .= $this->get_xml_data_element("HasMailLectureNotifications", "false");
        $soapstruct .= "</ns1:UserOptions>";
        return new SoapVar($soapstruct, XSD_ANYXML);
    }

    private function get_formatted_provisioning_info($provisioninginfo)
    {

        // DO NOT CHANGE THE ORDERING HERE!
        // The order should be: External course ID, Instructors, Longname, Publishers, Shortname, Students.
        // If you change the order, things will break.
        $soapstruct = "<ns1:ProvisioningInfo>";
        $soapstruct .= $this->get_xml_data_element("ExternalCourseID", $provisioninginfo->ExternalCourseID);
        if (!empty($provisioninginfo->Instructors)) {
            $soapstruct .= "<ns1:Instructors>";
            foreach ($provisioninginfo->Instructors as $instructor) {
                $soapstruct .= "<ns1:UserProvisioningInfo>";
                $soapstruct .= $this->get_xml_data_element("Email", $instructor->Email);
                $soapstruct .= $this->get_xml_data_element("FirstName", $instructor->FirstName);
                $soapstruct .= $this->get_xml_data_element("LastName", $instructor->LastName);
                $soapstruct .= $this->get_xml_data_element("UserKey", $instructor->UserKey);
                $soapstruct .= "</ns1:UserProvisioningInfo>";
            }
            $soapstruct .= "</ns1:Instructors>";
        } else {
            $soapstruct .= "<ns1:Instructors />";
        }
        $soapstruct .= $this->get_xml_data_element("LongName", $provisioninginfo->LongName);
        if (!empty($provisioninginfo->Publishers)) {
            $soapstruct .= "<ns1:Publishers>";
            foreach ($provisioninginfo->Publishers as $publisher) {
                $soapstruct .= "<ns1:UserProvisioningInfo>";
                $soapstruct .= $this->get_xml_data_element("Email", $publisher->Email);
                $soapstruct .= $this->get_xml_data_element("FirstName", $publisher->FirstName);
                $soapstruct .= $this->get_xml_data_element("LastName", $publisher->LastName);
                $soapstruct .= $this->get_xml_data_element("UserKey", $publisher->UserKey);
                $soapstruct .= "</ns1:UserProvisioningInfo>";
            }
            $soapstruct .= "</ns1:Publishers>";
        } else {
            $soapstruct .= "<ns1:Publishers />";
        }
        $soapstruct .= $this->get_xml_data_element("ShortName", $provisioninginfo->ShortName);
        if (!empty($provisioninginfo->Students)) {
            $soapstruct .= "<ns1:Students>";
            foreach ($provisioninginfo->Students as $student) {
                $soapstruct .= "<ns1:UserProvisioningInfo>";
                $soapstruct .= $this->get_xml_data_element("Email", $student->Email);
                $soapstruct .= $this->get_xml_data_element("FirstName", $student->FirstName);
                $soapstruct .= $this->get_xml_data_element("LastName", $student->LastName);
                $soapstruct .= $this->get_xml_data_element("UserKey", $student->UserKey);
                $soapstruct .= "</ns1:UserProvisioningInfo>";
            }
            $soapstruct .= "</ns1:Students>";
        } else {
            $soapstruct .= "<ns1:Students />";
        }
        $soapstruct .= "</ns1:ProvisioningInfo>";

        return $soapstruct;
    }

    //Gets formatted soap variable used for paged calls to ProvisionUsers.
    private function get_user_provisioning_soap_var($userprovisioninginfo)
    {
        $soapstruct = "";
        if(!empty($userprovisioninginfo))
        {
            $soapstruct .= "<ns1:ProvisioningInfo>";
            foreach($userprovisioninginfo as $user)
            {
                $soapstruct .= "<ns1:UserProvisioningInfo>";
                $soapstruct .= $this->get_xml_data_element("Email", $user->Email);
                $soapstruct .= $this->get_xml_data_element("FirstName", $user->FirstName);
                $soapstruct .= $this->get_xml_data_element("LastName", $user->LastName);
                $soapstruct .= $this->get_xml_data_element("UserKey", $user->UserKey);
                $soapstruct .= "</ns1:UserProvisioningInfo>";
            }
            $soapstruct .= "</ns1:ProvisioningInfo>";
        }
        else
        {
            $soapstruct .= "<ns1:ProvisioningInfo />";
        }
        $soapstruct .= "<ns1:UserOptions>";
        $soapstruct .= $this->get_xml_data_element("HasMailLectureNotifications", "false");
        $soapstruct .= "</ns1:UserOptions>";

        return new SoapVar($soapstruct, XSD_ANYXML);
    }

    //Splits array of UserProvisioningInfo into pages, and makes separate calls to ProvisionUSers with each page to prevent timeouts
    //when there are many users being provisioned to a course.
    function make_paged_call_provision_users($userArray)
    {
        $arraysize = count($userArray);
        $pagesize = 50;
        $pagenumber = 1;

        //While there is still at least one full page of users to provision, make calls to provision the full page-sized amount of users.
        while($pagenumber * $pagesize <= $arraysize)
        {
            $userpage = array_slice(
                    $userArray,
                    ($pagenumber -1) * $pagesize,
                    $pagesize
                );
            $this->provision_users($userpage);

            $pagenumber += 1;
        }

        //Provision the remaining users in their own page. If there was less than a single page of users in the first place, only
        // this logic will be called.
        $finalpage = array_slice(
                $userArray,
                ($pagenumber -1) * $pagesize
            );
        $this->provision_users($finalpage);
    }

}
/* End of file PanoptoSoapClient.php */

<?php
/**
 * Panopto course provisioning script
 */

global $CFG, $USER, $DB;

define('CLI_SCRIPT', true);

require_once (dirname(__FILE__) . '/../../../config.php');
require_once ($CFG->libdir . '/accesslib.php');
require_once (dirname(__FILE__) . '/../lib/panopto_data.php');

$USER->username = 'moodlesync';


$result = array();

// Go through all courses that need updating
$courses = $DB->get_records_select("panopto_course_update_list", "courseid");
foreach ($courses as $course) {
  $courseid = $course->courseid;
  try {
    $panopto_data = new panopto_data($courseid);
    $provisioning_data = $panopto_data->get_provisioning_info();
    
    // Provision the course
    $panopto_data->provision_course($provisioning_data);

    $result []= array(
      'result' => 'ok',
      'in' => $courseid,
      'out' => '');
  } catch( Exception $e ) {
    $result []= array(
      'result' => 'error',
      'in' => $courseid,
      'exception' => $e->getMessage());
  }
}

echo json_encode($result);
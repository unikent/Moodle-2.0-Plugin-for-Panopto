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

$sql = 'SELECT ctx.id, ctx.instanceid FROM {block_instances} bi
          LEFT JOIN {context} ctx ON ctx.id = bi.parentcontextid
        WHERE bi.blockname="panopto" AND ctx.contextlevel = ' . CONTEXT_COURSE;

$courses = $DB->get_records_sql($sql);

$result = array();

foreach ($courses as $course) {
  $courseid = $course->instanceid;
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
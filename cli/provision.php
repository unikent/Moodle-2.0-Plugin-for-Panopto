<?php
/**
 * Panopto course provisioning script
 */

define('CLI_SCRIPT',true);

require_once (dirname(__FILE__).'/../../../config.php');
require_once dirname(__FILE__).'/../lib/panopto_data.php';

global $CFG, $USER;

$USER->username = 'moodlesync';

$result = array();

foreach (json_decode(file_get_contents('php://stdin')) as $c) {
  try {
    $panopto_data = new panopto_data(null);
    $panopto_data->moodle_course_id = $c;
    $provisioning_data = $panopto_data->get_provisioning_info();
    $provisioned_data = $panopto_data->provision_course($provisioning_data);

    $result []= array(
      'result' => 'ok',
      'in' => $c,
      'out' => $provisioned_data);
  } catch( Exception $e ) {
    $result []= array(
      'result' => 'error',
      'in' => $c,
      'exception' => $e->getMessage());
  }
}

echo json_encode($result);
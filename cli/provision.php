<?php
/**
 * Panopto course provisioning script
 */

define('CLI_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/accesslib.php');
require_once(dirname(__FILE__) . '/../lib/panopto_data.php');

$USER->username = 'moodlesync';

$result = array();

// Go through all courses that need updating and provision them
$courses = $DB->get_records_select("course");
foreach ($courses as $course) {
    $panoptodata = new panopto_data($course->id);

    try {
        // Provision the course.
        $provisioningdata = $panoptodata->get_provisioning_info();
        $panoptodata->provision_course($provisioningdata);

        echo "Provisioned {$course->id}\n";
    } catch (Exception $e) {
        echo "Error provisioning {$course->id}...\n";
        echo $e->getMessage();
        echo "\n";
    }
}


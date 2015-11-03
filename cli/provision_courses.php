<?php
/**
 * Panopto course provisioning script
 */

define('CLI_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/accesslib.php');
require_once(dirname(__FILE__) . '/../lib/panopto_data.php');

list($options, $unrecognized) = cli_get_params(
    array(
        'course' => '*',
    )
);

$USER->username = 'moodlesync';

// Build list of users.
$courses = array();
if ($options['course'] == '*') {
    $courses = $DB->get_fieldset_select('block_panopto_foldermap', 'moodleid', '');
} else {
    $courses = explode(',', $options['course']);
}

// Go through courses that need updating and provision them.
foreach ($courses as $course) {
    $panoptodata = new panopto_data($course);

    try {
        // Provision the course.
        $provisioningdata = $panoptodata->get_provisioning_info();
        $panoptodata->provision_course($provisioningdata);

        echo "Provisioned {$course}\n";
    } catch (Exception $e) {
        echo "Error provisioning {$course}...\n";
        echo $e->getMessage();
        echo "\n";
    }
}

<?php
/**
 * Panopto course provisioning script
 */

define('CLI_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/../lib/panopto_data.php');
require_once($CFG->libdir . '/accesslib.php');
require_once($CFG->libdir . '/enrollib.php');

$result = array();

// Go through all users that need updating and provision them.
$users = $DB->get_records('user');
foreach ($users as $user) {
    \core\session\manager::set_user($user);
    $courses = enrol_get_my_courses();
    if (empty($courses)) {
        continue;
    }

    foreach ($courses as $course) {
        $context = \context_course::instance($course->id);
        if (has_capability('block/panopto:panoptocreator', $context)) {
            try {
                $panoptodata = new \panopto_data($course->id);
                $panoptodata->provision_user_folder($user);

                echo "Provisioned {$user->id}\n";
            } catch (Exception $e) {
                echo "Error provisioning {$user->id}...\n";
                echo $e->getMessage();
                echo "\n";
            }

            break;
        }
    }
}

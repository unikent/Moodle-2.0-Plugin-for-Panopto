<?php

define('AJAX_SCRIPT', true);

global $CFG, $DB, $USER;

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . "/$CFG->admin/roles/lib.php");
require_once(dirname(__FILE__) . '/lib/panopto_data.php');

require_login();

$role = required_param('role', PARAM_TEXT);
$courseid = required_param('course', PARAM_INT);

switch ($role) {
    case 'ac':
        $role = 'panopto_academic';
    break;

    case 'nac':
        $role = 'panopto_non_academic';
    break;

    default:
    throw new \moodle_exception('Incorrect role given');
}

$role = \block_panopto\util::get_role($role);
if ($role === false) {
    throw new moodle_exception('Incorrect role given');
}

$sysContext = context_system::instance();
$crsContext = context_course::instance($courseid);

if (has_capability('block/panopto:panoptocreator', $crsContext)) {
    $raid = role_assign($role->id, $USER->id, $sysContext);
    if (empty($raid)) {
        throw new moodle_exception('Role assignment failed');
    }
}

$course_provision = false;
if (has_capability('block/panopto:provision_course', $crsContext)) {
    $panopto_data = new panopto_data(null);
    $panopto_data->moodle_course_id = $courseid;
    $provisioning_data = $panopto_data->get_provisioning_info();
    $provisioned_data = $panopto_data->provision_course($provisioning_data);
    if (!empty($provisioned_data)) {
        $panopto_data->provision_user_folders($provisioning_data);
    }

    $course_provision = empty($provisioned_data) ? false : true;
}

if ($role->shortname == 'panopto_academic') {
    $email_plain = get_string('accademic_terms_plain_txt', 'block_panopto');
    $email_html = get_string('accademic_terms', 'block_panopto');
} else {
    $email_plain = get_string('non_accademic_terms_plain_txt', 'block_panopto');
    $email_html = get_string('non_accademic_terms', 'block_panopto');
}

email_to_user($USER, get_admin(), get_string('email_subject', 'block_panopto'), $email_plain, $email_html);

if (!empty($CFG->block_panopto_admin_email_toggle)) {
    $email_txt = 'User ' . $USER->firstname . ' ' . $USER->lastname . ' (' . $USER->username . '),';
    $email_txt .= 'agreed to the ' . $role . ' terms and conditions on ' . date('d/m/Y', time()) . ' at ' . date('G:i', time());
    email_to_user($CFG->block_panopto_admin_email, get_admin(), get_string('admin_email_subject', 'block_panopto'), $email_txt, $email_txt);
}

echo json_encode(array(
    'role_assign' => true,
    'course_provision' => $course_provision
));

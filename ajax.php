<?php

define('AJAX_SCRIPT', true);

/** Include config */
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once(dirname(__FILE__) . '/block_panopto.php');

$courseid = required_param('courseid', PARAM_INT);
$editing = optional_param('editing', 0, PARAM_BOOL);

require_login($courseid);
require_sesskey();

$PAGE->set_context(context_course::instance($courseid, MUST_EXIST));

$block = new block_panopto();
$content = $block->get_ajax_content($editing);

echo $OUTPUT->header();
echo json_encode(array(
    "footer" => $content->footer,
    "text" => $content->text
));
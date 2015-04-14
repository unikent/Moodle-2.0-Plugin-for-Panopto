<?php

define('AJAX_SCRIPT', true);

/** Include config */
require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/block_panopto.php');

require_sesskey();

$PAGE->set_context(context_course::instance($COURSE->id, MUST_EXIST));

$block = new block_panopto();
$content = $block->get_ajax_content();

echo $OUTPUT->header();
echo json_encode(array(
    "footer" => $content->footer,
    "text" => $content->text
));
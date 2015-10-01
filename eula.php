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
 * Panopto block.
 *
 * @package    block_panopto
 * @category   block
 * @copyright  University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');

$version = optional_param('version', null, PARAM_INT);

// We might actually have been forwarded by a course.
$course = optional_param('course', null, PARAM_INT);
if ($course !== null) {
    require_login($course);

    $PAGE->set_url('/blocks/panopto/eula.php', array(
        'course' => $course,
        'version' => $version
    ));
} else {
    require_login();

    $context = context_system::instance();
    $PAGE->set_context($context);
    $PAGE->set_url('/blocks/panopto/eula.php', array('version' => $version));
}

// Have we signed?
if (optional_param('sign', false, PARAM_BOOL)) {
    require_sesskey();

    $version = required_param('version', PARAM_INT);

    // Sign.
    \block_panopto\eula::sign($course, $USER->id, $version);

    // Redirect back?
    redirect(new \moodle_url($PAGE->context->get_url()), 'Agreement signed successfully!', 2);
}

$PAGE->set_title('Kent Player Terms and Conditions');
$PAGE->set_heading('Kent Player Terms and Conditions');
$PAGE->requires->css('/blocks/panopto/styles.css');

echo $OUTPUT->header();
echo $OUTPUT->heading($PAGE->heading);

if (empty($version)) {
    $acurl = new \moodle_url($PAGE->url, array(
        'version' => \block_panopto\eula::VERSION_ACADEMIC
    ));
    echo \html_writer::tag('p', \html_writer::link($acurl, 'Sign the academic agreement', array(
        'class' => 'btn btn-lg btn-default',
        'role' => 'button'
    )));

    $nacurl = new \moodle_url($PAGE->url, array(
        'version' => \block_panopto\eula::VERSION_NON_ACADEMIC
    ));
    echo \html_writer::tag('p', \html_writer::link($nacurl, 'Sign the non-academic agreement', array(
        'class' => 'btn btn-lg btn-default',
        'role' => 'button'
    )));
} else {
    // Display the agreement.
    echo '<div class="panel panel-primary panel-panopto">';

    echo '<div class="panel-heading">';
    echo get_string('eula_' . $version . '_title', 'block_panopto');
    echo '</div>';

    echo '<div class="panel-body">';
    echo get_string('eula_' . $version . '_html', 'block_panopto');
    echo '</div>';

    echo '</div>';

    $signurl = new \moodle_url($PAGE->url, array(
        'sign' => true,
        'sesskey' => sesskey()
    ));
    echo \html_writer::tag('p', \html_writer::link($signurl, 'Sign agreement', array(
        'class' => 'btn btn-primary pull-right',
        'role' => 'button'
    )));
}

echo $OUTPUT->footer();
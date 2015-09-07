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
 * @package block_panopto
 * @copyright  Panopto 2009 - 2015 /With contributions from Spenser Jones (sjones@ambrose.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("lib/panopto_data.php");
require_once(dirname(__FILE__) . '/../../lib/accesslib.php');

/**
 * Base class for the Panopto block for Moodle.
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2015
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_panopto extends block_base {
    /**
     * Set system properties of plugin.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_panopto');
    }

    /**
     * Block has global config (display "Settings" link on blocks admin page).
     */
    public function has_config() {
        return true;
    }

    /**
     * Block has per-instance config (display edit icon in block header).
     */
    public function instance_allow_config() {
        return true;
    }

    /**
     * Save per-instance config in custom table instead of mdl_block_instance configdata column.
     */
    public function instance_config_save($data, $nolongerused = false) {
        global $COURSE;
        if (!empty($data->course)) {
            panopto_data::set_panopto_course_id($COURSE->id, $data->course);

            // If role mapping info is given, map roles.
            if (!empty($data->creator) || !empty($data->publisher)) {
                self::set_course_role_permissions($COURSE->id, $data->publisher, $data->creator);

                // Get course context.
                $context = context_course::instance($COURSE->id);
            }
        } else {
            // If server is not set globally, there will be no other form values to push into config.
            return true;
        }
    }

    /**
     * Required JS
     */
    public function get_required_javascript() {
        global $COURSE, $CFG;

        parent::get_required_javascript();

        $this->page->requires->strings_for_js(array(
            'error',
            'show_all',
            'show_less',
            'ajax_json_error',
            'ajax_data_error',
            'ajax_failure',
            'ajax_busy'
        ), 'block_panopto');

        // Finally, the init call
        $this->page->requires->js_init_call('M.local_panopto.init', array($COURSE->id), false, array(
            'name' => 'local_panopto',
            'fullpath' => '/blocks/panopto/js/ajax.js',
            'requires' => array("node", "io", "dump", "json-parse")
        ));
    }

    // Generate HTML for block contents
    public function get_content() {
        global $CFG, $COURSE, $USER;

        if ($this->content) {
            return $this->content;
        }

        $this->content = new \stdClass();
        $this->content->text = "";
        $this->content->footer = '<div id="panopto-footer"></div>';

        // If we have not signed the agreement, but we are an editor, show the TAC.
        if (has_capability('moodle/course:update', \context_course::instance($COURSE->id)) && !\block_panopto\eula::has_signed($USER->id)) {
            $url = new \moodle_url('/blocks/panopto/eula.php', array(
                'course' => $COURSE->id
            ));
            $link = \html_writer::link($url, 'sign the terms and conditions');
            $this->content->text .= "You must {$link}.";

            return $this->content;
        }

        // Just return a status message if there is one.
        if (!empty($CFG->block_panopto_status_message)) {
            $this->content->text .= \html_writer::div($CFG->block_panopto_status_message, '', array(
                'id' => 'panopto-status'
            ));
        }

        $this->content->text .= '<div id="panopto-text">Please Wait</div>';

        return $this->content;
    }

    /**
     * Generate HTML for block contents.
     */
    public function get_ajax_content() {
        global $CFG, $COURSE, $PAGE, $USER, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $context = \context_course::instance($COURSE->id, \MUST_EXIST);
        $hascreator = has_capability('block/panopto:panoptocreator', $context);
        $hasviewer = has_capability('block/panopto:panoptoviewer', $context);

        $cache = \cache::make('block_panopto', 'blockdata');
        $cachekey = "data_{$COURSE->id}_{$hascreator}_{$hasviewer}";
        $this->content = $cache->get($cachekey);
        if ($this->content) {
            return $this->content;
        }

        $permstr = '';
        if ($hascreator) {
            $permstr = get_string('access_status_creator', 'block_panopto');
        } else if ($hasviewer) {
            $permstr = get_string('access_status_viewer', 'block_panopto');
        } else {
            $permstr = get_string('access_status_none', 'block_panopto');
        }

        // Sync role mapping. In case this is the first time block is running we need to load old settings from db.
        // They will be the default values if this is the first time running.
        $mapping = panopto_data::get_course_role_mappings($COURSE->id);
        self::set_course_role_permissions($COURSE->id, $mapping['publisher'], $mapping['creator']);

        $this->content = new \stdClass();
        $this->content->text = "";
        $this->content->footer = "";

        // Construct the Panopto data proxy object.
        $panoptodata = new panopto_data($COURSE->id);
        if (empty($panoptodata->servername) || empty($panoptodata->instancename) || empty($panoptodata->applicationkey)) {
            if (!$panoptodata->default_provision()) {
                $this->content->text = 'Could not auto-provision course.';
                return $this->content;
            }

            // Start again.
            $panoptodata = new panopto_data($COURSE->id);
        }

        try {
            if (!$panoptodata->sessiongroupid) {
                $this->content->text = get_string('no_course_selected', 'block_panopto');
            } else {
                // Get course info from SOAP service.
                $courseinfo = $panoptodata->get_course();

                // Panopto course was deleted, or an exception was thrown while retrieving course data.
                if ($courseinfo->Access == "Error") {
                    $this->content->text .= "<span class='error'>" . get_string('error_retrieving', 'block_panopto') . "</span>";
                } else {
                    // Kent Change.
                    $this->content->text .= "<div id='panopto_perm_state'>$permstr</div>";
                    // End Kent Change.

                    // SSO form passes instance name in POST to keep URLs portable.
                    $this->content->text .= "
                        <form name='SSO' method='post'>
                            <input type='hidden' name='instance' value='$panoptodata->instancename' />
                        </form>";

                    $this->content->text .= '<div><b>' . get_string('live_sessions', 'block_panopto') . '</b></div>';
                    $livesessions = $panoptodata->get_live_sessions();
                    if (!empty($livesessions)) {
                        $i = 0;
                        foreach ($livesessions as $livesession) {
                            // Alternate gray background for readability.
                            $altclass = ($i % 2) ? "listItemAlt" : "";

                            $livesessiondisplayname = s($livesession->Name);
                            $this->content->text .= "<div class='listItem $altclass'>
                            $livesessiondisplayname
                                                         <span class='nowrap'>
                                                            [<a href='javascript:M.local_panopto.launchNotes(\"$livesession->LiveNotesURL\")'
                                                                >" . get_string('take_notes', 'block_panopto') . '</a>]';
                            if ($livesession->BroadcastViewerURL) {
                                $this->content->text .= "[<a href='$livesession->BroadcastViewerURL' onclick='return M.local_panopto.startSSO(this)'>"
                                        . get_string('watch_live', 'block_panopto') . '</a>]';
                            }
                            $this->content->text .= "
                                                         </span>
                                                    </div>";
                            $i++;
                        }
                    } else {
                        $this->content->text .= '<div class="listItem">'
                                . get_string('no_live_sessions', 'block_panopto') . '</div>';
                    }

                    $this->content->text .= "<div class='sectionHeader'><b>"
                            . get_string('completed_recordings', 'block_panopto') . '</b></div>';
                    $completeddeliveries = $panoptodata->get_completed_deliveries();
                    if (!empty($completeddeliveries)) {
                        $i = 0;
                        foreach ($completeddeliveries as $completeddelivery) {
                            // Collapse to 3 lectures by default.
                            if ($i == 3) {
                                $this->content->text .= "<div id='hiddenLecturesDiv'>";
                            }

                            // Alternate gray background for readability.
                            $altclass = ($i % 2) ? "listItemAlt" : "";

                            $completeddeliverydisplayname = s($completeddelivery->DisplayName);
                            $this->content->text .= "<div class='listItem $altclass'>
                                                        <a href='$completeddelivery->ViewerURL' onclick='return M.local_panopto.startSSO(this)'>
                                                        $completeddeliverydisplayname
                                                        </a>
                                                    </div>";
                            $i++;
                        }

                        // If some lectures are hidden, display "Show all" link.
                        if ($i > 3) {
                            $this->content->text .= "</div>";
                            $this->content->text .= "<div id='showAllDiv'>";
                            $this->content->text .= "[<a id='showAllToggle' href='javascript:M.local_panopto.toggleHiddenLectures()'>"
                                    . get_string('show_all', 'block_panopto') . '</a>]';
                            $this->content->text .= "</div>";
                        }
                    } else {
                        $this->content->text .= "<div class='listItem'>" . get_string('no_completed_recordings', 'block_panopto') . '</div>';
                    }

                    if ($courseinfo->AudioPodcastURL) {
                        $this->content->text .= "<div class='sectionHeader'><b>" . get_string('podcast_feeds', 'block_panopto') . "</b></div>
                                                 <div class='listItem'>
                                                    <img src='$CFG->wwwroot/blocks/panopto/images/feed_icon.gif' />
                                                    <a href='$courseinfo->AudioPodcastURL'>" . get_string('podcast_audio', 'block_panopto') . "</a>
                                                    <span class='rssParen'>(</span
                                                        ><a href='$courseinfo->AudioRssURL' target='_blank' class='rssLink'>RSS</a
                                                    ><span class='rssParen'>)</span>
                                                 </div>";
                        if ($courseinfo->VideoPodcastURL) {
                            $this->content->text .= "
                                                 <div class='listItem'>
                                                    <img src='$CFG->wwwroot/blocks/panopto/images/feed_icon.gif' /> 
                                                    <a href='$courseinfo->VideoPodcastURL'>" . get_string('podcast_video', 'block_panopto') . "</a>
                                                    <span class='rssParen'>(</span
                                                        ><a href='$courseinfo->VideoRssURL' target='_blank' class='rssLink'>RSS</a
                                                    ><span class='rssParen'>)</span>
                                                 </div>";
                        }
                    }

                    if (has_capability('moodle/course:update', $context)) {
                        $this->content->text .= "<div class='sectionHeader'><b>" . get_string('links', 'block_panopto') . "</b></div>
                                                 <div class='listItem'>
                                                    <a href='$courseinfo->CourseSettingsURL' onclick='return M.local_panopto.startSSO(this)'
                                                        >" . get_string('course_settings', 'block_panopto') . "</a>
                                                 </div>\n";
                        $systeminfo = $panoptodata->get_system_info();
                        $this->content->text .= "<div class='listItem'>
                                                    " . get_string('download_recorder', 'block_panopto') . "
                                                        <span class='nowrap'>
                                                            (<a href='$systeminfo->RecorderDownloadUrl'>Windows</a>
                                                            | <a href='$systeminfo->MacRecorderDownloadUrl'>Mac</a>)</span>
                                                </div>";
                    }
                }
            }
        } catch (Exception $e) {
            $this->content->text .= "<br><br><span class='error'>" . get_string('error_retrieving', 'block_panopto') . "</span>";
        }

        // KENT
        $this->content->text .= "<span class='panoptoextras'>";
        if (has_capability('moodle/course:update', $context)) {
            $this->content->text .= "<span class='panoptohelp'>" . $OUTPUT->help_icon('help_staff', 'block_panopto') . "</span>";
        } else {
            $this->content->text .= "<span class='panoptohelp'>" .$OUTPUT->help_icon('help_student', 'block_panopto') . "</span>";
        }
        $this->content->text .= "<span class='panoptoterms'>" . $OUTPUT->help_icon('help_terms', 'block_panopto', get_string('terms_link_title', 'block_panopto')) . "</span>"; 
        $this->content->text .= "</span>";
        // END KENT

        $this->content->footer = '';

        $cache->set($cachekey, $this->content);

        return $this->content;
    }

    /**
     * Return applicable formats
     */
    public function applicable_formats() {
        return array(
            'my' => false,
            'all' => true
        );
    }

    /**
     * Gives selected capabilities to specified roles.
     */
    public function set_course_role_permissions($courseid, $publisherroles, $creatorroles) {
        $coursecontext = context_course::instance($courseid);

        // Clear capabilities from all of course's roles to be reassigned.
        self::clear_capabilities_for_course($courseid);

        foreach ($publisherroles as $role) {
            if (isset($role) && trim($role)!=='' ){
                assign_capability('block/panopto:provision_aspublisher', CAP_ALLOW, $role, $coursecontext, $overwrite = false);
            }

        }
        foreach ($creatorroles as $role) {
            if (isset($role) && trim($role)!=='' ){
                assign_capability('block/panopto:provision_asteacher', CAP_ALLOW, $role, $coursecontext, $overwrite = false);
                }
        }
        // Mark dirty (moodle standard for capability changes at context level).
        $coursecontext->mark_dirty();

        panopto_data::set_course_role_mappings($courseid, $publisherroles, $creatorroles);
    }

    /**
     * Clears capabilities from all roles so that they may be reassigned as specified.
     */
    public function clear_capabilities_for_course($courseid) {
        $coursecontext = context_course::instance($courseid);

        // Get all roles for current course.
        $currentcourseroles = get_all_roles($coursecontext);

        // Remove publisher and creator capabilities from all roles.
        foreach ($currentcourseroles as $role) {
            unassign_capability('block/panopto:provision_aspublisher', $role->id, $coursecontext);
            unassign_capability('block/panopto:provision_asteacher', $role->id, $coursecontext);
            // Mark dirty (moodle standard for capability changes at context level).
            $coursecontext->mark_dirty();
        }
    }

}

// End of block_panopto.php.

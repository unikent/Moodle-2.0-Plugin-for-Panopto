<?php
/* Copyright Panopto 2009 - 2011 / With contributions from Spenser Jones (sjones@ambrose.edu)
 * 
 * This file is part of the Panopto plugin for Moodle.
 * 
 * The Panopto plugin for Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * The Panopto plugin for Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with the Panopto plugin for Moodle.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once("lib/panopto_data.php");
require_once (dirname(__FILE__) . '/../../lib/accesslib.php');

class block_panopto extends block_base {
    var $blockname = "panopto";

    // Set system properties of plugin.
    function init() {
        $this->title = get_string('pluginname', 'block_panopto');
    }

    /**
     * Do we have the right grants?
     */
    private function has_access() {
        global $DB, $USER;

        $ar = \block_panopto\util::get_role('panopto_academic');
        $ar_check = !empty($ar) ? user_has_role_assignment($USER->id, $ar->id, context_system::instance()->id) : false;

        $nar = \block_panopto\util::get_role('panopto_non_academic');
        $nar_check = !empty($nar) ? user_has_role_assignment($USER->id, $nar->id, context_system::instance()->id) : false;

        return $ar_check || $nar_check;
    }

    /**
     * Required JS
     */
    public function get_required_javascript() {
        parent::get_required_javascript();

        global $COURSE, $CFG;

        $perm_str = '';
        $role_assign_bool = false;

        $this->page->requires->string_for_js('show_all', 'block_panopto');
        $this->page->requires->string_for_js('show_less', 'block_panopto');

        $this->page->requires->string_for_js('ajax_json_error', 'block_panopto');
        $this->page->requires->string_for_js('ajax_data_error', 'block_panopto');
        $this->page->requires->string_for_js('ajax_failure', 'block_panopto');
        $this->page->requires->string_for_js('ajax_busy', 'block_panopto');
        
        $this->page->requires->string_for_js('error', 'block_panopto');

        if ($CFG->kent->distribution !== "2012") {
            $role_assign_bool = $this->has_access();

            $context = context_course::instance($COURSE->id, MUST_EXIST);
            $hasCreator = has_capability('block/panopto:panoptocreator', $context);
            $hasViewer = has_capability('block/panopto:panoptoviewer', $context);

            if ($role_assign_bool && $hasCreator) {
                $perm_str = get_string('access_status_creator', 'block_panopto');
            } elseif ($hasCreator && $this->page->user_is_editing()) {
                $perm_str = get_string('access_status_tcs', 'block_panopto') . ' <div id="panopto_ts_button">'.get_string('access_status_tcs_btn', 'block_panopto').'</div>';
            } elseif ($hasViewer) {
                $perm_str = get_string('access_status_viewer', 'block_panopto');
            } else {
                $perm_str = get_string('access_status_none', 'block_panopto');
            }

            if ($this->page->user_is_editing()) {
                // We need jQuery!
                $this->page->requires->jquery();
                $this->page->requires->jquery_plugin('migrate');
                $this->page->requires->jquery_plugin('ui');

                // Need some langs..
                $this->page->requires->string_for_js('role_choice_head', 'block_panopto');
                $this->page->requires->string_for_js('role_choice_ac_btn', 'block_panopto');
                $this->page->requires->string_for_js('role_choice_nac_btn', 'block_panopto');
                $this->page->requires->string_for_js('role_choice_cancel', 'block_panopto');
                $this->page->requires->string_for_js('terms_head', 'block_panopto');
                $this->page->requires->string_for_js('terms_back_btn', 'block_panopto');
                $this->page->requires->string_for_js('terms_agree_btn', 'block_panopto');
                $this->page->requires->string_for_js('terms_decline_btn', 'block_panopto');
                $this->page->requires->string_for_js('accademic_terms', 'block_panopto');
                $this->page->requires->string_for_js('non_accademic_terms', 'block_panopto');
                $this->page->requires->string_for_js('success_roleassign', 'block_panopto');
                $this->page->requires->string_for_js('success_sync_succ', 'block_panopto');
                $this->page->requires->string_for_js('success_sync_fail', 'block_panopto');
                $this->page->requires->string_for_js('success_extras', 'block_panopto');

                // Add in our JS
                $this->page->requires->js('/blocks/panopto/js/underscore-min.js');
                $this->page->requires->js('/blocks/panopto/js/panopto_init.js');
                $this->page->requires->js('/blocks/panopto/js/panopto_tac.js');
            }
        }

        // Finally, the init call
        $this->page->requires->js_init_call('M.local_panopto.init', array($COURSE->id, $perm_str, $role_assign_bool, $this->page->user_is_editing()), false, array(
            'name' => 'local_panopto',
            'fullpath' => '/blocks/panopto/js/ajax.js',
            'requires' => array("node", "io", "dump", "json-parse")
        ));
    }

    // Block has global config (display "Settings" link on blocks admin page)
    function has_config() {
        return true;
    }

    // Save global block data in mdl_config_plugins table instead of global CFG variable
    function config_save($data) {
        foreach ($data as $name => $value) {
            set_config($name, trim($value), $this->blockname);
        }
        return true;
    }
     
    // Block has per-instance config (display edit icon in block header)
    function instance_allow_config() {
        return true;
    }

    // Save per-instance config in custom table instead of mdl_block_instance configdata column
    function instance_config_save($data, $nolongerused = false) {
        global $DB, $COURSE;

        if (!empty($data->course)) {
            require_once(dirname(__FILE__) . '/lib/panopto_data.php');

            // Mark the course for update.
            if (!$DB->record_exists('block_panopto_updates', array('courseid' => $COURSE->id))) {
                $DB->insert_record('block_panopto_updates', array(
                    "courseid" => $COURSE->id
                ));
            }

            // We are the master of this installation if nothing preceeds us.
            $master = !$DB->record_exists('block_panopto_foldermap', array(
                'panopto_id' => $data->course,
                'master' => 1
            ));

            return panopto_data::set_panopto_course_id($COURSE->id, $data->course, $master);
        }

        // If server is not set globally, there will be no other form values to push into config.
        return true;
    }

    // Generate HTML for block contents
    function get_content() {
        global $CFG, $COURSE;

        $this->content = new stdClass();
        $this->content->text = "";
        $this->content->footer = "";

        // Just return a status message if there is one.
        if (!empty($CFG->block_panopto_status_message)) {
            $this->content->text .= "<div id=\"panopto-status\">$CFG->block_panopto_status_message</div>";
        }

        $this->content->text .= '<div id="panopto-text">Please Wait</div>';
        $this->content->footer .= '<div id="panopto-footer"></div>';

        return $this->content;
    }
    
    function applicable_formats() {
        return array(
            'my' => false,
            'all' => true
        );
    }
    
    //Gives selected capabilities to specified roles
    function set_course_role_permissions($courseid, $publisher_roles, $creator_roles){
        $course_context = context_course::instance($courseid);
        
        //clear capabilities from all of course's roles to be reassigned
        block_panopto::clear_capabilities_for_course($courseid);

        foreach($publisher_roles as $role){
         assign_capability('block/panopto:provision_aspublisher', CAP_ALLOW, $role, $course_context, $overwrite = false);
        }      
         foreach($creator_roles as $role){
            assign_capability('block/panopto:provision_asteacher', CAP_ALLOW, $role, $course_context, $overwrite = false);
        }
        //Mark dirty (moodle standard for capability changes at context level)
        $course_context->mark_dirty();
     
        panopto_data::set_course_role_mappings($courseid, $publisher_roles, $creator_roles);  
    }
    
    //Clears capabilities from all roles so that they may be reassigned as specified
    function clear_capabilities_for_course($courseid){
        $course_context = context_course::instance($courseid);
        
        //Get all roles for current course
        $current_course_roles = get_all_roles($course_context);
        
        //remove publisher and creator capabilities from all roles
        foreach($current_course_roles as $role){
            unassign_capability( 'block/panopto:provision_aspublisher', $role->id, $course_context);
            unassign_capability( 'block/panopto:provision_asteacher', $role->id, $course_context); 
            //Mark dirty (moodle standard for capability changes at context level)
            $course_context->mark_dirty();            
        }
    }
}
// End of block_panopto.php
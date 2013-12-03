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

class block_panopto extends block_base {
    var $blockname = "panopto";

    // Set system properties of plugin.
    function init() {
        $this->title = get_string('pluginname', 'block_panopto');
    }

    /**
     * Required JS
     */
    public function get_required_javascript() {
        parent::get_required_javascript();

        global $COURSE;
        $this->page->requires->js_init_call('M.local_panopto.init', array($COURSE->id), false, array(
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
        global $COURSE;

        if (!empty($data->course)) {
            require_once(dirname(__FILE__) . '/lib/panopto_data.php');
            return panopto_data::set_panopto_course_id($COURSE->id, $data->course);
        }

        // If server is not set globally, there will be no other form values to push into config.
        return true;
    }

    // Generate HTML for block contents
    function get_content() {
        $this->content = new stdClass();
        $this->content->text = '<div id="panopto-text">Please Wait</div>';
        $this->content->footer = '<div id="panopto-footer"></div>';
        return $this->content;
    }
    
    function applicable_formats() {
        return array(
            'my' => false,
            'all' => true
        );
    }
}
// End of block_panopto.php
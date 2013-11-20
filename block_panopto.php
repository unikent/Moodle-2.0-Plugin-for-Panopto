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

class block_panopto extends block_base {
    var $blockname = "panopto";

    // Set system properties of plugin.
    function init() {
        $this->title = get_string('pluginname', 'block_panopto');
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

        if(!empty($data->course)) {
            return panopto_data::set_panopto_course_id($COURSE->id, $data->course);
        } else {
            // If server is not set globally, there will be no other form values to push into config.
            return true;
        }
    }

    // Generate HTML for block contents
    function get_content() {
        // Kent Change
        global $CFG, $DB, $COURSE, $USER, $OUTPUT;
        $context = context_course::instance($COURSE->id, MUST_EXIST);
        // End Change

        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;

        // Construct the Panopto data proxy object
        $panopto_data = new panopto_data($COURSE->id);

        if(empty($panopto_data->servername) || empty($panopto_data->instancename) || empty($panopto_data->applicationkey)) {
            $this->content->text = get_string('unconfigured', 'block_panopto');
            $this->content->footer = "";
            	
            return $this->content;
        }

        try {
            if(!$panopto_data->sessiongroup_id) {
                $this->content->text .= get_string('no_course_selected', 'block_panopto');
            } else {
                // Get course info from SOAP service.
                $course_info = $panopto_data->get_course();

                // Panopto course was deleted, or an exception was thrown while retrieving course data.
                if($course_info->Access == "Error") {
                    $this->content->text .= "<span class='error'>" . get_string('error_retrieving', 'block_panopto') . "</span>";
                } else {
                    // Kent Change
                    // (Override for 2012)
                    if ($CFG->kent->distribution !== "2012") {
                        $ar = $DB->get_record('role', array('shortname' => 'panopto_academic'));
                        $nar = $DB->get_record('role', array('shortname' => 'panopto_non_academic'));

                        $role_assign_bool = (user_has_role_assignment($USER->id, $ar->id, context_system::instance()->id) || user_has_role_assignment($USER->id, $nar->id, context_system::instance()->id));

                        if($role_assign_bool && has_capability('block/panopto:panoptocreator', $context)) {
                            $perm_str = get_string('access_status_creator', 'block_panopto');
                        } elseif (has_capability('block/panopto:panoptocreator', $context) && $this->page->user_is_editing()) {
                            $this->content->text .= '<script type="text/javascript">
                                window.courseId = ' . $COURSE->id .';
                                window.role_choice_head = "'.get_string('role_choice_head', 'block_panopto').'";
                                window.role_choice_ac_btn = "'.get_string('role_choice_ac_btn', 'block_panopto').'";
                                window.role_choice_nac_btn = "'.get_string('role_choice_nac_btn', 'block_panopto').'";
                                window.role_choice_cancel = "'.get_string('role_choice_cancel', 'block_panopto').'";
                                window.terms_head = "'.get_string('terms_head', 'block_panopto').'";
                                window.terms_back_btn = "'.get_string('terms_back_btn', 'block_panopto').'";
                                window.terms_agree_btn = "'.get_string('terms_agree_btn', 'block_panopto').'";
                                window.terms_decline_btn = "'.get_string('terms_decline_btn', 'block_panopto').'";
                                window.accademic_terms = "'.str_replace(array("\r", "\n"), '', get_string('accademic_terms', 'block_panopto')).'";
                                window.non_accademic_terms = "'.str_replace(array("\r", "\n"), '', get_string('non_accademic_terms', 'block_panopto')).'";
                                window.success_roleassign= "'.get_string('success_roleassign', 'block_panopto').'";
                                window.success_sync_succ= "'.get_string('success_sync_succ', 'block_panopto').'";
                                window.success_sync_fail= "'.get_string('success_sync_fail', 'block_panopto').'";
                                window.success_extras= "'.get_string('success_extras', 'block_panopto').'";
                                window.error= "'.get_string('error', 'block_panopto').'";
                            </script>';
                            $this->content->text .= '<script src="'.$CFG->wwwroot.'/blocks/panopto/js/underscore-min.js" type="text/javascript"></script>';
                            $this->content->text .= '<script src="'.$CFG->wwwroot.'/blocks/panopto/js/panopto_init.js" type="text/javascript"></script>';                        
                            $perm_str = get_string('access_status_tcs', 'block_panopto') . ' <a id="panopto_ts_button" href="#">'.get_string('access_status_tcs_btn', 'block_panopto').'</a>';
                        } elseif (has_capability('block/panopto:panoptoviewer', $context)) {
                            $perm_str = get_string('access_status_viewer', 'block_panopto');
                        } else {
                            $perm_str = get_string('access_status_none', 'block_panopto');
                        }

                        $this->content->text .= "<div id='panopto_perm_state'>$perm_str</div>";
                    } else {
                        $role_assign_bool = true;
                    }
                    // End Kent Change

                    // SSO form passes instance name in POST to keep URLs portable.
                    $this->content->text .= "
		        		<form name='SSO' method='post'>
							<input type='hidden' name='instance' value='$panopto_data->instancename' />
						</form>";
                     
                    $this->content->text .= '<div><b>' . get_string('live_sessions', 'block_panopto') . '</b></div>';
                    $live_sessions = $panopto_data->get_live_sessions();
                    if(!empty($live_sessions)) {
                        $i = 0;
                        foreach($live_sessions as $live_session) {
                            // Alternate gray background for readability.
                            $altClass = ($i % 2) ? "listItemAlt" : "";
                             
                            $live_session_display_name = s($live_session->Name);
                            $this->content->text .= "<div class='listItem $altClass'>
                            $live_session_display_name
														 <span class='nowrap'>
														 	[<a href='javascript:panopto_launchNotes(\"$live_session->LiveNotesURL\")'
														 		>" . get_string('take_notes', 'block_panopto') . '</a>]';
                            if($live_session->BroadcastViewerURL) {
                                $this->content->text .= "[<a href='$live_session->BroadcastViewerURL' onclick='return panopto_startSSO(this)'>" . get_string('watch_live', 'block_panopto') . '</a>]';
                            }
                            $this->content->text .= "
												 	  	 </span>
													</div>";
                            $i++;
                        }
                    } else {
                        $this->content->text .= '<div class="listItem">' . get_string('no_live_sessions', 'block_panopto') . '</div>';
                    }
                     
                    $this->content->text .= "<div class='sectionHeader'><b>" . get_string('completed_recordings', 'block_panopto') . '</b></div>';
                    $completed_deliveries = $panopto_data->get_completed_deliveries();
                    if(!empty($completed_deliveries)) {
                        $i = 0;
                        foreach($completed_deliveries as $completed_delivery) {
                            // Collapse to 3 lectures by default
                            if($i == 3) {
                                $this->content->text .= "<div id='hiddenLecturesDiv'>";
                            }
                            	
                            // Alternate gray background for readability.
                            $altClass = ($i % 2) ? "listItemAlt" : "";
                             
                            $completed_delivery_display_name = s($completed_delivery->DisplayName);
                            $this->content->text .= "<div class='listItem $altClass'>
					        							<a href='$completed_delivery->ViewerURL' onclick='return panopto_startSSO(this)'>
					        							$completed_delivery_display_name
					        							</a>
				        							</div>";
					        							$i++;
                        }

                        // If some lectures are hidden, display "Show all" link.
                        if($i > 3) {
                            $this->content->text .= "</div>";
                            $this->content->text .= "<div id='showAllDiv'>";
                            $this->content->text .= "[<a id='showAllToggle' href='javascript:panopto_toggleHiddenLectures()'>" . get_string('show_all', 'block_panopto') . '</a>]';
                            $this->content->text .= "</div>";
                        }
                    } else {
                        $this->content->text .= "<div class='listItem'>" . get_string('no_completed_recordings', 'block_panopto') . '</div>';
                    }
                     
                    if($course_info->AudioPodcastURL) {
                        $this->content->text .= "<div class='sectionHeader'><b>" . get_string('podcast_feeds', 'block_panopto') . "</b></div>
				        						 <div class='listItem'>
				        						 	<img src='$CFG->wwwroot/blocks/panopto/images/feed_icon.gif' />
				        							<a href='$course_info->AudioPodcastURL'>" . get_string('podcast_audio', 'block_panopto') . "</a>
				        							<span class='rssParen'>(</span
				        								><a href='$course_info->AudioRssURL' target='_blank' class='rssLink'>RSS</a
			        								><span class='rssParen'>)</span>
		                        				 </div>";
                        if($course_info->VideoPodcastURL) {
                            $this->content->text .= "
				        						 <div class='listItem'>
			        								<img src='$CFG->wwwroot/blocks/panopto/images/feed_icon.gif' />	
				        						 	<a href='$course_info->VideoPodcastURL'>" . get_string('podcast_video', 'block_panopto') . "</a>
				        							<span class='rssParen'>(</span
				        								><a href='$course_info->VideoRssURL' target='_blank' class='rssLink'>RSS</a
			        								><span class='rssParen'>)</span>
		                        				 </div>";
                        }
                    }
                    // Kent Change
                    if(has_capability('moodle/course:update', $context) && $role_assign_bool) {
                    // End Change
                        $this->content->text .= "<div class='sectionHeader'><b>" . get_string('links', 'block_panopto') . "</b></div>
				        						 <div class='listItem'>
				        							<a href='$course_info->CourseSettingsURL' onclick='return panopto_startSSO(this)'
				        								>" . get_string('course_settings', 'block_panopto') . "</a>
			        							 </div>\n";
                        $system_info = $panopto_data->get_system_info();
                        $this->content->text .= "<div class='listItem'>
				        							" . get_string('download_recorder', 'block_panopto') . "
					        							<span class='nowrap'>
					        								(<a href='$system_info->RecorderDownloadUrl'>Windows</a>
								   							| <a href='$system_info->MacRecorderDownloadUrl'>Mac</a>)</span>
			        							</div>";
                    }
                     
                    $this->content->text .= '
						<script type="text/javascript">
			        // Function to pop up Panopto live note taker.
			        function panopto_launchNotes(url) {
						// Open empty notes window, then POST SSO form to it.
						var notesWindow = window.open("", "PanoptoNotes", "width=500,height=800,resizable=1,scrollbars=0,status=0,location=0");
						document.SSO.action = url;
						document.SSO.target = "PanoptoNotes";
						document.SSO.submit();
			
						// Ensure the new window is brought to the front of the z-order.
						notesWindow.focus();
					}
							
					function panopto_startSSO(linkElem) {
						document.SSO.action = linkElem.href;
						document.SSO.target = "_blank";
						document.SSO.submit();
								
						// Cancel default link navigation.
					  	return false;
					}
					  		
					function panopto_toggleHiddenLectures() {
					  	var showAllToggle = document.getElementById("showAllToggle");
					  	var hiddenLecturesDiv = document.getElementById("hiddenLecturesDiv");
					  			
					  	if(hiddenLecturesDiv.style.display == "block") {
					  		hiddenLecturesDiv.style.display = "none";
					  		showAllToggle.innerHTML = "' . get_string('show_all', 'block_panopto') . '";
					  	} else {
					  	hiddenLecturesDiv.style.display = "block";
					  	showAllToggle.innerHTML = "' . get_string('show_less', 'block_panopto') . '";
					}
				}
				</script>';
              }
           }
        }
        catch(Exception $e) {
            $this->content->text .= "<br><br><span class='error'>" . get_string('error_retrieving', 'block_panopto') . "</span>";
        }

        // Kent Change
        $this->content->text .= "<span class='panoptoextras'>";
        $context = context_course::instance($COURSE->id);
        if(has_capability('moodle/course:update', $context)) {
            $this->content->text .= "<span class='panoptohelp'>" . $OUTPUT->help_icon('help_staff', 'block_panopto') . "</span>";
        } else {
            $this->content->text .= "<span class='panoptohelp'>" .$OUTPUT->help_icon('help_student', 'block_panopto') . "</span>";
        }
        $this->content->text .= "<span class='panoptoterms'>" . $OUTPUT->help_icon('help_terms', 'block_panopto', get_string('terms_link_title', 'block_panopto')) . "</span>"; 
        $this->content->text .= "</span>";

        if($this->page->user_is_editing()) {
            $params = new stdClass;
            $params->course_id = $COURSE->id;
            $params->return_url = $_SERVER['REQUEST_URI'];
            $query_string = http_build_query($params, '', '&');
            $reprovision = get_string('reprovision', 'block_panopto');
            $provision_url = "$CFG->wwwroot/blocks/panopto/provision_course.php?" . $query_string;
            $this->content->footer .= "<a class='reprovision' href='$provision_url'>$reprovision</a>";
        } else {
            $this->content->footer = ' ';
        }
        // End Change

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
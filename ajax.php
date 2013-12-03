<?php

define('AJAX_SCRIPT', true);

/** Include config */
require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/lib/panopto_data.php');

require_sesskey();
$courseid = required_param('courseid', PARAM_INTEGER);

global $CFG, $DB, $USER, $OUTPUT;
$context = context_course::instance($courseid, MUST_EXIST);

$content = new stdClass;

// Construct the Panopto data proxy object
$panopto_data = new panopto_data($courseid);

if(empty($panopto_data->servername) || empty($panopto_data->instancename) || empty($panopto_data->applicationkey)) {
    $content->text = get_string('unconfigured', 'block_panopto');
    $content->footer = "";
    	
    return $content;
}

try {
    if(!$panopto_data->sessiongroup_id) {
        $content->text .= get_string('no_course_selected', 'block_panopto');
    } else {
        // Get course info from SOAP service.
        $course_info = $panopto_data->get_course();

        // Panopto course was deleted, or an exception was thrown while retrieving course data.
        if($course_info->Access == "Error") {
            $content->text .= "<span class='error'>" . get_string('error_retrieving', 'block_panopto') . "</span>";
        } else {
            // Kent Change
            // (Override for 2012)
            if ($CFG->kent->distribution !== "2012") {
                $ar = $DB->get_record('role', array('shortname' => 'panopto_academic'));
                $nar = $DB->get_record('role', array('shortname' => 'panopto_non_academic'));

                $role_assign_bool = (user_has_role_assignment($USER->id, $ar->id, context_system::instance()->id) || user_has_role_assignment($USER->id, $nar->id, context_system::instance()->id));

                if($role_assign_bool && has_capability('block/panopto:panoptocreator', $context)) {
                    $perm_str = get_string('access_status_creator', 'block_panopto');
                } elseif (has_capability('block/panopto:panoptocreator', $context) && $PAGE->user_is_editing()) {
                    $content->text .= '<script type="text/javascript">
                        window.courseId = ' . $courseid .';
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
                    $content->text .= '<script src="'.$CFG->wwwroot.'/blocks/panopto/js/underscore-min.js" type="text/javascript"></script>';
                    $content->text .= '<script src="'.$CFG->wwwroot.'/blocks/panopto/js/panopto_init.js" type="text/javascript"></script>';                        
                    $perm_str = get_string('access_status_tcs', 'block_panopto') . ' <a id="panopto_ts_button" href="#">'.get_string('access_status_tcs_btn', 'block_panopto').'</a>';
                } elseif (has_capability('block/panopto:panoptoviewer', $context)) {
                    $perm_str = get_string('access_status_viewer', 'block_panopto');
                } else {
                    $perm_str = get_string('access_status_none', 'block_panopto');
                }

                $content->text .= "<div id='panopto_perm_state'>$perm_str</div>";
            } else {
                $role_assign_bool = true;
            }
            // End Kent Change

            // SSO form passes instance name in POST to keep URLs portable.
            $content->text .= "
        		<form name='SSO' method='post'>
					<input type='hidden' name='instance' value='$panopto_data->instancename' />
				</form>";
             
            $content->text .= '<div><b>' . get_string('live_sessions', 'block_panopto') . '</b></div>';
            $live_sessions = $panopto_data->get_live_sessions();
            if(!empty($live_sessions)) {
                $i = 0;
                foreach($live_sessions as $live_session) {
                    // Alternate gray background for readability.
                    $altClass = ($i % 2) ? "listItemAlt" : "";
                     
                    $live_session_display_name = s($live_session->Name);
                    $content->text .= "<div class='listItem $altClass'>
                    $live_session_display_name
												 <span class='nowrap'>
												 	[<a href='javascript:panopto_launchNotes(\"$live_session->LiveNotesURL\")'
												 		>" . get_string('take_notes', 'block_panopto') . '</a>]';
                    if($live_session->BroadcastViewerURL) {
                        $content->text .= "[<a href='$live_session->BroadcastViewerURL' onclick='return panopto_startSSO(this)'>" . get_string('watch_live', 'block_panopto') . '</a>]';
                    }
                    $content->text .= "
										 	  	 </span>
											</div>";
                    $i++;
                }
            } else {
                $content->text .= '<div class="listItem">' . get_string('no_live_sessions', 'block_panopto') . '</div>';
            }
             
            $content->text .= "<div class='sectionHeader'><b>" . get_string('completed_recordings', 'block_panopto') . '</b></div>';
            $completed_deliveries = $panopto_data->get_completed_deliveries();
            if(!empty($completed_deliveries)) {
                $i = 0;
                foreach($completed_deliveries as $completed_delivery) {
                    // Collapse to 3 lectures by default
                    if($i == 3) {
                        $content->text .= "<div id='hiddenLecturesDiv'>";
                    }
                    	
                    // Alternate gray background for readability.
                    $altClass = ($i % 2) ? "listItemAlt" : "";
                     
                    $completed_delivery_display_name = s($completed_delivery->DisplayName);
                    $content->text .= "<div class='listItem $altClass'>
			        							<a href='$completed_delivery->ViewerURL' onclick='return panopto_startSSO(this)'>
			        							$completed_delivery_display_name
			        							</a>
		        							</div>";
			        							$i++;
                }

                // If some lectures are hidden, display "Show all" link.
                if($i > 3) {
                    $content->text .= "</div>";
                    $content->text .= "<div id='showAllDiv'>";
                    $content->text .= "[<a id='showAllToggle' href='javascript:panopto_toggleHiddenLectures()'>" . get_string('show_all', 'block_panopto') . '</a>]';
                    $content->text .= "</div>";
                }
            } else {
                $content->text .= "<div class='listItem'>" . get_string('no_completed_recordings', 'block_panopto') . '</div>';
            }
             
            if($course_info->AudioPodcastURL) {
                $content->text .= "<div class='sectionHeader'><b>" . get_string('podcast_feeds', 'block_panopto') . "</b></div>
		        						 <div class='listItem'>
		        						 	<img src='$CFG->wwwroot/blocks/panopto/images/feed_icon.gif' />
		        							<a href='$course_info->AudioPodcastURL'>" . get_string('podcast_audio', 'block_panopto') . "</a>
		        							<span class='rssParen'>(</span
		        								><a href='$course_info->AudioRssURL' target='_blank' class='rssLink'>RSS</a
	        								><span class='rssParen'>)</span>
                        				 </div>";
                if($course_info->VideoPodcastURL) {
                    $content->text .= "
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
                $content->text .= "<div class='sectionHeader'><b>" . get_string('links', 'block_panopto') . "</b></div>
		        						 <div class='listItem'>
		        							<a href='$course_info->CourseSettingsURL' onclick='return panopto_startSSO(this)'
		        								>" . get_string('course_settings', 'block_panopto') . "</a>
	        							 </div>\n";
                $system_info = $panopto_data->get_system_info();
                $content->text .= "<div class='listItem'>
		        							" . get_string('download_recorder', 'block_panopto') . "
			        							<span class='nowrap'>
			        								(<a href='$system_info->RecorderDownloadUrl'>Windows</a>
						   							| <a href='$system_info->MacRecorderDownloadUrl'>Mac</a>)</span>
	        							</div>";
            }
             
            $content->text .= '
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
    $content->text .= "<br><br><span class='error'>" . get_string('error_retrieving', 'block_panopto') . "</span>";
}

// Kent Change
$content->text .= "<span class='panoptoextras'>";
$context = context_course::instance($courseid);
if(has_capability('moodle/course:update', $context)) {
    $content->text .= "<span class='panoptohelp'>" . $OUTPUT->help_icon('help_staff', 'block_panopto') . "</span>";
} else {
    $content->text .= "<span class='panoptohelp'>" .$OUTPUT->help_icon('help_student', 'block_panopto') . "</span>";
}
$content->text .= "<span class='panoptoterms'>" . $OUTPUT->help_icon('help_terms', 'block_panopto', get_string('terms_link_title', 'block_panopto')) . "</span>"; 
$content->text .= "</span>";

if($PAGE->user_is_editing()) {
    $params = new stdClass;
    $params->course_id = $courseid;
    $params->return_url = $_SERVER['REQUEST_URI'];
    $query_string = http_build_query($params, '', '&');
    $reprovision = get_string('reprovision', 'block_panopto');
    $provision_url = "$CFG->wwwroot/blocks/panopto/provision_course.php?" . $query_string;
    $content->footer .= "<a class='reprovision' href='$provision_url'>$reprovision</a>";
} else {
    $content->footer = ' ';
}
// End Change


echo json_encode(array(
    "footer" => $content->footer,
    "text" => $content->text
));
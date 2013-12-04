M.local_panopto_tac = {
    Y : null,
    transaction : [],
    init : function(Y, courseid) {
		window.courseId = courseid;
		window.role_choice_head = M.str.block_panopto.role_choice_head;
		window.role_choice_ac_btn = M.str.block_panopto.role_choice_ac_btn;
		window.role_choice_nac_btn = M.str.block_panopto.role_choice_nac_btn;
		window.role_choice_cancel = M.str.block_panopto.role_choice_cancel;
		window.terms_head = M.str.block_panopto.terms_head;
		window.terms_back_btn = M.str.block_panopto.terms_back_btn;
		window.terms_agree_btn = M.str.block_panopto.terms_agree_btn;
		window.terms_decline_btn = M.str.block_panopto.terms_decline_btn;
		window.accademic_terms = M.str.block_panopto.accademic_terms;
		window.non_accademic_terms = M.str.block_panopto.non_accademic_terms;
		window.success_roleassign= M.str.block_panopto.success_roleassign;
		window.success_sync_succ= M.str.block_panopto.success_sync_succ;
		window.success_sync_fail= M.str.block_panopto.success_sync_fail;
		window.success_extras= M.str.block_panopto.success_extras;
		window.error= M.str.block_panopto.error;

		$('#panopto_ts_button').panoptoTac();
    }
};

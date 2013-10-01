(($, window) ->
	class PanoptoTac

		defaults:
			dialogAnch: 'body'
			dialogId: 'panoptotac_dialog'
			pageWidth: 540

		constructor: (el, options) ->

			@isOpen = false
			@options = $.extend({}, @defaults, options)
			@el = el
			@page = 0
			@isAnimating = false
			@courseId = window.courseId
			@strings = 
				role_choice_head: window.role_choice_head
				role_choice_ac_btn: window.role_choice_ac_btn
				role_choice_nac_btn: window.role_choice_nac_btn
				role_choice_cancel: window.role_choice_cancel
				terms_head: window.terms_head
				terms_back_btn: window.terms_back_btn
				terms_agree_btn: window.terms_agree_btn
				terms_decline_btn: window.terms_decline_btn
				accademic_terms: window.accademic_terms
				non_accademic_terms: window.non_accademic_terms
				success_roleassign: window.success_roleassign
				success_sync_succ: window.success_sync_succ
				success_sync_fail: window.success_sync_fail
				success_extras: window.success_extras
				error: window.error

			@setUpViews()
			@loadChoicePage()

			@el.bind click: @openDialog
			@buttonDelegates()

		setUpViews: =>
			@dialog_bg = _.template '<div id="panoptotac_dialog_overlay"></div>'
			@dialog_box = _.template "<div id='<%= dialogId %>'>
										<div class='panoptotac_wrap'>
											<a href='#' id='panoptotac_dialog_cancel'>x</a> 
											<div class='panoptotac_content_wrap'>
											</div>
											
									  	</div>
									  </div>"

			@dialog_box_content = _.template "<div class='panoptotac_page <%= clas %>'>
												<h2 class='panoptotac_head'><%= title %></h2>
												<div class='panoptotac_content'><%= content %></div>
												<div class='panoptotac_foot'><%= foot %></div>
											</div>"

			$(@options.dialogAnch).append(@dialog_bg)
			$(@options.dialogAnch).append(@dialog_box({dialogId: @options.dialogId}))
			$('.panoptotac_content_wrap').width(@options.pageWidth * 2)

			@dlgOver = $('#panoptotac_dialog_overlay')
			@dlg = $('#'+ @options.dialogId)

		buttonDelegates: =>

			$(@dlg).on 'click', '#panoptotac_dialog_cancel, .panoptotac_cancel, .panoptotac_dec', @cancelDialog
			
			$(@dlg).on 'click', '.panoptotac_choice_btn', (e)->
				e.preventDefault()
				_this.loadTermsPage @

			$(@dlg).on 'click', '.panoptotac_back', (e)=>
				e.preventDefault()
				@loadChoicePage()

			$(@dlg).on 'click', '.panoptotac_acc', @acceptTerms

			$(@dlg).on 'click', '.panoptotac_terms_box a', (e)->
				if $(@).attr('href')[0] is '#'
					e.preventDefault()
					target = $($(@).attr('href')).get(0)
					parent = $(target).closest('.panoptotac_terms_box').get(0)
					parent.scrollTop = target.offsetTop

		openDialog: (e) =>
			e.preventDefault()
			if not @isOpen
				$('body').addClass 'panoptotac_scroll_disable'
				@dlgOver.show()
				@dlg.show()
				@isOpen = true

		cancelDialog: (e) =>
			e.preventDefault()
			if @isOpen
				$('body').removeClass 'panoptotac_scroll_disable'
				@dlgOver.hide()
				@dlg.hide()
				@isOpen = false
				if @page isnt 1 then @loadChoicePage()

		loadChoicePage: =>

			if @isAnimating or @page is 1 then return
			if @page is 2
				@isAnimating = true
				$('.panoptotac_content_wrap').animate { left: 0}, 'fast', 'easeOutExpo', =>
					@isAnimating = false
					@page = 1
					$('.panoptotac_terms').remove();
			else
				dialog_tac_choice = "<a href='#' class='panoptotac_academic_staff_btn panoptotac_choice_btn' data-type='ac'>"+@strings.role_choice_ac_btn+"</a>" +
									  "<a href='#' class='panoptotac_non_academic_staff_btn panoptotac_choice_btn' data-type='nac'>"+@strings.role_choice_nac_btn+"</a>"
				dialog_tac_choice_foot = '<a href="#" class="panoptotac_cancel panoptotac_btn">'+@strings.role_choice_cancel+'</a>'
				
				$('.panoptotac_content_wrap').append(@dialog_box_content({
						clas: 'panoptotac_choice'
						title: @strings.role_choice_head
						content: dialog_tac_choice
						foot: dialog_tac_choice_foot
				}))

				@page = 1

		loadTermsPage: (button) =>
			if not @isAnimating
				@isAnimating = true
				@page = 2
				@role = $(button).data('type')
				
				dialog_tac_terms_content = "<div class='panoptotac_terms_box'>#{ if @role is 'ac' then @strings.accademic_terms else @strings.non_accademic_terms}</div>"

				dialog_tac_terms_foot = "<a href='#' class='panoptotac_back panoptotac_btn'>"+@strings.terms_back_btn+"</a>
										<div class='panoptotac_submit_wrap'>
											<a href='#' class='panoptotac_btn panoptotac_dec'>"+@strings.terms_decline_btn+"</a>
											<a href='#' class='panoptotac_btn panoptotac_acc'>"+@strings.terms_agree_btn+"</a>
										</div>"
				$('.panoptotac_content_wrap').append(@dialog_box_content({
						clas: 'panoptotac_terms'
						title: @strings.terms_head
						content: dialog_tac_terms_content
						foot: dialog_tac_terms_foot
				}))
				.animate {
					left: '-' + @options.pageWidth
				}, 'fast', 'easeOutExpo', =>
					@isAnimating = false

		acceptTerms: (e)=>
			e.preventDefault()
			__this = @

			loading = "<div class='panoptotac_loading'>Submitting</div>"

			$('.panoptotac_terms').fadeOut ->
				$('div, h2', @).html ''
				$('.panoptotac_terms .panoptotac_content').html(loading)
				$(@).fadeIn()

				$.ajax M.cfg.wwwroot+'/blocks/panopto/accept_terms.php',
					data: 
						role: __this.role
						course: __this.courseId
					success: (result)=>
						$('.panoptotac_loading', @).fadeOut()
						success = "<div class='panoptotac_success'>Success<span>" +
							"#{__this.strings.success_roleassign}" +
							"#{if result.course_provision is true then __this.strings.success_sync_succ else __this.strings.success_sync_fail}" +
							"#{__this.strings.success_extras}" +
							"</span></div>"
						$('.panoptotac_terms .panoptotac_content').hide ->
							$(@).html(success).fadeIn()
						$('#panopto_perm_state').html('Access: Creator')
					error: (result)=>
						$('.panoptotac_loading', @).fadeOut()
						error = "<div class='panoptotac_error'>Error<span>" +
							"#{__this.strings.error}" +
							"</span></div>"
						$('.panoptotac_terms .panoptotac_content').hide ->
							$(@).html(error).fadeIn()
					

	$.fn.extend panoptoTac: (options, args...) ->

		$.error 'PanoptoTac can only be initialized once' if @.length > 1 

		obj = @.data('panoptoTac')
		if !obj and typeof options isnt 'string'
			@.data 'panoptoTac', (obj = new PanoptoTac(@, options))
		else if typeof options is 'string'
			obj[options].apply(obj, args)
		else
			$.error 'Incorrect usage of panoptoTac plugin'

) window.jQuery, window

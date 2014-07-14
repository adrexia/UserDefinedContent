/**
 * Javascript required to power the user defined forms.
 * 
 * Rewritten from the prototype FieldEditor and constantly
 * being refactored to be be less specific on the UDF dom.
 */
(function($) {
	$(document).ready(function() {
		/**
		 * Namespace
		 */
		var usercontent = usercontent || {};
		
		/** 
		 * Messages from usercontent are translatable using i18n.
		 */
		usercontent.messages = {
			CONFIRM_DELETE_ALL_SUBMISSIONS: 'All submissions will be permanently removed. Continue?',
			ERROR_CREATING_FIELD: 'Error creating field',
			ADDING_FIELD: 'Adding new field',
			ADDED_FIELD: 'Added new field',
			HIDE_OPTIONS: 'Hide options',
			SHOW_OPTIONS: 'Show options',
			ADDING_OPTION: 'Adding option',
			ADDED_OPTION: 'Added option',
			ERROR_CREATING_OPTION: 'Error creating option',
			REMOVED_OPTION: 'Removed option',
			ADDING_RULE: 'Adding rule'
		};
		
		/**
		 * Returns a given translatable string from a passed key. Keys
		 * should be all caps without any spaces.
		 */
		usercontent.message = function() {
			en = arguments[1] || usercontent.messages[arguments[0]];
			
			return ss.i18n._t("UserContent."+ arguments[0], en);
		};
		
		/**
		 * Update the sortable properties of the form as a function
		 * since the application will need to refresh the UI dynamically based
		 * on a number of factors including when the user adds a page or
		 * swaps between pages
		 *
		 */
		usercontent.update = function() {
			$("#section_fields").sortable({
				handle: '.fieldHandler',
				cursor: 'pointer',
				items: '.EditableContentField',
				placeholder: 'removed-form-field',
				opacity: 0.6,
				revert: 'true',
				change : function (event, ui) {
					$("#section_fields").sortable('refreshPositions');
				},
				update : function (event, ui) {
					var sort = 1;

					$(".EditableContentField").each(function() {
						$(this).find(".sortHidden").val(sort++);
					});
				}
			});

			$(".editableOptions").sortable({
				handle: '.handle',
				cursor:'pointer',
				items: '.EditableContentField',
				placeholder: 'removed-form-field',
				opacity: 0.6,
				revert: true,
				change : function (event, ui) {
					$(this).sortable('refreshPositions');
				},
				update : function (event, ui) {
					var sort = 1;
					$(".editableOptions section").each(function() {
						$(this).find(".sortOptionHidden").val(sort++);
					});
				}
			});
		};
		
		usercontent.appendToURL = function(url, pathsegmenttobeadded) {
			var parts = url.match(/([^\?#]*)?(\?[^#]*)?(#.*)?/);
			for(var i in parts) if(!parts[i]) parts[i] = '';
			return parts[1] + pathsegmenttobeadded + parts[2] + parts[3];
		}

		/**
		 * Workaround for not refreshing the sort.
		 * 
		 * TODO: better solution would to not fire this on every hover but needs to
		 *		ensure it doesn't have edge cases. The sledge hammer approach.
		 */
		$(".fieldHandler, .handle").on('hover', function() {
			usercontent.update();
		});
		
		/**
		 * Kick off the usercontent UI
		 */
		usercontent.update();
		
		
		$.entwine('udf', function($){
			
			/*-------------------- FIELD EDITOR ----------------------- */
			
			/**
			 * Create a new instance of a field in the current form 
			 * area. the type information should all be on this object
			 */
			$('div.FieldEditor .MenuHolder .action').entwine({
				onclick: function(e) {
					var form = $("#Form_EditForm"),
						length = $(".FieldInfo").length + 1, 
						fieldType = $(this).siblings("select").val(),
						formData = form.serialize()+'NewID='+ length +"&Type="+ fieldType, 
						fieldEditor = $(this).closest('.FieldEditor');

					e.preventDefault();

					if($("#Fields").hasClass('readonly') || !fieldType) {
						return;
					}
					
					
					// Due to some very weird behaviout of jquery.metadata, the url have to be double quoted
					var addURL = fieldEditor.attr('data-add-url').substr(1, fieldEditor.attr('data-add-url').length-2);

					$.ajax({
						headers: {"X-Pjax" : 'Partial'},
						type: "POST",
						url: addURL,
						data: formData, 
						success: function(data) {
							$('#Fields_fields').append(data);

							statusMessage(usercontent.message('ADDED_FIELD'));
							
							var name = $("#Fields_fields .EditableContentField:last").attr("data-id").split(' ');

							$("#Fields_fields select.fieldOption").append("<option value='"+ name[2] +"'>New "+ name[2] + "</option>");
							$("#Fields_fields").sortable('refresh');
						},
						error: function(e) {
							alert(ss.i18n._t('GRIDFIELD.ERRORINTRANSACTION'));
						}
					});
				}
			});
			
			/**
			 * Delete a field from the user defined form
			 */
			$(".EditableContentField .delete").entwine({
				onclick: function(e) {
					e.preventDefault();
					
					var text = $(this).parents(".EditableContentField").find(".fieldInfo .text").val();

					$(this).parents(".EditableContentField").slideUp(function(){$(this).remove()})
				}
			});
			
			/** 
			 * Upon renaming a field we should go through and rename all the
			 * fields in the select fields to use this new field title. We can
			 * just worry about the title text - don't mess around with the keys
			 */
			$('.EditableContentField .fieldInfo .text').entwine({
				onchange: function(e) {
					var value = $(this).val();
					var name = $(this).parents(".EditableContentField").attr("data-id").split(' ');
					$("#Fields_fields select.fieldOption option").each(function(i, domElement) {
						if($(domElement).val() === name[2]) {
							$(domElement).text(value);	
						}
					});
				}
			});
			
			/**
			 * Show the more options popdown. Or hide it if we currently have it open
			 */
			$(".EditableContentField .moreOptions").entwine({
				onclick: function(e) {
					e.preventDefault();
					
					var parent = $(this).closest(".EditableContentField");
					if(!parent) {
						return;
					}
					
					var extraOptions = parent.find(".extraOptions");
					if(!extraOptions) {
						return;
					}
					
					if(extraOptions.hasClass('hidden')) {
						$(this).addClass("showing");
						$(this).html('Hide options');
						extraOptions.removeClass('hidden');
						parent.addClass('expanded');
					} else {
						$(this).removeClass("showing");
						$(this).html('Show options');
						extraOptions.addClass('hidden');
						parent.removeClass('expanded');
					}
				}
			});
			
			/**
			 * Add a suboption to a radio field or to a dropdown box for example
			 */
			$(".EditableContentField .addableOption").entwine({
				onclick: function(e) {
					e.preventDefault();

					// Give the user some feedback
					statusMessage(usercontent.message('ADDING_OPTION'));

					// variables
					var options = $(this).parent(".EditableContentField"),
						action = usercontent.appendToURL($("#Form_EditForm").attr("action"), '/field/Fields/addoptionfield'),
						parent = $(this).attr("rel"),
						securityID = ($("input[name=SecurityID]").length > 0) ? $("input[name=SecurityID]").first().attr("value") : '';

					// send ajax request to the page
					$.ajax({
						type: "GET",
						url: action,
						data: 'Parent='+ parent +'&SecurityID='+securityID,
						// create a new field
						success: function(msg){
							options.before(msg);
							statusMessage(usercontent.message('ADDED_OPTION'));
						},

						// error creating new field
						error: function(request, text, error) {
							statusMessage(usercontent.message('ERROR_CREATING_OPTION'));
						} 
					});

				}
			});
			
			/**
			 * Delete a suboption such as an dropdown option or a 
			 * checkbox field
			 */
			$(".EditableContentField .deleteOption").entwine({
				onclick: function(e) {
					e.preventDefault();
					
					// pass the deleted status onto the element
					$(this).closest(".EditableContentField").children("[type=text]").attr("value", "field-node-deleted");
					$(this).closest(".EditableContentField").hide();

					// Give the user some feedback
					statusMessage(usercontent.message('REMOVED_OPTION'));
				}
			});
			

		
		});
	});
})(jQuery);
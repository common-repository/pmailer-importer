jQuery(document).ready( function($) 
{ 
	// reset any previous set attr & styles
	$('#pmailer_imp_options').fadeTo(0, 1);
	$('#pmailer_imp_options input, #pmailer_imp_options select').removeAttr('disabled');
	
	// bind events
	$('#pmailer_imp_show_summary').toggle(function() 
	{
		$("#pmailer_imp_summary").slideToggle("fast");
		$('#pmailer_imp_show_summary').text('▼ Hide details');
	}, function() 
	{
		$("#pmailer_imp_summary").slideToggle("fast");
		$('#pmailer_imp_show_summary').text('► Show details');
	});
	
	function pmailerExitMessage()
	{
		return confirm('Contacts are being imported to pMailer, if you leave this page the import will stop.');
	}
	
	var error_count = 0;
	
	function pmailerImportProgress(response)
	{
		$('#pmailer_imp_message').text(response.message);
		
		// if more than 3 errors occur consequtivly then stop the import
		if ( response.status == 'error' ) 
		{
			error_count++;
			if ( error_count >= 3 )
			{
				alert("The following error occurred: \n" + response.message);
				return;
			}
		}
		
		// if status was success then reset error count
		if ( response.status == 'success' )
		{
			error_count = 0;
		}
		
		// stop import if all has been imported
		if ( response.status == 'complete' )
		{
			// allow the user to leave the page without a warning
			window.onbeforeunload = null;
			return;
		}
		
		// display progress
		var total_to_import = 0; 
		if ( $('#pmailer_imp_users').attr('checked') == true ) 
		{
			total_to_import += parseInt($('#pmailer_imp_total_users').val());
		}
		if ( $('#pmailer_imp_comments').attr('checked') == true ) 
		{
			total_to_import += parseInt($('#pmailer_imp_total_comments').val());
		}
		var total_imported = parseInt(response.pmailer_imp_comment_limit) + parseInt(response.pmailer_imp_user_limit);
		if ( total_imported >= total_to_import )
		{
			total_imported = total_to_import;
			$('#pmailer_imp_message').text('Finishing import...');
		}
		var percentage = parseInt(total_imported / total_to_import * 100);
		percentage = ( isNaN(percentage) == false ) ? percentage : 0;
		var progress_message = 'Imported: ' + total_imported + ' out of ' + total_to_import + ' (' + percentage + '% complete)';
		$('#pmailer_imp_import_status').text(progress_message);
		
		var summary = '';
		$.each(response.results, function(key, message)
		{
			summary += message.message + '<br />';
		});
		
		$('#pmailer_imp_summary').prepend(summary);
		
		// remove import options if they have been fully imported
		if ( response.pmailer_imp_comment_limit >= parseInt($('#pmailer_imp_total_comments').val()) )
		{
			$('#pmailer_imp_comments').attr('name', 'ignore_comments');
		}
		if ( response.pmailer_imp_user_limit >= parseInt($('#pmailer_imp_total_users').val()) )
		{
			$('#pmailer_imp_users').attr('name', 'ignore_users');
		}
		
		// get data to import
		$('#pmailer_imp_options input, #pmailer_imp_options select').removeAttr('disabled');
		var query_data = $('#pmailer_imp_form_settings').serializeArray();
		query_data = $.merge(query_data, [{'name': 'pmailer_imp_comment_limit', 'value': response.pmailer_imp_comment_limit}]);
		query_data = $.merge(query_data, [{'name': 'pmailer_imp_user_limit', 'value': response.pmailer_imp_user_limit}]);
		$('#pmailer_imp_options input, #pmailer_imp_options select').attr('disabled', 'disabled');
		// send import data
		$.ajax({
		  url: $('#pmailer_imp_url').val(),
		  dataType: 'json',
		  data: query_data,
		  type: 'POST',
		  success: pmailerImportProgress
		});
		
	}
	
	function pmailerImportContacts(event)
	{
		event.preventDefault();
		
		// warn user if they try leave the page while importing contacts
		window.onbeforeunload = pmailerExitMessage;
		
		// clear all previous errors
		var valid = true;
		$('#pmailer_imp_selected_lists_error, #pmailer_imp_selected_options_error').text('');
		$('#pmailer_imp_selected_lists, #pmailer_imp_users_label, #pmailer_imp_comments_label').css({'border':'1px solid #DFDFDF'})

		// check if minimal options for import have been set and warn if they havnt
		var selected_lists = $('#pmailer_imp_selected_lists').val();
		if ( selected_lists == null )
		{
			$('#pmailer_imp_selected_lists').css({'border': '1px dotted red'});
			$('#pmailer_imp_selected_lists_error').text('Please select atleast one list to import into.');
			valid = false;
		}
		
		if ( $('#pmailer_imp_users').attr('checked') == false && $('#pmailer_imp_comments').attr('checked') == false )
		{
			$('#pmailer_imp_users_label, #pmailer_imp_comments_label').css({'border': '1px dotted red'});
			$('#pmailer_imp_selected_options_error').text('Please select atleast one category to import');
			valid = false;
		}
		
		// do not import if options are not filled in;
		if ( valid == false )
		{
			return;
		}
		
		// send data to import
		var query_data = $('#pmailer_imp_form_settings').serialize();
		$.ajax({
		  url: $('#pmailer_imp_url').val(),
		  dataType: 'json',
		  data: query_data,
		  type: 'POST',
		  success: pmailerImportProgress
		});
		
		// show pregress
		$('#pmailer_imp_progress').show();
		
		// disable btns
		$('#pmailer_imp_options').fadeTo('fast', 0.5);
		$('#pmailer_imp_options input, #pmailer_imp_options select').attr('disabled', 'disabled');
		
	}
	
	$('#pmailer_imp_import_process').bind('click', pmailerImportContacts);
});

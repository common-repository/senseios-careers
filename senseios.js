
jQuery(document).ready(function(){
	jQuery('#sensei_save_settings').click(function(e){
		// Show the spinner
		jQuery('#sensei_test_loading').css('display', 'block');

		// Clear previous output
		jQuery('#sensei_test_login_output').html('<ul></ul>');
		jQuery('#sensei_test_login_output ul').append('<li class="sensei_true">Starting test</li>');

		var nextTests = true;
		if(jQuery('#sensei_url').val() == ''){
			showResult('URL missing', false);
			nextTests = false;
		}
		if(jQuery('#sensei_vendor').val() == ''){
			showResult('Vendor Code missing', false);
			nextTests = false;
		}
		if(jQuery('#sensei_token').val() == ''){
			showResult('API Token missing', false);
			nextTests = false;
		}

		if(nextTests){
			// Must be SSL
			if(jQuery('#sensei_url').val().substr(0,5) == 'https'){
				showResult('URL good!', true);
			}else{
				showResult('URL must start with https', false);
				nextTests = false;
			}

			// Validate that Vendor Code is a valid GUID
			var guidRegEx = new RegExp("^(\{{0,1}([0-9a-fA-F]){8}-([0-9a-fA-F]){4}-([0-9a-fA-F]){4}-([0-9a-fA-F]){4}-([0-9a-fA-F]){12}\}{0,1})$");
			if(guidRegEx.test(jQuery('#sensei_vendor').val())){
				showResult('Vendor Code good!', true);
			}else{
				showResult('Vendor Code is not in a valid format', false);
				nextTests = false;
			}
		}

		if(nextTests){
			// Can we reach the URL?
			var senseiURL = jQuery('#sensei_url').val();
			var senseiAPI = jQuery('#sensei_token').val();
			var senseiVendor = jQuery('#sensei_vendor').val();

			var senseiAPIURL = senseiURL + '?_=' + senseiAPI + '&VendorCode=' + senseiVendor;

			var jqxhr = jQuery.ajax({ 
				url: senseiAPIURL,
				/*error: function (xhr, ajaxOptions, thrownError) {
			        showResult("Can't connect to SenseiOS. Make sure your IP is whitelisted by SenseiOS.", false);
					nextTests = false;
			    },*/
				async: false })
			  .done(function() {
				    showResult("Connected to SenseiOS", true);
			  })
		}

		// Hide the spinner
		jQuery('#sensei_test_loading').css('display', 'none');

		// Block the save if we had errors
		if( !nextTests ){
			return false;
		}else{
			return true;
		}
	});
});

function handleAjaxError( jqXHR, textStatus, errorThrown){
	alert('Uh oh! ' + textStatus);
}

function showResult(msg, isGood){
	jQuery('#sensei_test_login_output ul').append('<li class="sensei_' + isGood + '">' + msg + '</li>');
}
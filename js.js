jQuery(document).ready(function() {
	jQuery('.paywall-unlock').click(function () {
		
		// Get the hidden element
		var hidden_element = jQuery('#' + this.id).next();
		hidden_element.slideToggle('fast');
		
		// Change the unlock link text
		if(hidden_element.attr('status') === 'invisible') {
			hidden_element.attr('status', 'visible');
		}
		else {
			hidden_element.attr('status', 'invisible');
			
			// Get the unlocklink text, that the user wants to be displayed
			var unlocklink_text = jQuery('#' + this.id).attr('unlocklink-text');
			
			// Make the lesslink a unlock link
			jQuery('#' + this.id).html(unlocklink_text);
		}
	});
});
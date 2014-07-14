jQuery(function($) {
	/**
	 * Make sure the form does not expire on the user.
	 */
	setInterval(function() {
		// Ping every 3 mins.
		$.ajax({url: "UserDefinedContent_Controller/ping"});
	}, 180*1000);
});

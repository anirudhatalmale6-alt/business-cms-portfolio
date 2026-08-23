/* global BCMS_ADMIN */
( function () {
	'use strict';

	var button = document.getElementById( 'bcms-test-webhook' );
	var result = document.getElementById( 'bcms-test-result' );

	if ( ! button || ! result || ! window.BCMS_ADMIN ) {
		return;
	}

	button.addEventListener( 'click', function () {
		button.disabled = true;
		result.textContent = BCMS_ADMIN.i18n.testing;
		result.style.color = '#646970';

		var body = new window.FormData();
		body.append( 'action', 'bcms_test_webhook' );
		body.append( '_wpnonce', BCMS_ADMIN.nonce );

		window
			.fetch( BCMS_ADMIN.ajax, {
				method: 'POST',
				credentials: 'same-origin',
				body: body,
			} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( payload ) {
				var ok = payload && payload.success;
				result.style.color = ok ? '#1a7f37' : '#b32d2e';
				result.textContent =
					( payload && payload.data && payload.data.message ) || BCMS_ADMIN.i18n.failed;
			} )
			.catch( function () {
				result.style.color = '#b32d2e';
				result.textContent = BCMS_ADMIN.i18n.failed;
			} )
			.then( function () {
				button.disabled = false;
			} );
	} );
} )();

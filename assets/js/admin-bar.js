jQuery(function ($) {
	$(document).on('click', '.elodin-recently-edited-action', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var url = $(this).data('url');
		if (!url) {
			return;
		}
		window.location.href = url;
	});
	$(document).on(
		'click',
		'#wp-admin-bar-recently-edited .elodin-recently-edited-pin',
		function (e) {
			e.preventDefault();
			e.stopPropagation();
			e.stopImmediatePropagation();
			var $pin = $(this);
			var postId = $pin.data('postId');
			if (!postId) {
				return;
			}
			$.post(ElodinRecentlyEdited.ajaxUrl, {
				action: 'elodin_recently_edited_toggle_pin',
				post_id: postId,
				nonce: ElodinRecentlyEdited.nonce,
			})
				.done(function () {
					window.location.reload();
				})
				.fail(function () {
					window.location.reload();
				});
			return false;
		},
	);
});

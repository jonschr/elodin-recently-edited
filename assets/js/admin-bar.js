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
				nonce: ElodinRecentlyEdited.noncePin,
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
	$(document).on('click', '#wp-admin-bar-recently-edited .ab-submenu a', function (e) {
		if ($(e.target).is('select.elodin-recently-edited-status-select') || $(e.target).closest('select.elodin-recently-edited-status-select').length) {
			e.preventDefault();
			e.stopPropagation();
		}
	});
	$(document).on('click', '.elodin-recently-edited-status-select', function (e) {
		e.stopPropagation();
	});
	$(document).on('change', '.elodin-recently-edited-status-select', function (e) {
		e.preventDefault();
		var $select = $(this);
		var postId = $select.data('postId');
		var status = $select.val();
		var original = $select.data('original');
		if (!postId || !status || status === original) {
			return;
		}
		if (status === 'delete') {
			if (!confirm('Are you sure you want to delete this post?')) {
				$select.val(original);
				return;
			}
		}
		$.post(ElodinRecentlyEdited.ajaxUrl, {
			action: 'elodin_recently_edited_update_status',
			post_id: postId,
			status: status,
			nonce: ElodinRecentlyEdited.nonceStatus,
		}).always(function () {
			window.location.reload();
		});
	});
});

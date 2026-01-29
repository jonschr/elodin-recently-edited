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
				.done(function (response) {
					if (response.success) {
						// Toggle the pin visually
						var isPinned = $pin.hasClass('is-pinned');
						if (isPinned) {
							$pin.removeClass('is-pinned').text('☆');
						} else {
							$pin.addClass('is-pinned').text('★');
						}
					} else {
						alert(
							'Error toggling pin: ' +
								(response.data
									? response.data.message
									: 'Unknown error'),
						);
					}
				})
				.fail(function () {
					alert('Failed to toggle pin.');
				});
			return false;
		},
	);
	$(document).on(
		'click',
		'#wp-admin-bar-recently-edited .ab-submenu a',
		function (e) {
			if (
				$(e.target).is('select.elodin-recently-edited-status-select') ||
				$(e.target).is(
					'select.elodin-recently-edited-post-type-select',
				) ||
				$(e.target).closest(
					'select.elodin-recently-edited-status-select',
				).length ||
				$(e.target).closest(
					'select.elodin-recently-edited-post-type-select',
				).length
			) {
				e.preventDefault();
				e.stopPropagation();
			}
		},
	);
	$(document).on(
		'click',
		'.elodin-recently-edited-status-select, .elodin-recently-edited-post-type-select',
		function (e) {
			e.preventDefault();
			e.stopPropagation();
		},
	);
	$(document).on(
		'change',
		'.elodin-recently-edited-status-select',
		function (e) {
			e.preventDefault();
			var $select = $(this);
			var postId = $select.data('postId');
			var status = $select.val();
			var original = $select.data('original');
			if (!postId || !status) {
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
			})
				.done(function (response) {
					if (response.success) {
						if (status === 'delete') {
							// Remove the menu item
							$select.closest('.ab-submenu .ab-item').remove();
						} else {
							// Update the original status
							$select.data('original', status);
						}
					} else {
						// Revert on error
						$select.val(original);
						alert(
							'Error updating status: ' +
								(response.data
									? response.data.message
									: 'Unknown error'),
						);
					}
				})
				.fail(function () {
					$select.val(original);
					alert('Failed to update status.');
				});
		},
	);
	$(document).on(
		'change',
		'.elodin-recently-edited-post-type-select',
		function (e) {
			e.preventDefault();
			var $select = $(this);
			var postId = $select.data('postId');
			var postType = $select.val();
			var original = $select.data('original');
			if (!postId || !postType) {
				return;
			}
			$.post(ElodinRecentlyEdited.ajaxUrl, {
				action: 'elodin_recently_edited_update_post_type',
				post_id: postId,
				post_type: postType,
				nonce: ElodinRecentlyEdited.noncePostType,
			})
				.done(function (response) {
					if (response.success) {
						// Update the original post type
						$select.data('original', postType);
					} else {
						// Revert on error
						$select.val(original);
						alert(
							'Error updating post type: ' +
								(response.data
									? response.data.message
									: 'Unknown error'),
						);
					}
				})
				.fail(function () {
					$select.val(original);
					alert('Failed to update post type.');
				});
		},
	);
});

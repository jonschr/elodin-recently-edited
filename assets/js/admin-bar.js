/**
 * JavaScript functionality for Recently Edited Quick Links admin bar
 *
 * Handles AJAX interactions for pinning posts, updating status, and changing post types.
 * All AJAX requests include proper nonce verification for security.
 */

jQuery(function ($) {
	var menuIds = ['wp-admin-bar-recently-edited', 'wp-admin-bar-related'];
	var closeDelayMs = 2000;
	var closeTimers = {};

	function storageKey(menuId) {
		return menuId === 'wp-admin-bar-related'
			? 'elodin_related_keep_menu_open'
			: 'elodin_recently_edited_keep_menu_open';
	}

	function scrollStorageKey(menuId) {
		return menuId === 'wp-admin-bar-related'
			? 'elodin_related_scroll_top'
			: 'elodin_recently_edited_scroll_top';
	}

	function normalizeSearchText(value) {
		return (value || '').toString().toLowerCase().trim();
	}

	function filterMenuItems($menu, query) {
		var normalized = normalizeSearchText(query);
		var matchCount = 0;
		$menu.find('.elodin-recently-edited-row').each(function () {
			var $row = $(this);
			var searchText = $row.attr('data-search-text') || $row.text();
			var matches =
				normalized === '' ||
				normalizeSearchText(searchText).indexOf(normalized) !== -1;
			if (matches && normalized !== '') {
				matchCount += 1;
			}
			$row.closest('li').toggle(matches);
		});

		var $noMatchesItem = $menu.find('.elodin-recently-edited-no-matches');
		if (normalized === '') {
			$noMatchesItem.hide();
		} else {
			$noMatchesItem.toggle(matchCount === 0);
		}
	}

	function getOpenMenu() {
		return $(
			'#wp-admin-bar-recently-edited.hover, #wp-admin-bar-related.hover',
		).first();
	}

	function cancelClose(menuId) {
		if (closeTimers[menuId]) {
			clearTimeout(closeTimers[menuId]);
			delete closeTimers[menuId];
		}
	}

	function getSubmenu($menu) {
		return $menu.find('> .ab-sub-wrapper > .ab-submenu');
	}

	function saveScrollPosition(menuId) {
		var $menu = $('#' + menuId);
		if (!$menu.length) {
			return;
		}
		var $submenu = getSubmenu($menu);
		if (!$submenu.length) {
			return;
		}
		sessionStorage.setItem(
			scrollStorageKey(menuId),
			String($submenu.scrollTop()),
		);
	}

	function restoreScrollPosition(menuId) {
		var $menu = $('#' + menuId);
		if (!$menu.length) {
			return;
		}
		var $submenu = getSubmenu($menu);
		if (!$submenu.length) {
			return;
		}
		var stored = sessionStorage.getItem(scrollStorageKey(menuId));
		if (!stored) {
			return;
		}
		var value = parseInt(stored, 10);
		if (Number.isNaN(value)) {
			return;
		}
		$submenu.scrollTop(value);
	}

	function scheduleClose(menuId) {
		if (!menuId) {
			return;
		}
		cancelClose(menuId);
		$('#' + menuId).addClass('hover');
		closeTimers[menuId] = window.setTimeout(function () {
			clearKeepOpenState(menuId);
		}, closeDelayMs);
	}

	function clearKeepOpenState(menuId) {
		if (!menuId) {
			menuIds.forEach(function (id) {
				clearKeepOpenState(id);
			});
			return;
		}
		cancelClose(menuId);
		sessionStorage.removeItem(storageKey(menuId));
		$('#' + menuId).removeClass('hover');
	}

	function getMenuIdFromElement($element) {
		var $menu = $element.closest(
			'#wp-admin-bar-recently-edited, #wp-admin-bar-related',
		);
		return $menu.length ? $menu.attr('id') : 'wp-admin-bar-recently-edited';
	}

	/**
	 * Check if we should keep the recently edited menu open after page load
	 */
	function checkAndRestoreMenuState() {
		menuIds.forEach(function (menuId) {
			var shouldKeepOpen = sessionStorage.getItem(storageKey(menuId));
			if (shouldKeepOpen === 'true') {
				// Clear the flag
				sessionStorage.removeItem(storageKey(menuId));

				// Add hover class to keep menu open
				$('#' + menuId).addClass('hover');
				window.setTimeout(function () {
					restoreScrollPosition(menuId);
				}, 0);
			}
		});
	}

	/**
	 * Handle clicks on action links (view, edit, etc.)
	 */
	$(document).on('click', '.elodin-recently-edited-action', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var url = $(this).data('url');
		if (!url || url === '#') {
			return;
		}

		if (e.metaKey || e.ctrlKey) {
			window.open(url, '_blank', 'noopener');
			return;
		}

		// Set flag to keep menu open after navigation
		var menuId = getMenuIdFromElement($(this));
		saveScrollPosition(menuId);
		sessionStorage.setItem(storageKey(menuId), 'true');

		window.location.href = url;
	});

	/**
	 * Keep the related menu open after selecting a content type
	 */
	$(document).on(
		'click',
		'#wp-admin-bar-related .elodin-related-pill',
		function () {
			sessionStorage.setItem(storageKey('wp-admin-bar-related'), 'true');
		},
	);

	/**
	 * Handle clicks outside the menu to close it
	 */
	$(document).on('click', function (e) {
		// If click is outside the recently edited menu, remove the keep-open flag
		if (
			!$(e.target).closest(
				'#wp-admin-bar-recently-edited, #wp-admin-bar-related',
			).length
		) {
			clearKeepOpenState();
		}
	});

	/**
	 * Keep menu open briefly when the user moves the mouse away
	 */
	$(document).on(
		'mouseleave',
		'#wp-admin-bar-recently-edited, #wp-admin-bar-related',
		function () {
			scheduleClose($(this).attr('id'));
		},
	);

	/**
	 * Close the other menu immediately on hover
	 */
	$(document).on(
		'mouseenter',
		'#wp-admin-bar-recently-edited, #wp-admin-bar-related',
		function () {
			var menuId = $(this).attr('id');
			cancelClose(menuId);
			menuIds.forEach(function (id) {
				if (id !== menuId) {
					clearKeepOpenState(id);
				}
			});
		},
	);

	/**
	 * Open the Related menu on click without navigating
	 */
	$(document).on('click', '#wp-admin-bar-related > .ab-item', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var menuId = 'wp-admin-bar-related';
		cancelClose(menuId);
		$('#' + menuId).addClass('hover');
		restoreScrollPosition(menuId);
		menuIds.forEach(function (id) {
			if (id !== menuId) {
				clearKeepOpenState(id);
			}
		});
	});

	/**
	 * Filter menu items based on search input
	 */
	$(document).on(
		'input',
		'.elodin-recently-edited-search-input',
		function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $input = $(this);
			var menuId = getMenuIdFromElement($input);
			filterMenuItems($('#' + menuId), $input.val());
		},
	);

	$(document).on(
		'click',
		'.elodin-recently-edited-search-input',
		function (e) {
			e.preventDefault();
			e.stopPropagation();
		},
	);

	$(document).on('keydown', function (e) {
		if (e.metaKey || e.ctrlKey || e.altKey) {
			return;
		}

		if (e.key.length !== 1) {
			return;
		}

		if (
			$(e.target).is('input, textarea, select') ||
			$(e.target).closest('[contenteditable="true"]').length
		) {
			return;
		}

		var $menu = getOpenMenu();
		if (!$menu.length) {
			return;
		}

		var $input = $menu.find('.elodin-recently-edited-search-input').first();
		if (!$input.length) {
			return;
		}

		var nextValue = ($input.val() || '') + e.key;
		$input.val(nextValue);
		filterMenuItems($menu, nextValue);
		$input.focus();
		e.preventDefault();
	});

	/**
	 * Initialize menu state on page load
	 */
	checkAndRestoreMenuState();

	/**
	 * Persist scroll position while scrolling
	 */
	$('#wp-admin-bar-recently-edited .ab-submenu, #wp-admin-bar-related .ab-submenu').on(
		'scroll',
		function () {
			var $menu = $(this).closest(
				'#wp-admin-bar-recently-edited, #wp-admin-bar-related',
			);
			if (!$menu.length) {
				return;
			}
			saveScrollPosition($menu.attr('id'));
		},
	);

	/**
	 * Handle pin/unpin toggle for posts
	 */
	$(document).on(
		'click',
		'#wp-admin-bar-recently-edited .elodin-recently-edited-pin, #wp-admin-bar-related .elodin-recently-edited-pin',
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

	/**
	 * Prevent clicks on select elements from triggering parent link navigation
	 */
	$(document).on(
		'click',
		'#wp-admin-bar-recently-edited .ab-submenu a, #wp-admin-bar-related .ab-submenu a',
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

	/**
	 * Prevent select clicks from bubbling up
	 */
	$(document).on(
		'click',
		'.elodin-recently-edited-status-select, .elodin-recently-edited-post-type-select',
		function (e) {
			e.preventDefault();
			e.stopPropagation();
		},
	);

	/**
	 * Copy post ID on click and show feedback
	 */
	$(document).on('click', '.elodin-recently-edited-id', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var $id = $(this);
		var postId = $id.data('id');
		if (!postId) {
			return;
		}

		var originalText = $id.text();
		var copyText = String(postId);

		function showCopied() {
			$id.text('Copied');
			window.setTimeout(function () {
				$id.text(originalText);
			}, 900);
		}

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard
				.writeText(copyText)
				.then(showCopied)
				.catch(function () {
					showCopied();
				});
		} else {
			var tempInput = $('<input>')
				.val(copyText)
				.appendTo('body')
				.select();
			try {
				document.execCommand('copy');
			} catch (err) {
				// no-op fallback
			}
			tempInput.remove();
			showCopied();
		}
	});

	/**
	 * Handle status change for posts
	 */
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

	/**
	 * Handle post type change for posts
	 */
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

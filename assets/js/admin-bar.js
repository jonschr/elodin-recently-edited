/**
 * JavaScript functionality for Recently Edited Quick Links admin bar
 *
 * Handles AJAX interactions for pinning posts, updating status, and changing post types.
 * All AJAX requests include proper nonce verification for security.
 */

jQuery(function ($) {
	var menuIds = ['wp-admin-bar-recently-edited'];
	var closeDelayMs = 2000;
	var closeTimers = {};

	function storageKey(menuId) {
		return 'elodin_recently_edited_keep_menu_open';
	}

	function scrollStorageKey(menuId, group) {
		return 'elodin_recently_edited_scroll_top_' + (group || 'all');
	}

	function groupStorageKey(menuId) {
		return 'elodin_recently_edited_active_group';
	}

	function normalizeSearchText(value) {
		return (value || '').toString().toLowerCase().trim();
	}

	function setScrollbarWidthVariable() {
		var probe = $('<div>')
			.css({
				height: '100px',
				left: '-9999px',
				overflow: 'scroll',
				position: 'absolute',
				top: '-9999px',
				width: '100px',
			})
			.appendTo('body');
		var scrollbarWidth = probe[0].offsetWidth - probe[0].clientWidth;
		probe.remove();

		$('#wp-admin-bar-recently-edited').css(
			'--elodin-scrollbar-width',
			scrollbarWidth + 'px',
		);
	}

	function filterMenuItems($menu, query) {
		var normalized = normalizeSearchText(query);
		var matchCount = 0;
		var activeGroup = getActiveGroup($menu);

		$menu.find('.elodin-recently-edited-row').each(function () {
			var $row = $(this);
			var $item = $row.closest('.elodin-recently-edited-list-item');
			if ($row.attr('data-related-group') !== activeGroup) {
				$item.hide();
				return;
			}

			var searchText = $row.attr('data-search-text') || $row.text();
			var matches =
				normalized === '' ||
				normalizeSearchText(searchText).indexOf(normalized) !== -1;
			if (matches && normalized !== '') {
				matchCount += 1;
			}
			$item.toggle(matches);
		});

		var $noMatchesItem = $menu.find('.elodin-recently-edited-no-matches');
		if (normalized === '') {
			$noMatchesItem.hide();
		} else {
			$noMatchesItem.toggle(matchCount === 0);
		}
	}

	function getOpenMenu() {
		return $('#wp-admin-bar-recently-edited.hover').first();
	}

	function cancelClose(menuId) {
		if (closeTimers[menuId]) {
			clearTimeout(closeTimers[menuId]);
			delete closeTimers[menuId];
		}
	}

	function getSubmenu($menu) {
		var $postList = $menu.find('.elodin-recently-edited-post-list').first();
		return $postList.length
			? $postList
			: $menu.find('> .ab-sub-wrapper > .ab-submenu');
	}

	function getActiveGroup($menu) {
		return (
			$menu.find('.elodin-related-pill.is-active').data('relatedTarget') ||
			'all'
		);
	}

	function switchRelatedGroup($menu, target) {
		if (!$menu.length || !target) {
			return;
		}

		var $targetPill = $menu.find(
			'.elodin-related-pill[data-related-target="' + target + '"]',
		);
		if (!$targetPill.length && target !== 'all') {
			target = 'all';
			$targetPill = $menu.find(
				'.elodin-related-pill[data-related-target="all"]',
			);
		}
		if (!$targetPill.length) {
			return;
		}

		$menu.find('.elodin-related-pill').removeClass('is-active');
		$targetPill.addClass('is-active');

		$menu.find('.elodin-recently-edited-list-item').removeClass('is-active');
		$menu
			.find(
				'.elodin-recently-edited-row[data-related-group="' +
					target +
					'"]',
			)
			.closest('.elodin-recently-edited-list-item')
			.addClass('is-active');

		filterMenuItems(
			$menu,
			$menu.find('.elodin-recently-edited-search-input').first().val(),
		);
		$menu.find('.elodin-recently-edited-post-list').scrollTop(0);
	}

	function saveActiveGroup(menuId) {
		var $menu = $('#' + menuId);
		if (!$menu.length) {
			return;
		}
		sessionStorage.setItem(groupStorageKey(menuId), getActiveGroup($menu));
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
		var group = getActiveGroup($menu);
		sessionStorage.setItem(
			scrollStorageKey(menuId, group),
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
		var group = getActiveGroup($menu);
		var stored = sessionStorage.getItem(scrollStorageKey(menuId, group));
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
		$('#' + menuId).addClass('hover elodin-recently-edited-grace-open');
		window.setTimeout(function () {
			$('#' + menuId).addClass('hover elodin-recently-edited-grace-open');
		}, 0);
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
		$('#' + menuId).removeClass('hover elodin-recently-edited-grace-open');
	}

	function getMenuIdFromElement($element) {
		var $menu = $element.closest('#wp-admin-bar-recently-edited');
		return $menu.length ? $menu.attr('id') : 'wp-admin-bar-recently-edited';
	}

	/**
	 * Check if we should keep the recently edited menu open after page load
	 */
	function checkAndRestoreMenuState() {
		menuIds.forEach(function (menuId) {
			var shouldKeepOpen = sessionStorage.getItem(storageKey(menuId));
			if (shouldKeepOpen === 'true') {
				var storedGroup = sessionStorage.getItem(groupStorageKey(menuId));
				// Clear the flag
				sessionStorage.removeItem(storageKey(menuId));
				sessionStorage.removeItem(groupStorageKey(menuId));

				// Add hover class to keep menu open
				$('#' + menuId).addClass('hover');
				window.setTimeout(function () {
					if (storedGroup) {
						switchRelatedGroup($('#' + menuId), storedGroup);
					}
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
		saveActiveGroup(menuId);
		saveScrollPosition(menuId);
		sessionStorage.setItem(storageKey(menuId), 'true');

		window.location.href = url;
	});

	/**
	 * Switch content type lists on hover without navigating.
	 */
	$(document).on(
		'mouseenter focus',
		'#wp-admin-bar-recently-edited .elodin-related-pill',
		function () {
			var $pill = $(this);
			switchRelatedGroup(
				$('#wp-admin-bar-recently-edited'),
				$pill.data('relatedTarget'),
			);
		},
	);

	/**
	 * Keep the menu open after selecting a content type admin link.
	 */
	$(document).on(
		'click',
		'#wp-admin-bar-recently-edited .elodin-related-pill',
		function (e) {
			var href = $(this).attr('href');
			var menuId = 'wp-admin-bar-recently-edited';
			saveActiveGroup(menuId);
			sessionStorage.setItem(storageKey(menuId), 'true');
			saveScrollPosition(menuId);

			if (!href || href === '#') {
				e.preventDefault();
				e.stopPropagation();
			}
		},
	);

	/**
	 * Handle clicks outside the menu to close it
	 */
	$(document).on('click', function (e) {
		// If click is outside the recently edited menu, remove the keep-open flag
		if (!$(e.target).closest('#wp-admin-bar-recently-edited').length) {
			clearKeepOpenState();
		}
	});

	/**
	 * Keep menu open briefly when the user moves the mouse away
	 */
	$(document).on(
		'mouseleave',
		'#wp-admin-bar-recently-edited',
		function () {
			scheduleClose($(this).attr('id'));
		},
	);

	/**
	 * Cancel pending close when the menu is hovered again
	 */
	$(document).on(
		'mouseenter',
		'#wp-admin-bar-recently-edited',
		function () {
			var menuId = $(this).attr('id');
			cancelClose(menuId);
			$(this).removeClass('elodin-recently-edited-grace-open');
			menuIds.forEach(function (id) {
				if (id !== menuId) {
					clearKeepOpenState(id);
				}
			});
		},
	);

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
			switchRelatedGroup($('#' + menuId), 'all');
			filterMenuItems($('#' + menuId), $input.val());
		},
	);

	$(document).on(
		'click',
		'.elodin-recently-edited-search-input',
		function (e) {
			e.preventDefault();
			e.stopPropagation();
			var menuId = getMenuIdFromElement($(this));
			switchRelatedGroup($('#' + menuId), 'all');
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

		switchRelatedGroup($menu, 'all');
		var nextValue = ($input.val() || '') + e.key;
		$input.val(nextValue);
		filterMenuItems($menu, nextValue);
		$input.focus();
		e.preventDefault();
	});

	/**
	 * Initialize menu state on page load
	 */
	setScrollbarWidthVariable();
	checkAndRestoreMenuState();

	/**
	 * Persist scroll position while scrolling
	 */
	$('#wp-admin-bar-recently-edited .elodin-recently-edited-post-list').on(
		'scroll',
		function () {
			var $menu = $(this).closest('#wp-admin-bar-recently-edited');
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
						var $matchingPins = $(
							'#wp-admin-bar-recently-edited .elodin-recently-edited-pin',
						).filter(function () {
							return String($(this).data('postId')) === String(postId);
						});
						if (isPinned) {
							$matchingPins.removeClass('is-pinned').text('☆');
						} else {
							$matchingPins.addClass('is-pinned').text('★');
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
						var $matchingStatusSelects = $(
							'#wp-admin-bar-recently-edited .elodin-recently-edited-status-select',
						).filter(function () {
							return String($(this).data('postId')) === String(postId);
						});
						if (status === 'delete') {
							// Remove the menu item
							$matchingStatusSelects
								.closest('.elodin-recently-edited-list-item')
								.remove();
						} else {
							// Update the original status
							$matchingStatusSelects.data('original', status).val(status);
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
						$(
							'#wp-admin-bar-recently-edited .elodin-recently-edited-post-type-select',
						)
							.filter(function () {
								return String($(this).data('postId')) === String(postId);
							})
							.data('original', postType)
							.val(postType);
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

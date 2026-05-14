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
	var menuLoadRequest = null;
	var rowIndex = null;
	var isMac = /Mac|iPhone|iPad|iPod/.test(window.navigator.platform || '');

	function storageKey(menuId) {
		return 'elodin_recently_edited_keep_menu_open';
	}

	function scrollStorageKey(menuId, group) {
		return 'elodin_recently_edited_scroll_top_' + (group || 'all');
	}

	function groupStorageKey(menuId) {
		return 'elodin_recently_edited_active_group';
	}

	function selectionStorageKey(menuId) {
		return 'elodin_recently_edited_selected_row';
	}

	function normalizeSearchText(value) {
		return (value || '').toString().toLowerCase().trim();
	}

	function isKeyboardEventForE(event) {
		return event.code === 'KeyE' || String(event.key || '').toLowerCase() === 'e';
	}

	function getSearchShortcutLabel() {
		return isMac ? 'Cmd+Shift+E' : 'Ctrl+Shift+E';
	}

	function updateSearchPlaceholder() {
		$('.elodin-recently-edited-search-input').attr(
			'placeholder',
			'Search recently edited... (' + getSearchShortcutLabel() + ')',
		);
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

	function replaceAdminBarNode(menuId, html) {
		var $node = $('#wp-admin-bar-' + menuId);
		var $item = $node.children('.ab-item').first();
		if (!$item.length) {
			$item = $node.children('.ab-empty-item').first();
		}
		$item.html(html);
	}

	function getClientCacheKey() {
		return (
			(ElodinRecentlyEdited.cacheKey || 'elodin_recently_edited_menu') +
			'_v' +
			(ElodinRecentlyEdited.cacheSchema || 1)
		);
	}

	function readClientMenuCache() {
		try {
			var raw = window.localStorage.getItem(getClientCacheKey());
			if (!raw) {
				return null;
			}

			var cached = JSON.parse(raw);
			if (!cached || !cached.nodes) {
				return null;
			}

			return cached;
		} catch (error) {
			return null;
		}
	}

	function writeClientMenuCache(nodes) {
		try {
			window.localStorage.setItem(
				getClientCacheKey(),
				JSON.stringify({
					createdAt: Date.now(),
					nodes: nodes,
				}),
			);
		} catch (error) {
			// Storage can fail in private browsing or when quota is full.
		}
	}

	function clearClientMenuCache() {
		try {
			window.localStorage.removeItem(getClientCacheKey());
		} catch (error) {
			// no-op
		}
	}

	function hydrateRecentlyEditedMenu(nodes) {
		var $menu = $('#wp-admin-bar-recently-edited');
		if (!$menu.length || !nodes || !nodes.postList || !nodes.types) {
			return false;
		}

		if (nodes.root && nodes.root.href) {
			$menu.children('.ab-item').first().attr('href', nodes.root.href);
		}

		replaceAdminBarNode('recently-edited-search', nodes.search.title);
		replaceAdminBarNode('recently-edited-types', nodes.types.title);
		replaceAdminBarNode('recently-edited-no-matches', nodes.noMatches.title);
		replaceAdminBarNode('recently-edited-column-header', nodes.columnHeader.title);
		replaceAdminBarNode('recently-edited-post-list', nodes.postList.title);
		invalidateRowIndex();
		updateSearchPlaceholder();

		$menu
			.find('.elodin-recently-edited-shell-hidden')
			.removeClass('elodin-recently-edited-shell-hidden');
		$menu
			.find('.elodin-recently-edited-shell-item')
			.removeClass('elodin-recently-edited-shell-item');

		$menu.removeClass('elodin-recently-edited-is-lazy');
		setScrollbarWidthVariable();

		var storedGroup =
			sessionStorage.getItem(groupStorageKey($menu.attr('id'))) ||
			$menu.data('restoreGroup');
		if (storedGroup) {
			switchRelatedGroup($menu, storedGroup);
		}
		$menu.removeData('restoreGroup');
		restoreScrollPosition($menu.attr('id'));
		selectStoredVisibleRowOrCurrentOrFirst();

		return true;
	}

	function hydrateRecentlyEditedMenuFromCache() {
		var cached = readClientMenuCache();
		if (!cached) {
			return false;
		}

		return hydrateRecentlyEditedMenu(cached.nodes);
	}

	function scheduleMenuIndexBuild() {
		if (hydrateRecentlyEditedMenuFromCache()) {
			return;
		}

		window.setTimeout(function () {
			loadRecentlyEditedMenu();
		}, 0);
	}

	function invalidateRowIndex() {
		rowIndex = null;
	}

	function getRowIndex() {
		var menu = document.getElementById('wp-admin-bar-recently-edited');
		if (!menu) {
			return [];
		}

		if (rowIndex) {
			return rowIndex;
		}

		rowIndex = [];
		menu.querySelectorAll('.elodin-recently-edited-row').forEach(function (row) {
			var item = row.closest('.elodin-recently-edited-list-item');
			if (!item) {
				return;
			}

			rowIndex.push({
				group: row.getAttribute('data-related-group') || '',
				item: item,
				postType: row.getAttribute('data-post-type') || '',
				row: row,
				searchText: normalizeSearchText(
					row.getAttribute('data-search-text') || row.textContent,
				),
			});
		});

		return rowIndex;
	}

	function indexedRowMatchesGroup(row, group) {
		return (
			group === 'all' ||
			row.postType === group ||
			row.group === group
		);
	}

	function loadRecentlyEditedMenu(options) {
		options = options || {};
		var $menu = $('#wp-admin-bar-recently-edited');
		if (!$menu.length || !$menu.hasClass('elodin-recently-edited-is-lazy')) {
			return null;
		}

		if (menuLoadRequest) {
			return menuLoadRequest;
		}

		menuLoadRequest = $.ajax({
			url: ElodinRecentlyEdited.menuRestUrl,
			method: 'GET',
			data: {
				current_post_type: ElodinRecentlyEdited.currentPostType || '',
				current_post_id: ElodinRecentlyEdited.currentPostId || 0,
				preload: options.preload ? 1 : 0,
			},
			beforeSend: function (xhr) {
				xhr.setRequestHeader('X-WP-Nonce', ElodinRecentlyEdited.restNonce);
			},
		})
			.done(function (response) {
				if (response && response.skipped) {
					menuLoadRequest = null;
					return;
				}

				var nodes = response && response.nodes ? response.nodes : {};
				if (!nodes.postList || !nodes.types) {
					throw new Error('Missing Recently Edited menu nodes.');
				}

				writeClientMenuCache(nodes);
				hydrateRecentlyEditedMenu(nodes);
			})
			.fail(function () {
				replaceAdminBarNode(
					'recently-edited-post-list',
					'<div class="elodin-recently-edited-post-list"><div class="elodin-recently-edited-loading">Unable to load recently edited content.</div></div>',
				);
			})
			.always(function () {
				menuLoadRequest = null;
			});

		return menuLoadRequest;
	}

	function filterMenuItems($menu, query) {
		var normalized = normalizeSearchText(query);
		var matchCount = 0;
		var activeGroup = getActiveGroup($menu);

		getRowIndex().forEach(function (indexedRow) {
			if (!indexedRowMatchesGroup(indexedRow, activeGroup)) {
				indexedRow.item.style.display = 'none';
				return;
			}

			var matches =
				normalized === '' ||
				indexedRow.searchText.indexOf(normalized) !== -1;
			if (matches && normalized !== '') {
				matchCount += 1;
			}
			indexedRow.item.style.display = matches ? '' : 'none';
		});

		var $noMatchesItem = $menu.find('.elodin-recently-edited-no-matches');
		if (normalized === '') {
			$noMatchesItem.hide();
		} else {
			$noMatchesItem.toggle(matchCount === 0);
		}

		selectFirstVisibleRow();
	}

	function getOpenMenu() {
		return $('#wp-admin-bar-recently-edited.hover').first();
	}

	function getVisibleIndexedRows() {
		return getRowIndex().filter(function (indexedRow) {
			return (
				indexedRow.item.classList.contains('is-active') &&
				indexedRow.item.style.display !== 'none'
			);
		});
	}

	function setSelectedIndexedRow(indexedRow) {
		var $menu = $('#wp-admin-bar-recently-edited');
		$menu
			.find('.elodin-recently-edited-list-item.is-keyboard-selected')
			.removeClass('is-keyboard-selected');

		if (!indexedRow || !indexedRow.item) {
			return;
		}

		indexedRow.item.classList.add('is-keyboard-selected');
		indexedRow.item.scrollIntoView({ block: 'nearest' });
	}

	function selectFirstVisibleRow() {
		setSelectedIndexedRow(getVisibleIndexedRows()[0]);
	}

	function selectCurrentVisibleRowOrFirst() {
		var currentPostId = parseInt(ElodinRecentlyEdited.currentPostId || 0, 10);
		var currentRow = getVisibleIndexedRows().find(function (indexedRow) {
			if (indexedRow.row.classList.contains('elodin-recently-edited-row--current')) {
				return true;
			}

			if (!currentPostId) {
				return false;
			}

			return (
				$(indexedRow.row)
					.find('[data-post-id="' + currentPostId + '"]')
					.length > 0
			);
		});

		setSelectedIndexedRow(currentRow || getVisibleIndexedRows()[0]);
	}

	function getRowSelectionData($row) {
		var $resource = $row.find('[data-resource-type][data-resource-id]').first();
		if ($resource.length) {
			return {
				group: $row.attr('data-post-type') || $row.attr('data-related-group') || 'all',
				resourceId: String($resource.data('resourceId') || ''),
				resourceType: String($resource.data('resourceType') || ''),
			};
		}

		return null;
	}

	function storeRowSelection(menuId, $row) {
		var selection = getRowSelectionData($row);
		if (!selection || !selection.resourceId || !selection.resourceType) {
			sessionStorage.removeItem(selectionStorageKey(menuId));
			return;
		}

		sessionStorage.setItem(selectionStorageKey(menuId), JSON.stringify(selection));
	}

	function selectStoredVisibleRowOrCurrentOrFirst() {
		var $menu = $('#wp-admin-bar-recently-edited');
		var storedSelection = $menu.data('restoreSelection');
		var selectedRow = null;

		if (storedSelection && storedSelection.resourceId && storedSelection.resourceType) {
			selectedRow = getVisibleIndexedRows().find(function (indexedRow) {
				var $resource = $(indexedRow.row)
					.find(
						'[data-resource-type="' +
							storedSelection.resourceType +
							'"][data-resource-id="' +
							storedSelection.resourceId +
							'"]',
					)
					.first();

				return $resource.length > 0;
			});
		}

		if (selectedRow) {
			setSelectedIndexedRow(selectedRow);
			$menu.removeData('restoreSelection');
			return;
		}

		$menu.removeData('restoreSelection');
		selectCurrentVisibleRowOrFirst();
	}

	function selectRelativeVisibleRow(step) {
		var visibleRows = getVisibleIndexedRows();
		if (!visibleRows.length) {
			setSelectedIndexedRow(null);
			return;
		}

		var selectedIndex = visibleRows.findIndex(function (indexedRow) {
			return indexedRow.item.classList.contains('is-keyboard-selected');
		});

		if (selectedIndex < 0) {
			selectedIndex = step > 0 ? -1 : 0;
		}

		selectedIndex = Math.max(
			0,
			Math.min(visibleRows.length - 1, selectedIndex + step),
		);
		setSelectedIndexedRow(visibleRows[selectedIndex]);
	}

	function openSelectedRow(useEditUrl) {
		var selected = getVisibleIndexedRows().find(function (indexedRow) {
			return indexedRow.item.classList.contains('is-keyboard-selected');
		});

		if (!selected) {
			selected = getVisibleIndexedRows()[0];
		}
		if (!selected || !selected.row) {
			return;
		}

		var $row = $(selected.row);
		storeRowSelection('wp-admin-bar-recently-edited', $row);
		var $target = useEditUrl
			? $row.find('.elodin-recently-edited-edit').first()
			: $row.find('.elodin-recently-edited-title-link').first();
		if (!$target.length) {
			return;
		}

		$target.trigger('click');
	}

	function switchRelativeGroup(step) {
		var $menu = $('#wp-admin-bar-recently-edited');
		var $pills = $menu.find('.elodin-related-pill');
		if (!$pills.length) {
			return;
		}

		var activeIndex = $pills.index($pills.filter('.is-active').first());
		if (activeIndex < 0) {
			activeIndex = 0;
		}

		var nextIndex = (activeIndex + step + $pills.length) % $pills.length;
		switchRelatedGroup($menu, $pills.eq(nextIndex).data('relatedTarget'));
		selectFirstVisibleRow();
	}

	function focusRecentlyEditedSearch() {
		var $menu = $('#wp-admin-bar-recently-edited');
		if (!$menu.length) {
			return;
		}

		cancelClose($menu.attr('id'));
		$menu.addClass('hover elodin-recently-edited-grace-open');

		if ($menu.hasClass('elodin-recently-edited-is-lazy')) {
			var request = loadRecentlyEditedMenu();
			if (request) {
				request.done(function () {
					focusRecentlyEditedSearch();
				});
			}
			return;
		}

		var $input = $menu.find('.elodin-recently-edited-search-input').first();
		if (!$input.length) {
			return;
		}

		switchRelatedGroup($menu, 'all');
		$input.focus().select();
		selectFirstVisibleRow();
	}

	function getCurrentEditUrl() {
		if (ElodinRecentlyEdited.currentEditUrl) {
			return ElodinRecentlyEdited.currentEditUrl;
		}

		var $currentRowEdit = $(
			'#wp-admin-bar-recently-edited .elodin-recently-edited-row--current .elodin-recently-edited-edit',
		).first();
		if ($currentRowEdit.length && $currentRowEdit.data('url')) {
			return $currentRowEdit.data('url');
		}

		var $wpEditLink = $('#wp-admin-bar-edit > .ab-item').first();
		if ($wpEditLink.length && $wpEditLink.attr('href')) {
			return $wpEditLink.attr('href');
		}

		return '';
	}

	function getCurrentViewUrl() {
		if (ElodinRecentlyEdited.currentViewUrl) {
			return ElodinRecentlyEdited.currentViewUrl;
		}

		var $wpViewLink = $('#wp-admin-bar-view > .ab-item').first();
		if ($wpViewLink.length && $wpViewLink.attr('href')) {
			return $wpViewLink.attr('href');
		}

		return '';
	}

	function openCurrentToggleUrl() {
		var url = ElodinRecentlyEdited.isAdmin ? getCurrentViewUrl() : getCurrentEditUrl();
		if (!url || url === '#') {
			return false;
		}

		window.location.href = url;
		return true;
	}

	function handleMenuNavigationKeydown(e) {
		var $menu = getOpenMenu();
		if (!$menu.length || $menu.hasClass('elodin-recently-edited-is-lazy')) {
			return false;
		}

		if (e.key === 'Escape') {
			clearKeepOpenState($menu.attr('id'));
			return true;
		}

		if (e.key === 'ArrowDown') {
			selectRelativeVisibleRow(1);
			return true;
		}

		if (e.key === 'ArrowUp') {
			selectRelativeVisibleRow(-1);
			return true;
		}

		if (e.key === 'ArrowRight') {
			switchRelativeGroup(1);
			return true;
		}

		if (e.key === 'ArrowLeft') {
			switchRelativeGroup(-1);
			return true;
		}

		if (e.key === 'Enter') {
			openSelectedRow(isMac ? e.metaKey : e.ctrlKey);
			return true;
		}

		return false;
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

	function rowMatchesGroup($row, group) {
		if (group === 'all') {
			return true;
		}

		return (
			$row.attr('data-post-type') === group ||
			$row.attr('data-related-group') === group
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

		getRowIndex().forEach(function (indexedRow) {
			indexedRow.item.classList.toggle(
				'is-active',
				indexedRowMatchesGroup(indexedRow, target),
			);
		});

		filterMenuItems(
			$menu,
			$menu.find('.elodin-recently-edited-search-input').first().val(),
		);
		$menu.find('.elodin-recently-edited-post-list').scrollTop(0);
		selectFirstVisibleRow();
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

	function getMatchingTitleLinks(resourceType, resourceId) {
		return $(
			'#wp-admin-bar-recently-edited .elodin-recently-edited-title-link',
		).filter(function () {
			var $link = $(this);
			var linkResourceType = $link.data('resourceType') || 'post';
			var linkResourceId = $link.data('resourceId') || $link.data('postId');
			return (
				String(linkResourceType) === String(resourceType) &&
				String(linkResourceId) === String(resourceId)
			);
		});
	}

	function closeTitleEditor($input, savedTitle) {
		var $title = $input.closest('.elodin-recently-edited-title');
		var $link = $title.find('.elodin-recently-edited-title-link').first();

		if (typeof savedTitle === 'string') {
			$link.data('fullTitle', savedTitle).attr('data-full-title', savedTitle);
		}

		$title.removeClass('is-editing');
		$link.show();
		$input.remove();
	}

	function updateTitleRows(resourceType, resourceId, title, displayTitle, searchText) {
		var $links = getMatchingTitleLinks(resourceType, resourceId);
		$links.each(function () {
			var $link = $(this);
			$link.text(displayTitle).data('fullTitle', title).attr('data-full-title', title);
			$link.closest('.elodin-recently-edited-row').attr('data-search-text', searchText);
		});
		invalidateRowIndex();
	}

	function getMatchingSlugTexts(postId) {
		return $(
			'#wp-admin-bar-recently-edited .elodin-recently-edited-slug-text',
		).filter(function () {
			return String($(this).data('postId')) === String(postId);
		});
	}

	function closeSlugEditor($input, savedSlug) {
		var $slug = $input.closest('.elodin-recently-edited-slug');
		var $text = $slug.find('.elodin-recently-edited-slug-text').first();

		if (typeof savedSlug === 'string') {
			$text.data('fullSlug', savedSlug).attr('data-full-slug', savedSlug);
		}

		$slug.removeClass('is-editing');
		$text.show();
		$input.remove();
	}

	function copyTextWithFeedback($element, copyText, feedbackText) {
		var originalText = $element.text();

		function showCopied() {
			$element.text(feedbackText || 'Copied');
			window.setTimeout(function () {
				$element.text(originalText);
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
	}

	function updateSlugRows(postId, slug, displaySlug, searchText, titleUrl, copyUrl) {
		var $texts = getMatchingSlugTexts(postId);
		$texts.each(function () {
			var $text = $(this);
			var $row = $text.closest('.elodin-recently-edited-row');
			$text.text(displaySlug).data('fullSlug', slug).attr('data-full-slug', slug);
			if (copyUrl) {
				$text.attr('data-copy-text', copyUrl);
			}
			$row.attr('data-search-text', searchText);
			if (titleUrl) {
				$row
					.find(
						'.elodin-recently-edited-title-link[data-resource-type="post"]',
					)
					.data('url', titleUrl)
					.attr('data-url', titleUrl);
			}
		});
		invalidateRowIndex();
	}

	function saveSlugInput($input) {
		if ($input.data('saving')) {
			return;
		}

		var postId = $input.data('postId');
		var slug = $input.val();
		var original = $input.data('originalSlug');
		if (!postId) {
			closeSlugEditor($input);
			return;
		}

		if (slug === original) {
			closeSlugEditor($input);
			return;
		}

		$input.data('saving', true).prop('disabled', true);
		$.post(ElodinRecentlyEdited.ajaxUrl, {
			action: 'elodin_recently_edited_update_slug',
			post_id: postId,
			slug: slug,
			nonce: ElodinRecentlyEdited.nonceSlug,
		})
			.done(function (response) {
				if (response.success) {
					updateSlugRows(
						postId,
						response.data.slug,
						response.data.displaySlug,
						response.data.searchText,
						response.data.titleUrl,
						response.data.copyUrl,
					);
					closeSlugEditor($input, response.data.slug);
					clearClientMenuCache();
				} else {
					$input.prop('disabled', false).data('saving', false).focus();
					alert(
						'Error updating slug: ' +
							(response.data ? response.data.message : 'Unknown error'),
					);
				}
			})
			.fail(function () {
				$input.prop('disabled', false).data('saving', false).focus();
				alert('Failed to update slug.');
			});
	}

	function saveTitleInput($input) {
		if ($input.data('saving')) {
			return;
		}

		var resourceType = $input.data('resourceType') || 'post';
		var resourceId = $input.data('resourceId') || $input.data('postId');
		var title = $input.val();
		var original = $input.data('originalTitle');
		if (!resourceId) {
			closeTitleEditor($input);
			return;
		}

		if (title === original) {
			closeTitleEditor($input);
			return;
		}

		$input.data('saving', true).prop('disabled', true);
		$.post(ElodinRecentlyEdited.ajaxUrl, {
			action: 'elodin_recently_edited_update_title',
			resource_type: resourceType,
			resource_id: resourceId,
			post_id: resourceType === 'post' ? resourceId : 0,
			title: title,
			nonce: ElodinRecentlyEdited.nonceTitle,
		})
			.done(function (response) {
				if (response.success) {
					updateTitleRows(
						resourceType,
						resourceId,
						response.data.title,
						response.data.displayTitle,
						response.data.searchText,
					);
					closeTitleEditor($input, response.data.title);
					clearClientMenuCache();
				} else {
					$input.prop('disabled', false).data('saving', false).focus();
					alert(
						'Error updating title: ' +
							(response.data ? response.data.message : 'Unknown error'),
					);
				}
			})
			.fail(function () {
				$input.prop('disabled', false).data('saving', false).focus();
				alert('Failed to update title.');
			});
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
			if ($('#' + menuId).find(':focus').length) {
				scheduleClose(menuId);
				return;
			}
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
		sessionStorage.removeItem(selectionStorageKey(menuId));
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
				var storedSelection = sessionStorage.getItem(selectionStorageKey(menuId));
				var $menu = $('#' + menuId);
				if (storedGroup) {
					$menu.data('restoreGroup', storedGroup);
				}
				if (storedSelection) {
					try {
						$menu.data('restoreSelection', JSON.parse(storedSelection));
					} catch (error) {
						$menu.removeData('restoreSelection');
					}
				}
				// Clear the flag
				sessionStorage.removeItem(storageKey(menuId));
				sessionStorage.removeItem(groupStorageKey(menuId));
				sessionStorage.removeItem(selectionStorageKey(menuId));

				// Add hover class to keep menu open
				$menu.addClass('hover');
				window.setTimeout(function () {
					if (storedGroup) {
						switchRelatedGroup($menu, storedGroup);
					}
					restoreScrollPosition(menuId);
					selectStoredVisibleRowOrCurrentOrFirst();
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

		if (e.metaKey || e.ctrlKey || $(this).data('newTab') === true) {
			window.open(url, '_blank', 'noopener');
			return;
		}

		// Set flag to keep menu open after navigation
		var menuId = getMenuIdFromElement($(this));
		storeRowSelection(menuId, $(this).closest('.elodin-recently-edited-row'));
		saveActiveGroup(menuId);
		saveScrollPosition(menuId);
		sessionStorage.setItem(storageKey(menuId), 'true');

		window.location.href = url;
	});

	/**
	 * Switch content type lists on click without navigating.
	 */
	$(document).on(
		'click',
		'#wp-admin-bar-recently-edited .elodin-related-pill',
		function (e) {
			e.preventDefault();
			e.stopPropagation();

			var $pill = $(this);
			var menuId = 'wp-admin-bar-recently-edited';
			switchRelatedGroup($('#' + menuId), $pill.data('relatedTarget'));
			saveActiveGroup(menuId);
			saveScrollPosition(menuId);
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

	$(document).on('click', '.elodin-recently-edited-load-button', function (e) {
		e.preventDefault();
		e.stopPropagation();
		$(this).prop('disabled', true).text('Loading...');
		loadRecentlyEditedMenu();
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
			var menuId = getMenuIdFromElement($(this));
			switchRelatedGroup($('#' + menuId), 'all');
		},
	);

	$(document).on(
		'keydown',
		'.elodin-recently-edited-search-input',
		function (e) {
			if (handleMenuNavigationKeydown(e)) {
				e.preventDefault();
				e.stopPropagation();
			}
		},
	);

	$(document).on('keydown', function (e) {
		var isSearchShortcut =
			e.shiftKey &&
			!e.altKey &&
			isKeyboardEventForE(e) &&
			(isMac ? e.metaKey && !e.ctrlKey : e.ctrlKey && !e.metaKey);
		var isCurrentEditShortcut =
			!e.shiftKey &&
			e.altKey &&
			isKeyboardEventForE(e) &&
			(isMac ? e.metaKey && !e.ctrlKey : e.ctrlKey && !e.metaKey);

		if (isSearchShortcut) {
			focusRecentlyEditedSearch();
			e.preventDefault();
			e.stopPropagation();
			return;
		}

		if (isCurrentEditShortcut && openCurrentToggleUrl()) {
			e.preventDefault();
			e.stopPropagation();
			return;
		}

		if (
			getOpenMenu().length &&
			!$(e.target).is('input, textarea, select') &&
			!$(e.target).closest('[contenteditable="true"]').length &&
			handleMenuNavigationKeydown(e)
		) {
			e.preventDefault();
			e.stopPropagation();
			return;
		}

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
		if ($menu.hasClass('elodin-recently-edited-is-lazy')) {
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
	updateSearchPlaceholder();
	checkAndRestoreMenuState();
	if (!hydrateRecentlyEditedMenuFromCache()) {
		scheduleMenuIndexBuild();
	}

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
						clearClientMenuCache();
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
					'select.elodin-recently-edited-form-status-select',
				) ||
				$(e.target).is(
					'select.elodin-recently-edited-post-type-select',
				) ||
				$(e.target).is(
					'.elodin-recently-edited-title-input, .elodin-recently-edited-slug-input',
				) ||
				$(e.target).closest(
					'select.elodin-recently-edited-status-select',
				).length ||
				$(e.target).closest(
					'select.elodin-recently-edited-form-status-select',
				).length ||
				$(e.target).closest(
					'select.elodin-recently-edited-post-type-select',
				).length ||
				$(e.target).closest(
					'.elodin-recently-edited-title-input',
				).length ||
				$(e.target).closest(
					'.elodin-recently-edited-slug-input',
				).length
			) {
				e.preventDefault();
				e.stopPropagation();
			}
		},
	);

	/**
	 * Prevent form control clicks from bubbling up.
	 */
	$(document).on(
		'click',
		'.elodin-recently-edited-status-select, .elodin-recently-edited-form-status-select, .elodin-recently-edited-post-type-select, .elodin-recently-edited-title-input, .elodin-recently-edited-slug-input',
		function (e) {
			if ($(this).is('.elodin-recently-edited-title-input, .elodin-recently-edited-slug-input')) {
				e.stopPropagation();
				return;
			}

			e.preventDefault();
			e.stopPropagation();
		},
	);

	/**
	 * Open an inline title editor when clicking unused title-cell space.
	 */
	$(document).on(
		'click',
		'.elodin-recently-edited-title',
		function (e) {
			if (
				$(e.target).closest(
					'.elodin-recently-edited-title-link, .elodin-recently-edited-title-input',
				).length
			) {
				return;
			}

			e.preventDefault();
			e.stopPropagation();
			var $title = $(this);
			if ($title.hasClass('elodin-recently-edited-title--locked')) {
				return;
			}

			var $link = $title.find('.elodin-recently-edited-title-link').first();
			var originalTitle = $link.attr('data-full-title') || '';
			var resourceType = $link.data('resourceType') || 'post';
			var resourceId = $link.data('resourceId') || $link.data('postId');

			$title.find('.elodin-recently-edited-title-input').remove();
			$title.addClass('is-editing');
			$link.hide();

			var $input = $('<input>', {
				class: 'elodin-recently-edited-title-input',
				type: 'text',
				value: originalTitle,
			})
				.data('resourceType', resourceType)
				.data('resourceId', resourceId)
				.data('postId', resourceType === 'post' ? resourceId : 0)
				.data('originalTitle', originalTitle);

			$input.on('keydown', function (event) {
				if (event.key !== 'Enter' && event.key !== 'Escape') {
					return;
				}

				event.preventDefault();
				event.stopPropagation();
				event.stopImmediatePropagation();

				if (event.key === 'Enter') {
					saveTitleInput($input);
					return;
				}

				$input.data('cancelTitleEdit', true);
				closeTitleEditor($input);
			});

			$title.append($input);
			$input.focus().select();
		},
	);

	$(document).on(
		'keydown',
		'.elodin-recently-edited-title-input',
		function (e) {
			var $input = $(this);
			if (e.key === 'Enter') {
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();
				saveTitleInput($input);
			}
			if (e.key === 'Escape') {
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();
				$input.data('cancelTitleEdit', true);
				closeTitleEditor($input);
			}
		},
	);

	$(document).on('blur', '.elodin-recently-edited-title-input', function () {
		var $input = $(this);
		if ($input.data('cancelTitleEdit') || $input.data('saving')) {
			return;
		}
		saveTitleInput($input);
	});

	/**
	 * Open an inline slug editor when clicking the slug cell.
	 */
	$(document).on(
		'click',
		'.elodin-recently-edited-slug',
		function (e) {
			if (
				$(e.target).closest(
					'.elodin-recently-edited-slug-text, .elodin-recently-edited-slug-input',
				).length
			) {
				return;
			}

			e.preventDefault();
			e.stopPropagation();
			var $slug = $(this);
			if ($slug.hasClass('elodin-recently-edited-slug--locked')) {
				return;
			}

			var $text = $slug.find('.elodin-recently-edited-slug-text').first();
			var originalSlug = $text.attr('data-full-slug') || '';
			var postId = $text.data('postId');

			$slug.find('.elodin-recently-edited-slug-input').remove();
			$slug.addClass('is-editing');
			$text.hide();

			var $input = $('<input>', {
				class: 'elodin-recently-edited-slug-input',
				type: 'text',
				value: originalSlug,
			})
				.data('postId', postId)
				.data('originalSlug', originalSlug);

			$slug.append($input);
			$input.focus().select();
		},
	);

	$(document).on(
		'keydown',
		'.elodin-recently-edited-slug-input',
		function (e) {
			var $input = $(this);
			if (e.key === 'Enter') {
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();
				saveSlugInput($input);
			}
			if (e.key === 'Escape') {
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();
				$input.data('cancelSlugEdit', true);
				closeSlugEditor($input);
			}
		},
	);

	$(document).on('blur', '.elodin-recently-edited-slug-input', function () {
		var $input = $(this);
		if ($input.data('cancelSlugEdit') || $input.data('saving')) {
			return;
		}
		saveSlugInput($input);
	});

	/**
	 * Copy the full URL when clicking directly on slug text.
	 */
	$(document).on('click', '.elodin-recently-edited-slug-text', function (e) {
		e.preventDefault();
		e.stopPropagation();

		var $slug = $(this);
		var copyText = $slug.attr('data-copy-text');
		if (!copyText) {
			return;
		}

		copyTextWithFeedback($slug, copyText, 'Copied');
	});

	/**
	 * Copy post ID or row-specific copy text on click and show feedback
	 */
	$(document).on('click', '.elodin-recently-edited-id', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var $id = $(this);
		var postId = $id.data('id');
		if (!postId) {
			return;
		}

		var copyText = $id.attr('data-copy-text') || String(postId);
		var feedbackText = $id.attr('data-copy-feedback') || 'Copied';

		copyTextWithFeedback($id, copyText, feedbackText);
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
						invalidateRowIndex();
						clearClientMenuCache();
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
	 * Handle status change for Gravity Forms forms
	 */
	$(document).on(
		'change',
		'.elodin-recently-edited-form-status-select',
		function (e) {
			e.preventDefault();
			var $select = $(this);
			var formId = $select.data('formId');
			var status = $select.val();
			var original = $select.data('original');
			if (!formId || !status) {
				return;
			}

			$.post(ElodinRecentlyEdited.ajaxUrl, {
				action: 'elodin_recently_edited_update_gravity_form_status',
				form_id: formId,
				status: status,
				nonce: ElodinRecentlyEdited.nonceStatus,
			})
				.done(function (response) {
					if (response.success) {
						$(
							'#wp-admin-bar-recently-edited .elodin-recently-edited-form-status-select',
						)
							.filter(function () {
								return String($(this).data('formId')) === String(formId);
							})
							.data('original', status)
							.val(status);
						clearClientMenuCache();
					} else {
						$select.val(original);
						alert(
							'Error updating form status: ' +
								(response.data
									? response.data.message
									: 'Unknown error'),
						);
					}
				})
				.fail(function () {
					$select.val(original);
					alert('Failed to update form status.');
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
						invalidateRowIndex();
						clearClientMenuCache();
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

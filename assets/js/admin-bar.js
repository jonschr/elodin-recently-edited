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
	var forceClosedMenus = {};
	var menuLoadRequest = null;
	var mediaLoadRequest = null;
	var mediaItems = [];
	var metaInspectorCache = {};
	var metaInspectorFullKeys = {};
	var metaInspectorRequest = null;
	var metaInspectorTrigger = null;
	var shellRevealTimer = null;
	var shellRevealDelayMs = 700;
	var editorSaveRefreshTimer = null;
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

	function searchStorageKey(menuId) {
		return 'elodin_recently_edited_search_query';
	}

	function stateStorageKey(menuId) {
		return 'elodin_recently_edited_menu_state';
	}

	function targetUrlStorageKey(menuId) {
		return 'elodin_recently_edited_target_url';
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
			'Search this site\'s content... (' + getSearchShortcutLabel() + ')',
		);
	}

	function updateCurrentRowHighlight($menu) {
		var currentPostId = parseInt(ElodinRecentlyEdited.currentPostId || 0, 10);
		$menu
			.find('.elodin-recently-edited-row--current')
			.removeClass('elodin-recently-edited-row--current');

		if (!currentPostId) {
			return;
		}

		$menu
			.find('.elodin-recently-edited-row')
			.filter(function () {
				return (
					$(this)
						.find('[data-post-id="' + currentPostId + '"]')
						.length > 0
				);
			})
			.addClass('elodin-recently-edited-row--current');
	}

	function getScrollbarWidth() {
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

		return scrollbarWidth;
	}

	function setScrollbarWidthVariable() {
		var scrollbarWidth = getScrollbarWidth();

		$('#wp-admin-bar-recently-edited').css(
			'--elodin-scrollbar-width',
			scrollbarWidth + 'px',
		);
	}

	function setPageScrollbarCompensation() {
		var root = document.documentElement;
		var body = document.body;
		var pageHeight = Math.max(
			root.scrollHeight,
			body ? body.scrollHeight : 0,
			root.offsetHeight,
			body ? body.offsetHeight : 0,
		);
		var hasPageScrollbar = pageHeight > window.innerHeight;
		var scrollbarWidth = getScrollbarWidth();
		var compensation = hasPageScrollbar ? 0 : scrollbarWidth;

		$('#wp-admin-bar-recently-edited').css(
			'--elodin-page-scrollbar-compensation',
			compensation + 'px',
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
			window.localStorage.removeItem(getClientCacheKey() + '_media_v2');
		} catch (error) {
			// no-op
		}
	}

	function announce(message) {
		var $region = $('.elodin-recently-edited-live-region').first();
		if (!$region.length) {
			return;
		}
		$region.text('');
		window.setTimeout(function () {
			$region.text(message || '');
		}, 20);
	}

	function updateSearchClearButton($menu) {
		$menu = $menu && $menu.length ? $menu : $('#wp-admin-bar-recently-edited');
		var hasQuery = String(
			$menu.find('.elodin-recently-edited-search-input').first().val() || '',
		).length > 0;
		$menu.find('.elodin-recently-edited-search-clear').toggleClass('is-visible', hasQuery);
	}

	function clearSearch($menu, focusInput) {
		var $input = $menu.find('.elodin-recently-edited-search-input').first();
		$input.val('');
		filterMenuItems($menu, '');
		updateSearchClearButton($menu);
		if (focusInput && $input.length) {
			$input.focus();
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
		reconcileReviewControls($menu);
		updateSearchPlaceholder();
		updateCurrentRowHighlight($menu);

		$menu
			.find('.elodin-recently-edited-shell-hidden')
			.removeClass('elodin-recently-edited-shell-hidden');
		$menu
			.find('.elodin-recently-edited-shell-item')
			.removeClass('elodin-recently-edited-shell-item');

		if (shellRevealTimer) {
			window.clearTimeout(shellRevealTimer);
			shellRevealTimer = null;
		}
		$menu.removeClass(
			'elodin-recently-edited-is-lazy elodin-recently-edited-shell-pending',
		);
		setScrollbarWidthVariable();
		setPageScrollbarCompensation();

		var storedGroup =
			sessionStorage.getItem(groupStorageKey($menu.attr('id'))) ||
			$menu.data('restoreGroup');
		var storedSearch = $menu.data('restoreSearch');
		if (storedGroup) {
			switchRelatedGroup($menu, storedGroup);
		}
		$menu.removeData('restoreGroup');
		if (typeof storedSearch === 'string' && storedSearch !== '') {
			$menu.find('.elodin-recently-edited-search-input').first().val(storedSearch);
			filterMenuItems($menu, storedSearch, { skipSelection: true });
		}
		updateSearchClearButton($menu);
		sortRowsByPinnedState();
		$menu.removeData('restoreSearch');
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

	function revealLazyShellIfStillNeeded() {
		var $menu = $('#wp-admin-bar-recently-edited');
		if (!$menu.length || !$menu.hasClass('elodin-recently-edited-is-lazy')) {
			return;
		}

		$menu.removeClass('elodin-recently-edited-shell-pending');
	}

	function scheduleLazyShellReveal(delay) {
		if (shellRevealTimer) {
			window.clearTimeout(shellRevealTimer);
		}

		shellRevealTimer = window.setTimeout(function () {
			shellRevealTimer = null;
			revealLazyShellIfStillNeeded();
		}, delay);
	}

	function scheduleMenuIndexBuild() {
		if (hydrateRecentlyEditedMenuFromCache()) {
			return;
		}

		scheduleLazyShellReveal(shellRevealDelayMs);
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
		if (
			!$menu.length ||
			(!$menu.hasClass('elodin-recently-edited-is-lazy') && !options.force)
		) {
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
					revealLazyShellIfStillNeeded();
					menuLoadRequest = null;
					return;
				}

				var nodes = response && response.nodes ? response.nodes : {};
				if (!nodes.postList || !nodes.types) {
					throw new Error('Missing Recently Edited menu nodes.');
				}

				var responseCacheSchema = parseInt(response.cacheSchema || 0, 10);
				if (
					responseCacheSchema &&
					responseCacheSchema !== parseInt(ElodinRecentlyEdited.cacheSchema || 1, 10)
				) {
					clearClientMenuCache();
					ElodinRecentlyEdited.cacheSchema = responseCacheSchema;
				}

				writeClientMenuCache(nodes);
				hydrateRecentlyEditedMenu(nodes);
			})
			.fail(function () {
				revealLazyShellIfStillNeeded();
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

	/**
	 * Refresh the already-rendered menu after a successful block-editor save.
	 *
	 * Preserve the user's active content type and search while replacing the
	 * row index with the freshly sorted server response.
	 */
	function refreshRecentlyEditedMenuAfterSave() {
		if (editorSaveRefreshTimer) {
			window.clearTimeout(editorSaveRefreshTimer);
		}

		editorSaveRefreshTimer = window.setTimeout(function () {
			editorSaveRefreshTimer = null;

			if (menuLoadRequest) {
				menuLoadRequest.always(refreshRecentlyEditedMenuAfterSave);
				return;
			}

			var $menu = $('#wp-admin-bar-recently-edited');
			if (!$menu.length) {
				return;
			}

			$menu.data('restoreGroup', getActiveGroup($menu));
			$menu.data(
				'restoreSearch',
				String($menu.find('.elodin-recently-edited-search-input').first().val() || ''),
			);
			saveScrollPosition($menu.attr('id'));
			clearClientMenuCache();
			loadRecentlyEditedMenu({ force: true });
		}, 100);
	}

	/**
	 * Watch Gutenberg's editor store for completed manual saves.
	 *
	 * Autosaves are revisions and intentionally do not reorder Recently Edited.
	 */
	function watchBlockEditorSaves() {
		if (
			!ElodinRecentlyEdited.isAdmin ||
			!window.wp ||
			!window.wp.data ||
			typeof window.wp.data.select !== 'function' ||
			typeof window.wp.data.subscribe !== 'function'
		) {
			return;
		}

		function selectEditorStore() {
			try {
				return window.wp.data.select('core/editor');
			} catch (error) {
				return null;
			}
		}

		var editor = selectEditorStore();
		if (!editor || typeof editor.isSavingPost !== 'function') {
			return;
		}

		var wasSaving = Boolean(editor.isSavingPost());
		var saveWasAutosave =
			wasSaving &&
			typeof editor.isAutosavingPost === 'function' &&
			Boolean(editor.isAutosavingPost());

		window.wp.data.subscribe(function () {
			editor = selectEditorStore();
			if (!editor || typeof editor.isSavingPost !== 'function') {
				return;
			}

			var isSaving = Boolean(editor.isSavingPost());
			var isAutosaving =
				typeof editor.isAutosavingPost === 'function' &&
				Boolean(editor.isAutosavingPost());

			if (isSaving && !wasSaving) {
				saveWasAutosave = isAutosaving;
			}

			if (wasSaving && !isSaving && !saveWasAutosave) {
				var saveSucceeded =
					typeof editor.didPostSaveRequestSucceed !== 'function' ||
					Boolean(editor.didPostSaveRequestSucceed());
				var saveError =
					typeof editor.getLastPostSaveError === 'function'
						? editor.getLastPostSaveError()
						: null;

				if (saveSucceeded && !saveError) {
					refreshRecentlyEditedMenuAfterSave();
				}
			}

			wasSaving = isSaving;
			if (!isSaving) {
				saveWasAutosave = false;
			}
		});
	}

	function readClientMediaCache() {
		try {
			var raw = window.localStorage.getItem(getClientCacheKey() + '_media_v2');
			var cached = raw ? JSON.parse(raw) : null;
			return cached && Array.isArray(cached.items) ? cached.items : null;
		} catch (error) {
			return null;
		}
	}

	function writeClientMediaCache(items) {
		try {
			window.localStorage.setItem(
				getClientCacheKey() + '_media_v2',
				JSON.stringify({ createdAt: Date.now(), items: items }),
			);
		} catch (error) {
			// Storage can fail in private browsing or when quota is full.
		}
	}

	function getMediaGrid() {
		var $list = $('#wp-admin-bar-recently-edited .elodin-recently-edited-post-list').first();
		var $grid = $list.children('.elodin-recently-edited-media-grid').first();
		if (!$grid.length) {
			$grid = $('<div>', {
				class: 'elodin-recently-edited-media-grid',
				role: 'list',
				'aria-label': 'Recently modified media',
			}).appendTo($list);
		}
		return $grid;
	}

	function renderMediaItems(items) {
		mediaItems = Array.isArray(items) ? items : [];
		var $grid = getMediaGrid().empty();

		mediaItems.forEach(function (item) {
			var dimensions = item.width && item.height ? item.width + '×' + item.height : item.mimeType || '';
			var searchText = normalizeSearchText(
				[item.filename, item.title, item.alt, item.caption, item.mimeType, item.id].join(' '),
			);
			var $card = $('<div>', {
				class: 'elodin-recently-edited-media-card',
				role: 'listitem',
				tabindex: '-1',
				'data-search-text': searchText,
				'data-media-id': item.id,
			});
			var $preview = $('<button>', {
				type: 'button',
				class: 'elodin-recently-edited-media-preview',
				'aria-label': 'Preview ' + item.filename,
			}).attr('data-url', item.url || '');
			if (item.thumbnail) {
				$('<img>', {
					src: item.thumbnail,
					alt: '',
					loading: 'lazy',
				}).appendTo($preview);
			}
			$preview.appendTo($card);

			$('<button>', {
				type: 'button',
				class: 'elodin-recently-edited-media-filename',
				text: item.filename,
				title: 'Copy filename: ' + item.filename,
			}).attr('data-copy-text', item.filename).appendTo($card);

			var $meta = $('<div>', { class: 'elodin-recently-edited-media-meta' }).appendTo($card);
			$('<span>', { text: item.modifiedAgo || '' })
				.attr('title', item.modifiedAt ? 'Last updated: ' + item.modifiedAt : '')
				.appendTo($meta);
			$('<span>', { text: dimensions }).appendTo($meta);

			var $actions = $('<div>', { class: 'elodin-recently-edited-media-actions' }).appendTo($card);
			$('<button>', {
				type: 'button',
				class: 'elodin-recently-edited-media-copy-url',
				text: 'Copy URL',
			}).attr('data-copy-text', item.url || '').appendTo($actions);
			$('<button>', {
				type: 'button',
				class: 'elodin-recently-edited-media-edit',
				text: 'Edit',
			}).attr('data-url', item.editUrl || '').appendTo($actions);

			$card.appendTo($grid);
		});

		$('<div>', {
			class: 'elodin-recently-edited-media-empty',
			text: (ElodinRecentlyEdited.strings || {}).noMediaMatches || 'No media matches found.',
		}).appendTo($grid);

		filterMediaItems(
			$('#wp-admin-bar-recently-edited .elodin-recently-edited-search-input').first().val(),
		);
	}

	function filterMediaItems(query) {
		var $grid = getMediaGrid();
		var normalized = normalizeSearchText(query);
		var matches = 0;
		$grid.children('.elodin-recently-edited-media-card').each(function () {
			var isMatch = !normalized || String($(this).attr('data-search-text') || '').indexOf(normalized) !== -1;
			$(this).toggle(isMatch);
			if (isMatch) {
				matches += 1;
			}
		});
		$grid.children('.elodin-recently-edited-media-empty').toggle(matches === 0);
		var $selected = $grid.children('.elodin-recently-edited-media-card.is-keyboard-selected:visible').first();
		if (!$selected.length) {
			setSelectedMediaCard(null);
		}
	}

	function getVisibleMediaCards() {
		return getMediaGrid().children('.elodin-recently-edited-media-card:visible');
	}

	function getMediaColumnCount() {
		var grid = getMediaGrid()[0];
		if (!grid || !window.getComputedStyle) {
			return 5;
		}

		var template = window.getComputedStyle(grid).gridTemplateColumns || '';
		var columns = template.trim() ? template.trim().split(/\s+/).length : 0;
		return Math.max(1, columns || 5);
	}

	function setSelectedMediaCard($card) {
		var $cards = getMediaGrid().children('.elodin-recently-edited-media-card');
		$cards.removeClass('is-keyboard-selected').attr('tabindex', '-1');
		if (!$card || !$card.length) {
			return;
		}
		$card.addClass('is-keyboard-selected').attr('tabindex', '0');
		if ($card[0] && typeof $card[0].scrollIntoView === 'function') {
			$card[0].scrollIntoView({ block: 'nearest' });
		}
	}

	function selectRelativeMediaCard(step) {
		var $cards = getVisibleMediaCards();
		if (!$cards.length) {
			return;
		}
		var currentIndex = $cards.index($cards.filter('.is-keyboard-selected').first());
		if (currentIndex < 0) {
			setSelectedMediaCard($cards.eq(0));
			return;
		}
		if (step < 0 && currentIndex < getMediaColumnCount()) {
			setSelectedMediaCard(null);
			return;
		}
		setSelectedMediaCard($cards.eq(Math.max(0, Math.min($cards.length - 1, currentIndex + step))));
	}

	function activateSelectedMediaCard(useEditUrl) {
		var $card = getVisibleMediaCards().filter('.is-keyboard-selected').first();
		if (!$card.length) {
			$card = getVisibleMediaCards().first();
		}
		if (!$card.length) {
			return;
		}
		if (useEditUrl) {
			$card.find('.elodin-recently-edited-media-edit').first().trigger('click');
			return;
		}
		$card.find('.elodin-recently-edited-media-filename').first().trigger('click');
	}

	function loadMediaItems() {
		var cached = readClientMediaCache();
		if (cached) {
			renderMediaItems(cached);
			return null;
		}
		if (mediaLoadRequest) {
			return mediaLoadRequest;
		}

		var $grid = getMediaGrid().empty().append(
			$('<div>', {
				class: 'elodin-recently-edited-media-loading',
				text: (ElodinRecentlyEdited.strings || {}).loadingMedia || 'Loading media...',
			}),
		);
		mediaLoadRequest = $.ajax({
			url: ElodinRecentlyEdited.mediaRestUrl,
			method: 'GET',
			beforeSend: function (xhr) {
				xhr.setRequestHeader('X-WP-Nonce', ElodinRecentlyEdited.restNonce);
			},
		})
			.done(function (response) {
				var items = response && Array.isArray(response.items) ? response.items : [];
				writeClientMediaCache(items);
				renderMediaItems(items);
			})
			.fail(function () {
				$grid.empty().append(
					$('<div>', {
						class: 'elodin-recently-edited-media-loading',
						text: (ElodinRecentlyEdited.strings || {}).unableToLoadMedia || 'Unable to load media.',
					}),
				);
			})
			.always(function () {
				mediaLoadRequest = null;
			});

		return mediaLoadRequest;
	}

	function formatMetaValueSize(bytes) {
		bytes = parseInt(bytes || 0, 10);
		if (bytes < 1024) {
			return bytes + ' B';
		}
		if (bytes < 1024 * 1024) {
			return (bytes / 1024).toFixed(bytes < 10240 ? 1 : 0) + ' KB';
		}
		return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
	}

	function getMetaInspector() {
		var $menu = $('#wp-admin-bar-recently-edited');
		var $wrapper = $menu.children('.ab-sub-wrapper').first();
		var $panel = $wrapper.children('.elodin-recently-edited-meta-inspector').first();
		if (!$panel.length) {
			$panel = $('<section>', {
				class: 'elodin-recently-edited-meta-inspector',
				role: 'dialog',
				'aria-modal': 'true',
				'aria-label': 'Post Meta Inspector',
			}).appendTo($wrapper);
		}
		return $panel;
	}

	function buildMetaInspectorShell(postId, postTitle) {
		var $panel = getMetaInspector().empty().attr('data-post-id', postId);
		var $header = $('<header>', { class: 'elodin-recently-edited-meta-header' }).appendTo($panel);
		$('<button>', {
			type: 'button',
			class: 'elodin-recently-edited-meta-back',
			text: '← Back',
		}).appendTo($header);
		var $heading = $('<div>', { class: 'elodin-recently-edited-meta-heading' }).appendTo($header);
		$('<strong>', { text: 'Meta Inspector' }).appendTo($heading);
		$('<span>', { text: postTitle || 'Post #' + postId }).appendTo($heading);
		$('<input>', {
			type: 'search',
			class: 'elodin-recently-edited-meta-search',
			placeholder: 'Search meta keys and values...',
			'aria-label': 'Search meta keys and values',
		}).appendTo($header);
		$('<div>', {
			class: 'elodin-recently-edited-meta-summary',
			'aria-live': 'polite',
		}).appendTo($panel);
		$('<div>', { class: 'elodin-recently-edited-meta-list' }).appendTo($panel);
		$('<footer>', {
			class: 'elodin-recently-edited-meta-footer',
			text: 'Read only • sensitive-looking values are redacted • values are not stored in browser cache',
		}).appendTo($panel);
		return $panel;
	}

	function renderMetaValue($container, item, valueRecord, valueIndex, isFull) {
		var type = String(valueRecord.type || 'string');
		var value = String(valueRecord.value == null ? '' : valueRecord.value);
		var structured = type === 'array' || type === 'object';
		var $value = $('<div>', {
			class: 'elodin-recently-edited-meta-value' + (item.redacted ? ' is-redacted' : ''),
		}).appendTo($container);
		var descriptor = type + ' • ' + formatMetaValueSize(valueRecord.size);

		if (structured) {
			var $details = $('<details>', { class: 'elodin-recently-edited-meta-details' }).appendTo($value);
			$('<summary>', { text: descriptor }).appendTo($details);
			$('<pre>', { text: value }).appendTo($details);
		} else {
			$('<div>', { class: 'elodin-recently-edited-meta-value-type', text: descriptor }).appendTo($value);
			$('<pre>', { text: value }).appendTo($value);
		}

		var $actions = $('<div>', { class: 'elodin-recently-edited-meta-value-actions' }).appendTo($value);
		if (!item.redacted && !valueRecord.truncated) {
			$('<button>', {
				type: 'button',
				class: 'elodin-recently-edited-meta-copy-value',
				text: 'Copy value',
			})
				.attr('data-copy-text', value)
				.appendTo($actions);
		}
		if (valueRecord.truncated && !isFull) {
			$('<button>', {
				type: 'button',
				class: 'elodin-recently-edited-meta-load-full',
				text: 'Load full value',
			})
				.attr({ 'data-meta-key': item.key, 'data-value-index': valueIndex })
				.appendTo($actions);
		} else if (valueRecord.truncated) {
			$('<span>', { text: 'Display limit reached' }).appendTo($actions);
		}
	}

	function canRenderCompactMetaItem(item) {
		if (!item || !Array.isArray(item.values) || item.values.length !== 1) {
			return false;
		}
		var record = item.values[0];
		return (
			record &&
			!record.truncated &&
			record.type !== 'array' &&
			record.type !== 'object'
		);
	}

	function renderCompactMetaItem($item, item) {
		var record = item.values[0];
		var value = String(record.value == null ? '' : record.value);
		$item.addClass('is-compact' + (item.redacted ? ' is-redacted' : ''));
		$('<code>', {
			class: 'elodin-recently-edited-meta-compact-key',
			text: item.key,
			title: item.key,
		}).appendTo($item);
		$('<span>', {
			class: 'elodin-recently-edited-meta-compact-type',
			text: String(record.type || 'string') + ' • ' + formatMetaValueSize(record.size),
		}).appendTo($item);
		$('<code>', {
			class: 'elodin-recently-edited-meta-compact-value',
			text: value === '' ? '""' : value,
			title: value,
		}).appendTo($item);
		$('<button>', {
			type: 'button',
			class: 'elodin-recently-edited-meta-copy-key',
			text: 'Copy key',
		})
			.attr('data-copy-text', item.key)
			.appendTo($item);
		if (!item.redacted) {
			$('<button>', {
				type: 'button',
				class: 'elodin-recently-edited-meta-copy-value',
				text: 'Copy value',
			})
				.attr('data-copy-text', value)
				.appendTo($item);
		}
	}

	function renderMetaInspector(response) {
		var $panel = getMetaInspector();
		var $list = $panel.find('.elodin-recently-edited-meta-list').empty();
		var items = response && Array.isArray(response.items) ? response.items : [];
		var postId = response && response.post ? response.post.id : parseInt($panel.attr('data-post-id'), 10);
		var fullKeys = metaInspectorFullKeys[postId] || {};

		items.forEach(function (item) {
			var searchable = [item.key]
				.concat((item.values || []).map(function (record) { return record.value || ''; }))
				.join(' ');
			var $item = $('<article>', {
				class: 'elodin-recently-edited-meta-item',
				'data-meta-key': item.key,
				'data-search-text': normalizeSearchText(searchable),
			}).appendTo($list);
			if (canRenderCompactMetaItem(item)) {
				renderCompactMetaItem($item, item);
				return;
			}
			var $itemHeader = $('<div>', { class: 'elodin-recently-edited-meta-item-header' }).appendTo($item);
			$('<code>', { text: item.key }).appendTo($itemHeader);
			$('<span>', {
				class: 'elodin-recently-edited-meta-count',
				text: item.count === 1 ? '1 value' : item.count + ' values',
			}).appendTo($itemHeader);
			$('<button>', {
				type: 'button',
				class: 'elodin-recently-edited-meta-copy-key',
				text: 'Copy key',
			})
				.attr('data-copy-text', item.key)
				.appendTo($itemHeader);
			(item.values || []).forEach(function (record, valueIndex) {
				renderMetaValue($item, item, record, valueIndex, Boolean(fullKeys[item.key]));
			});
		});

		$('<div>', {
			class: 'elodin-recently-edited-meta-empty',
			text: (ElodinRecentlyEdited.strings || {}).noMetaMatches || 'No meta keys match this search.',
		}).toggle(items.length === 0).appendTo($list);
		$panel.find('.elodin-recently-edited-meta-summary').text(
			items.length === 1 ? '1 meta key' : items.length + ' meta keys',
		);
		filterMetaInspector($panel.find('.elodin-recently-edited-meta-search').val());
	}

	function filterMetaInspector(query) {
		var $panel = getMetaInspector();
		var normalized = normalizeSearchText(query);
		var matches = 0;
		$panel.find('.elodin-recently-edited-meta-item').each(function () {
			var match = !normalized || String($(this).attr('data-search-text') || '').indexOf(normalized) !== -1;
			$(this).toggle(match);
			if (match) {
				matches += 1;
			}
		});
		$panel.find('.elodin-recently-edited-meta-empty').toggle(matches === 0);
		$panel.find('.elodin-recently-edited-meta-summary').text(
			matches === 1 ? '1 matching meta key' : matches + ' matching meta keys',
		);
	}

	function requestPostMeta(postId, key) {
		var url = String(ElodinRecentlyEdited.metaRestUrl || '') + postId + '/meta';
		if (key) {
			url += '?key=' + encodeURIComponent(key);
		}
		return $.ajax({
			url: url,
			method: 'GET',
			cache: false,
			beforeSend: function (xhr) {
				xhr.setRequestHeader('X-WP-Nonce', ElodinRecentlyEdited.restNonce);
			},
		});
	}

	function openMetaInspector($trigger) {
		var postId = parseInt($trigger.attr('data-post-id'), 10);
		if (!postId) {
			return;
		}
		var postTitle = $trigger.attr('data-post-title') || 'Post #' + postId;
		var $menu = $('#wp-admin-bar-recently-edited');
		metaInspectorTrigger = $trigger;
		$menu.addClass('elodin-recently-edited-meta-view');
		var $panel = buildMetaInspectorShell(postId, postTitle);
		$panel.find('.elodin-recently-edited-meta-summary').text(
			(ElodinRecentlyEdited.strings || {}).loadingMeta || 'Loading post meta...',
		);
		$panel.find('.elodin-recently-edited-meta-back').focus();

		if (metaInspectorCache[postId]) {
			renderMetaInspector(metaInspectorCache[postId]);
			return;
		}
		if (metaInspectorRequest && typeof metaInspectorRequest.abort === 'function') {
			metaInspectorRequest.abort();
		}
		metaInspectorRequest = requestPostMeta(postId)
			.done(function (response) {
				metaInspectorCache[postId] = response;
				renderMetaInspector(response);
			})
			.fail(function (xhr, status) {
				if (status === 'abort') {
					return;
				}
				$panel.find('.elodin-recently-edited-meta-summary').text(
					(ElodinRecentlyEdited.strings || {}).unableToLoadMeta || 'Unable to load post meta.',
				);
			})
			.always(function () {
				metaInspectorRequest = null;
			});
	}

	function closeMetaInspector(restoreFocus) {
		var $menu = $('#wp-admin-bar-recently-edited');
		if (!$menu.hasClass('elodin-recently-edited-meta-view')) {
			return false;
		}
		if (metaInspectorRequest && typeof metaInspectorRequest.abort === 'function') {
			metaInspectorRequest.abort();
			metaInspectorRequest = null;
		}
		$menu.removeClass('elodin-recently-edited-meta-view');
		getMetaInspector().remove();
		if (restoreFocus && metaInspectorTrigger && metaInspectorTrigger.length) {
			metaInspectorTrigger.focus();
		}
		metaInspectorTrigger = null;
		return true;
	}

	function loadFullMetaKey($button) {
		var $panel = getMetaInspector();
		var postId = parseInt($panel.attr('data-post-id'), 10);
		var key = String($button.attr('data-meta-key') || '');
		if (!postId || !key || $button.prop('disabled')) {
			return;
		}
		$button.prop('disabled', true).text('Loading...');
		requestPostMeta(postId, key)
			.done(function (response) {
				if (!response || !Array.isArray(response.items) || !response.items.length) {
					return;
				}
				var cached = metaInspectorCache[postId] || { post: response.post, items: [] };
				cached.items = (cached.items || []).map(function (item) {
					return item.key === key ? response.items[0] : item;
				});
				metaInspectorCache[postId] = cached;
				metaInspectorFullKeys[postId] = metaInspectorFullKeys[postId] || {};
				metaInspectorFullKeys[postId][key] = true;
				var query = $panel.find('.elodin-recently-edited-meta-search').val();
				renderMetaInspector(cached);
				$panel.find('.elodin-recently-edited-meta-search').val(query);
				filterMetaInspector(query);
			})
			.fail(function () {
				$button.prop('disabled', false).text('Try again');
			});
	}

	function rebuildRecentlyEditedCache($link) {
		var originalText = $link.text();

		$link.text('Rebuilding...').attr('aria-disabled', 'true');

		$.ajax({
			url: ElodinRecentlyEdited.ajaxUrl,
			method: 'POST',
			data: {
				action: 'elodin_recently_edited_flush_menu_cache',
				nonce: ElodinRecentlyEdited.nonceCache,
			},
		})
			.done(function (response) {
				if (response && response.success && response.data) {
					clearClientMenuCache();
					if (response.data.cacheSchema) {
						ElodinRecentlyEdited.cacheSchema = response.data.cacheSchema;
					}
					loadRecentlyEditedMenu({ preload: true, force: true });
					$link.text(response.data.message || 'Cache rebuilt.');
					return;
				}

				$link.text('Unable to rebuild.');
			})
			.fail(function () {
				$link.text('Unable to rebuild.');
			})
			.always(function () {
				window.setTimeout(function () {
					$link.text(originalText).removeAttr('aria-disabled');
				}, 1800);
			});
	}

	function filterMenuItems($menu, query, options) {
		options = options || {};
		var normalized = normalizeSearchText(query);
		var matchCount = 0;
		var activeGroup = getActiveGroup($menu);
		updateSearchClearButton($menu);

		if (activeGroup === 'media') {
			getRowIndex().forEach(function (indexedRow) {
				indexedRow.item.style.display = 'none';
			});
			$menu.find('.elodin-recently-edited-no-matches').hide();
			filterMediaItems(query);
			return;
		}

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

		if (!options.skipSelection) {
			selectFirstVisibleRow();
		}
		updateSectionLabels();
	}

	function getOpenMenu() {
		return $('#wp-admin-bar-recently-edited')
			.filter(function () {
				var $menu = $(this);
				if ($menu.hasClass('elodin-recently-edited-force-closed')) {
					return false;
				}

				return (
					$menu.hasClass('hover') ||
					$menu.hasClass('elodin-recently-edited-grace-open') ||
					$menu.attr('aria-expanded') === 'true' ||
					$menu.find(':focus').length > 0 ||
					$menu.children('.ab-sub-wrapper').is(':visible')
				);
			})
			.first();
	}

	function getVisibleIndexedRows() {
		return getRowIndex().filter(function (indexedRow) {
			return (
				indexedRow.item.classList.contains('is-active') &&
				indexedRow.item.style.display !== 'none'
			);
		});
	}

	function sortRowsByPinnedState() {
		var $list = $('#wp-admin-bar-recently-edited .elodin-recently-edited-post-list').first();
		if (!$list.length) {
			return;
		}

		var rows = $list.children('.elodin-recently-edited-list-item').get();
		rows.sort(function (a, b) {
			var $a = $(a);
			var $b = $(b);
			var aPinned = $a.find('.elodin-recently-edited-pin.is-pinned').length > 0;
			var bPinned = $b.find('.elodin-recently-edited-pin.is-pinned').length > 0;

			if (aPinned !== bPinned) {
				return aPinned ? -1 : 1;
			}

			var aModified = parseInt($a.find('.elodin-recently-edited-row').attr('data-modified') || 0, 10);
			var bModified = parseInt($b.find('.elodin-recently-edited-row').attr('data-modified') || 0, 10);
			if (aModified !== bModified) {
				return aModified > bModified ? -1 : 1;
			}

			return 0;
		});

		$list.append(rows);
		invalidateRowIndex();
		updateSectionLabels();
	}

	function updateSectionLabels() {
		var $menu = $('#wp-admin-bar-recently-edited');
		var $items = $menu.find('.elodin-recently-edited-list-item');
		$items
			.removeClass('is-starred is-first-starred is-first-recent')
			.removeAttr('data-section-label');
		$items.each(function () {
			$(this).toggleClass(
				'is-starred',
				$(this).find('.elodin-recently-edited-pin.is-pinned').length > 0,
			);
		});
		if (getActiveGroup($menu) === 'media') {
			return;
		}

		var visibleRows = getVisibleIndexedRows();
		var firstStarred = visibleRows.find(function (indexedRow) {
			return $(indexedRow.row).find('.elodin-recently-edited-pin.is-pinned').length > 0;
		});
		var firstRecent = visibleRows.find(function (indexedRow) {
			return $(indexedRow.row).find('.elodin-recently-edited-pin.is-pinned').length === 0;
		});

		if (firstStarred) {
			$(firstStarred.item)
				.addClass('is-first-starred')
				.attr('data-section-label', (ElodinRecentlyEdited.strings || {}).starred || 'Starred');
		}
		if (firstStarred && firstRecent) {
			$(firstRecent.item)
				.addClass('is-first-recent')
				.attr('data-section-label', (ElodinRecentlyEdited.strings || {}).recentlyEdited || 'Recently edited');
		}
	}

	function setSelectedIndexedRow(indexedRow) {
		var $menu = $('#wp-admin-bar-recently-edited');
		$menu
			.find('.elodin-recently-edited-list-item.is-keyboard-selected')
			.removeClass('is-keyboard-selected');

		if (!indexedRow || !indexedRow.item) {
			sessionStorage.removeItem(selectionStorageKey($menu.attr('id')));
			return;
		}

		indexedRow.item.classList.add('is-keyboard-selected');
		storeRowSelection($menu.attr('id'), $(indexedRow.row));
		if ($menu.hasClass('hover')) {
			sessionStorage.setItem(storageKey($menu.attr('id')), 'true');
		}
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

	function getCurrentVisibleRow() {
		var currentPostId = parseInt(ElodinRecentlyEdited.currentPostId || 0, 10);

		return getVisibleIndexedRows().find(function (indexedRow) {
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
	}

	function getRowSelectionData($row) {
		var $resource = $row.find('[data-resource-type][data-resource-id]').first();
		if ($resource.length) {
			var visibleIndex = getVisibleIndexedRows().findIndex(function (indexedRow) {
				return indexedRow.row === $row[0];
			});
			var $viewTarget = $row.find('.elodin-recently-edited-title-link').first();
			var $editTarget = $row.find('.elodin-recently-edited-edit').first();

			return {
				editUrl: String($editTarget.data('url') || ''),
				group: $row.attr('data-post-type') || $row.attr('data-related-group') || 'all',
				resourceId: String($resource.data('resourceId') || ''),
				resourceType: String($resource.data('resourceType') || ''),
				searchText: String($row.attr('data-search-text') || ''),
				viewUrl: String($viewTarget.data('url') || ''),
				visibleIndex: visibleIndex,
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

	function normalizeUrlForSelection(url) {
		if (!url) {
			return '';
		}

		var anchor = document.createElement('a');
		anchor.href = url;

		return anchor.origin + anchor.pathname.replace(/\/$/, '') + anchor.search;
	}

	function getSelectedRowSelection(menuId) {
		var $menu = $('#' + menuId);
		var $selectedRow = $menu
			.find('.elodin-recently-edited-list-item.is-keyboard-selected .elodin-recently-edited-row')
			.first();

		return $selectedRow.length ? getRowSelectionData($selectedRow) : null;
	}

	function persistOpenMenuState(menuId, selectionOverride, targetUrl) {
		var $menu = $('#' + menuId);
		if (!$menu.length) {
			return;
		}

		saveActiveGroup(menuId);
		saveScrollPosition(menuId);

		var selection = selectionOverride || getSelectedRowSelection(menuId);
		if (selection) {
			sessionStorage.setItem(selectionStorageKey(menuId), JSON.stringify(selection));
		}

		var searchQuery = $menu
			.find('.elodin-recently-edited-search-input')
			.first()
			.val();
		if (searchQuery) {
			sessionStorage.setItem(searchStorageKey(menuId), searchQuery);
		} else {
			sessionStorage.removeItem(searchStorageKey(menuId));
		}

		if (targetUrl) {
			sessionStorage.setItem(targetUrlStorageKey(menuId), targetUrl);
		} else {
			sessionStorage.removeItem(targetUrlStorageKey(menuId));
		}

		sessionStorage.setItem(
			stateStorageKey(menuId),
			JSON.stringify({
				group: getActiveGroup($menu),
				search: searchQuery || '',
				selection: selection,
				scrollTop: getSubmenu($menu).length ? getSubmenu($menu).scrollTop() : 0,
				targetUrl: targetUrl || '',
			}),
		);
	}

	function selectStoredVisibleRowOrCurrentOrFirst() {
		var $menu = $('#wp-admin-bar-recently-edited');
		var storedSelection = $menu.data('restoreSelection');
		var storedTargetUrl = $menu.data('restoreTargetUrl');
		var selectedRow = getCurrentVisibleRow() || null;

		if (!selectedRow && !storedSelection) {
			selectedRow = getVisibleIndexedRows().find(function (indexedRow) {
				return indexedRow.item.classList.contains('is-keyboard-selected');
			});
			if (selectedRow) {
				return;
			}
		}

		if (!selectedRow && storedSelection && storedSelection.resourceId && storedSelection.resourceType) {
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

		if (!selectedRow && storedTargetUrl) {
			var normalizedTargetUrl = normalizeUrlForSelection(storedTargetUrl);
			selectedRow = getVisibleIndexedRows().find(function (indexedRow) {
				var $row = $(indexedRow.row);
				var viewUrl = normalizeUrlForSelection(
					String($row.find('.elodin-recently-edited-title-link').first().data('url') || ''),
				);
				var editUrl = normalizeUrlForSelection(
					String($row.find('.elodin-recently-edited-edit').first().data('url') || ''),
				);

				return normalizedTargetUrl && (viewUrl === normalizedTargetUrl || editUrl === normalizedTargetUrl);
			});
		}

		if (!selectedRow && storedSelection && (storedSelection.viewUrl || storedSelection.editUrl)) {
			selectedRow = getVisibleIndexedRows().find(function (indexedRow) {
				var $row = $(indexedRow.row);
				var viewUrl = normalizeUrlForSelection(
					String($row.find('.elodin-recently-edited-title-link').first().data('url') || ''),
				);
				var editUrl = normalizeUrlForSelection(
					String($row.find('.elodin-recently-edited-edit').first().data('url') || ''),
				);
				var storedViewUrl = normalizeUrlForSelection(storedSelection.viewUrl);
				var storedEditUrl = normalizeUrlForSelection(storedSelection.editUrl);

				return (
					(storedViewUrl && viewUrl === storedViewUrl) ||
					(storedEditUrl && editUrl === storedEditUrl)
				);
			});
		}

		if (!selectedRow && storedSelection && storedSelection.searchText) {
			selectedRow = getVisibleIndexedRows().find(function (indexedRow) {
				return String($(indexedRow.row).attr('data-search-text') || '') === storedSelection.searchText;
			});
		}

		if (!selectedRow && storedSelection && storedSelection.visibleIndex >= 0) {
			selectedRow = getVisibleIndexedRows()[storedSelection.visibleIndex];
		}

		if (selectedRow) {
			setSelectedIndexedRow(selectedRow);
			$menu.removeData('restoreSelection');
			$menu.removeData('restoreTargetUrl');
			return;
		}

		$menu.removeData('restoreSelection');
		$menu.removeData('restoreTargetUrl');
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
		setSelectedIndexedRow(selected);
		var selection = getRowSelectionData($row);
		storeRowSelection('wp-admin-bar-recently-edited', $row);
		persistOpenMenuState('wp-admin-bar-recently-edited', selection);
		sessionStorage.setItem(storageKey('wp-admin-bar-recently-edited'), 'true');
		var $target = useEditUrl
			? $row.find('.elodin-recently-edited-edit').first()
			: $row.find('.elodin-recently-edited-title-link').first();
		if (!$target.length) {
			return;
		}

		persistOpenMenuState(
			'wp-admin-bar-recently-edited',
			selection,
			String($target.data('url') || ''),
		);
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

	function focusRecentlyEditedSearch(selectExistingQuery) {
		var $menu = $('#wp-admin-bar-recently-edited');
		if (!$menu.length) {
			return;
		}
		var wasOpen = typeof selectExistingQuery === 'boolean'
			? selectExistingQuery
			: $menu.hasClass('hover');

		releaseForceClosedMenu($menu);
		cancelClose($menu.attr('id'));
		$menu
			.removeClass('elodin-recently-edited-force-closed')
			.addClass('hover elodin-recently-edited-grace-open');

		if ($menu.hasClass('elodin-recently-edited-is-lazy')) {
			var request = loadRecentlyEditedMenu();
			if (request) {
				request.done(function () {
					focusRecentlyEditedSearch(wasOpen);
				});
			}
			return;
		}

		var $input = $menu.find('.elodin-recently-edited-search-input').first();
		if (!$input.length) {
			return;
		}

		switchRelatedGroup($menu, 'all');
		$input.focus();
		if (wasOpen) {
			$input.select();
		} else if ($input[0] && typeof $input[0].setSelectionRange === 'function') {
			var queryLength = String($input.val() || '').length;
			$input[0].setSelectionRange(queryLength, queryLength);
		}
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

		var $openMenu = getOpenMenu();
		if ($openMenu.length) {
			var menuId = $openMenu.attr('id');
			persistOpenMenuState(menuId, null, url);
			sessionStorage.setItem(storageKey(menuId), 'true');
		}

		window.location.href = url;
		return true;
	}

	function handleMenuNavigationKeydown(e) {
		var $menu = getOpenMenu();
		if (!$menu.length || $menu.hasClass('elodin-recently-edited-is-lazy')) {
			return false;
		}
		if ($menu.hasClass('elodin-recently-edited-meta-view')) {
			return false;
		}

		if (
			e.shiftKey &&
			!e.metaKey &&
			!e.ctrlKey &&
			!e.altKey &&
			String(e.key || '').toLowerCase() === 's'
		) {
			toggleSelectedRowPin();
			return true;
		}

		if (e.key === 'Escape') {
			closeRecentlyEditedImmediately();
			return true;
		}

		if (e.key === 'Backspace') {
			if ($(e.target).is('.elodin-recently-edited-search-input')) {
				return false;
			}
			clearSearch($menu, true);
			return true;
		}

		if (getActiveGroup($menu) === 'media') {
			var hasSelectedMedia = getVisibleMediaCards().filter('.is-keyboard-selected').length > 0;
			var mediaColumnCount = getMediaColumnCount();
			if (hasSelectedMedia && e.key === 'ArrowRight') {
				selectRelativeMediaCard(1);
				return true;
			}
			if (hasSelectedMedia && e.key === 'ArrowLeft') {
				selectRelativeMediaCard(-1);
				return true;
			}
			if (e.key === 'ArrowDown') {
				selectRelativeMediaCard(hasSelectedMedia ? mediaColumnCount : 0);
				return true;
			}
			if (hasSelectedMedia && e.key === 'ArrowUp') {
				selectRelativeMediaCard(-mediaColumnCount);
				return true;
			}
			if (e.key === 'Enter') {
				activateSelectedMediaCard(isMac ? e.metaKey : e.ctrlKey);
				return true;
			}
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

	function toggleSelectedRowPin() {
		var selected = getVisibleIndexedRows().find(function (indexedRow) {
			return indexedRow.item.classList.contains('is-keyboard-selected');
		});
		if (!selected) {
			selected = getVisibleIndexedRows()[0];
		}
		if (!selected || !selected.row) {
			return;
		}

		$(selected.row).find('.elodin-recently-edited-pin').first().trigger('click');
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
		reconcileReviewControls($menu);
		$menu.toggleClass('elodin-recently-edited-media-view', target === 'media');
		if (target === 'media') {
			setSelectedMediaCard(null);
			loadMediaItems();
		}

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
		updateSectionLabels();
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
			var message = feedbackText || (ElodinRecentlyEdited.strings || {}).copied || 'Copied';
			$element.text(message);
			announce(message);
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
					announce('Slug updated');
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
					announce('Title updated');
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
		if (forceClosedMenus[menuId]) {
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
		sessionStorage.removeItem(searchStorageKey(menuId));
		sessionStorage.removeItem(stateStorageKey(menuId));
		sessionStorage.removeItem(targetUrlStorageKey(menuId));
		$('#' + menuId).removeClass('hover elodin-recently-edited-grace-open');
	}

	function forceCloseMenu($menu) {
		var menuId = $menu.attr('id');
		if (!menuId) {
			return;
		}

		forceClosedMenus[menuId] = true;
		$menu
			.addClass('elodin-recently-edited-force-closed')
			.removeClass('hover elodin-recently-edited-grace-open')
			.attr('aria-expanded', 'false');
		$menu
			.children('.ab-sub-wrapper')
			.each(function () {
				this.style.setProperty('display', 'none', 'important');
			})
			.attr('aria-hidden', 'true');
	}

	function releaseForceClosedMenu($menu) {
		var menuId = $menu.attr('id');
		if (!menuId) {
			return;
		}

		forceClosedMenus[menuId] = false;
		$menu
			.removeClass('elodin-recently-edited-force-closed')
			.removeAttr('aria-expanded')
			.children('.ab-sub-wrapper')
			.each(function () {
				this.style.removeProperty('display');
			})
			.removeAttr('aria-hidden');
	}

	function closeRecentlyEditedImmediately() {
		var closed = false;

		menuIds.forEach(function (menuId) {
			var $menu = $('#' + menuId);
			if (!$menu.length) {
				return;
			}

			closeMetaInspector(false);
			clearKeepOpenState(menuId);
			forceCloseMenu($menu);
			$menu.find(':focus').trigger('blur');
			closed = true;
		});

		return closed;
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
				var storedState = null;
				try {
					storedState = JSON.parse(sessionStorage.getItem(stateStorageKey(menuId)) || 'null');
				} catch (error) {
					storedState = null;
				}
				var storedGroup = storedState && storedState.group ? storedState.group : sessionStorage.getItem(groupStorageKey(menuId));
				var storedSelection = storedState && storedState.selection ? storedState.selection : sessionStorage.getItem(selectionStorageKey(menuId));
				var storedSearch = storedState && typeof storedState.search === 'string' ? storedState.search : sessionStorage.getItem(searchStorageKey(menuId));
				var storedTargetUrl = storedState && storedState.targetUrl ? storedState.targetUrl : sessionStorage.getItem(targetUrlStorageKey(menuId));
				var $menu = $('#' + menuId);
				if (storedGroup) {
					$menu.data('restoreGroup', storedGroup);
				}
				if (typeof storedSearch === 'string' && storedSearch !== '') {
					$menu.data('restoreSearch', storedSearch);
				}
				if (storedSelection) {
					if (typeof storedSelection === 'string') {
						try {
							$menu.data('restoreSelection', JSON.parse(storedSelection));
						} catch (error) {
							$menu.removeData('restoreSelection');
						}
					} else {
						$menu.data('restoreSelection', storedSelection);
					}
				}
				if (storedTargetUrl) {
					$menu.data('restoreTargetUrl', storedTargetUrl);
				}
				// Clear the flag
				sessionStorage.removeItem(storageKey(menuId));
				sessionStorage.removeItem(groupStorageKey(menuId));
				sessionStorage.removeItem(selectionStorageKey(menuId));
				sessionStorage.removeItem(searchStorageKey(menuId));
				sessionStorage.removeItem(stateStorageKey(menuId));
				sessionStorage.removeItem(targetUrlStorageKey(menuId));

				// Add hover class to keep menu open
				$menu.addClass('hover');
				window.setTimeout(function () {
					if (storedGroup) {
						switchRelatedGroup($menu, storedGroup);
					}
					if (typeof storedSearch === 'string' && storedSearch !== '') {
						$menu.find('.elodin-recently-edited-search-input').first().val(storedSearch);
						filterMenuItems($menu, storedSearch, { skipSelection: true });
					}
					if (storedState && typeof storedState.scrollTop === 'number') {
						getSubmenu($menu).scrollTop(storedState.scrollTop);
					} else {
						restoreScrollPosition(menuId);
					}
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
		var $row = $(this).closest('.elodin-recently-edited-row');
		var selection = getRowSelectionData($row);
		storeRowSelection(menuId, $row);
		persistOpenMenuState(menuId, selection, String(url || ''));
		sessionStorage.setItem(storageKey(menuId), 'true');

		window.location.href = url;
	});

	$(document).on(
		'keydown',
		'.elodin-recently-edited-action, .elodin-recently-edited-pin, .elodin-recently-edited-slug-text, .elodin-recently-edited-id',
		function (e) {
			if (e.key !== 'Enter' && e.key !== ' ') {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			e.stopImmediatePropagation();
			$(this).trigger('click');
		},
	);

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
			if ($(this).hasClass('elodin-recently-edited-force-closed')) {
				releaseForceClosedMenu($(this));
				return;
			}
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
			if (forceClosedMenus[menuId]) {
				return;
			}
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

	$(document).on(
		'click',
		'#wp-admin-bar-recently-edited .elodin-recently-edited-cache-refresh',
		function (e) {
			e.preventDefault();
			e.stopPropagation();

			var $link = $(this);
			if ($link.attr('aria-disabled') === 'true') {
				return;
			}

			rebuildRecentlyEditedCache($link);
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
			filterMenuItems($('#' + menuId), $input.val());
		},
	);

	$(document).on('click', '.elodin-recently-edited-search-clear', function (e) {
		e.preventDefault();
		e.stopPropagation();
		clearSearch($('#wp-admin-bar-recently-edited'), true);
	});

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
				e.stopImmediatePropagation();
			}
		},
	);

	document.addEventListener(
		'keydown',
		function (e) {
			if (e.key !== 'Escape') {
				return;
			}

			if (closeMetaInspector(true)) {
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();
				return;
			}

			if (!closeRecentlyEditedImmediately()) {
				return;
			}

			e.preventDefault();
			e.stopPropagation();
		},
		true,
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
	setPageScrollbarCompensation();
	updateSearchPlaceholder();
	checkAndRestoreMenuState();
	if (!hydrateRecentlyEditedMenuFromCache()) {
		scheduleMenuIndexBuild();
	}
	watchBlockEditorSaves();

	$(window).on('resize orientationchange', setPageScrollbarCompensation);

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
						var $selectedRow = $pin.closest('.elodin-recently-edited-row');
						var selectedResource = getRowSelectionData($selectedRow);
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
						sortRowsByPinnedState();
						if (selectedResource) {
							var selectedRow = getVisibleIndexedRows().find(function (indexedRow) {
								var rowResource = getRowSelectionData($(indexedRow.row));
								return (
									rowResource &&
									rowResource.resourceType === selectedResource.resourceType &&
									rowResource.resourceId === selectedResource.resourceId
								);
							});
							if (selectedRow) {
								setSelectedIndexedRow(selectedRow);
							}
						}
						clearClientMenuCache();
						announce(isPinned ? 'Removed from Starred' : 'Added to Starred');
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

	function getReviewStates() {
		return Array.isArray(ElodinRecentlyEdited.reviewStates)
			? ElodinRecentlyEdited.reviewStates.filter(function (state) {
				return state && state.key && state.label && state.color;
			})
			: [];
	}

	function getReviewStateConfig(stateKey) {
		return getReviewStates().find(function (state) {
			return state.key === stateKey;
		}) || null;
	}

	function getReviewPostTypes() {
		return Array.isArray(ElodinRecentlyEdited.reviewPostTypes)
			? ElodinRecentlyEdited.reviewPostTypes.map(String)
			: [];
	}

	function reconcileReviewControls($menu) {
		$menu = $menu && $menu.length ? $menu : $('#wp-admin-bar-recently-edited');
		var enabledTypes = getReviewPostTypes();
		var savedStates = ElodinRecentlyEdited.reviewPostStates || {};
		$menu.find('.elodin-recently-edited-row').each(function () {
			var $row = $(this);
			var postType = String($row.attr('data-post-type') || '');
			var $markers = $row.children('.elodin-recently-edited-row-markers').first();
			var $button = $markers.find('.elodin-recently-edited-review-status').first();
			var postId = parseInt(
				$row.find('.elodin-recently-edited-title-link[data-post-id]').first().attr('data-post-id') ||
				$row.find('.elodin-recently-edited-pin[data-post-id]').first().attr('data-post-id'),
				10,
			);
			var enabled = enabledTypes.indexOf(postType) !== -1 && postId > 0;
			$row.attr('data-review-enabled', enabled ? '1' : '0');
			if (!enabled) {
				$button.remove();
				return;
			}
			if (!$button.length) {
				$button = $('<button>', {
					type: 'button',
					class: 'elodin-recently-edited-review-status is-blank',
					'data-post-id': postId,
				}).append($('<span>', {
					class: 'elodin-recently-edited-review-dot',
					'aria-hidden': 'true',
				})).appendTo($markers);
				updateReviewStatusAppearance($button, String(savedStates[postId] || ''));
			}
		});
	}

	function updateReviewStatusAppearance($button, state) {
		var config = getReviewStateConfig(state);
		var label = config ? config.label : 'Blank';
		$button
			.toggleClass('is-blank', !config)
			.css('--elodin-review-color', config ? config.color : '')
			.attr('data-review-state', config ? state : '')
			.attr('aria-label', 'Review: ' + label + '. Click to cycle.')
			.attr('title', 'Review: ' + label + '. Click to cycle.');
	}

	function saveReviewStatus($button, postId, state, persistedState) {
		var activeRequest = $button.data('reviewSaveRequest');
		if (activeRequest && typeof activeRequest.abort === 'function') {
			activeRequest.abort();
		}

		var request = $.post(ElodinRecentlyEdited.ajaxUrl, {
			action: 'elodin_recently_edited_update_review_status',
			post_id: postId,
			state: state,
			nonce: ElodinRecentlyEdited.nonceReview,
		});
		$button.data('reviewSaveRequest', request);
		request
			.done(function (response) {
				if (!response || !response.success) {
					updateReviewStatusAppearance($button, persistedState);
					announce((ElodinRecentlyEdited.strings || {}).reviewSaveFailed || 'Unable to save review status.');
					return;
				}
				$button.data('persistedReviewState', state);
				if (!ElodinRecentlyEdited.reviewPostStates || typeof ElodinRecentlyEdited.reviewPostStates !== 'object') {
					ElodinRecentlyEdited.reviewPostStates = {};
				}
				if (state) {
					ElodinRecentlyEdited.reviewPostStates[postId] = state;
				} else {
					delete ElodinRecentlyEdited.reviewPostStates[postId];
				}
				try {
					window.localStorage.removeItem(getClientCacheKey());
				} catch (error) {
					// Browser storage may be unavailable.
				}
				var config = getReviewStateConfig(state);
				announce('Review: ' + (config ? config.label : 'Blank'));
			})
			.fail(function (xhr, status) {
				if (status === 'abort') {
					return;
				}
				updateReviewStatusAppearance($button, persistedState);
				announce((ElodinRecentlyEdited.strings || {}).reviewSaveFailed || 'Unable to save review status.');
			})
			.always(function () {
				if ($button.data('reviewSaveRequest') === request) {
					$button.removeData('reviewSaveRequest');
				}
			});
	}

	$(document).on(
		'click',
		'#wp-admin-bar-recently-edited .elodin-recently-edited-review-status',
		function (e) {
			e.preventDefault();
			e.stopPropagation();
			e.stopImmediatePropagation();
			var $button = $(this);
			var postId = parseInt($button.attr('data-post-id'), 10);
			var states = getReviewStates();
			if (!postId || !states.length) {
				return;
			}
			var current = String($button.attr('data-review-state') || '');
			var currentIndex = states.findIndex(function (state) { return state.key === current; });
			var nextState = currentIndex < 0
				? states[0].key
				: currentIndex >= states.length - 1
					? ''
					: states[currentIndex + 1].key;
			var persistedState = $button.data('persistedReviewState');
			if (typeof persistedState !== 'string') {
				persistedState = current;
				$button.data('persistedReviewState', persistedState);
			}
			updateReviewStatusAppearance($button, nextState);

			var saveTimer = $button.data('reviewSaveTimer');
			if (saveTimer) {
				window.clearTimeout(saveTimer);
			}
			$button.data(
				'reviewSaveTimer',
				window.setTimeout(function () {
					$button.removeData('reviewSaveTimer');
					saveReviewStatus($button, postId, String($button.attr('data-review-state') || ''), persistedState);
				}, 220),
			);
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
					'.elodin-recently-edited-title-link, .elodin-recently-edited-title-input, .elodin-recently-edited-meta-trigger',
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

	$(document).on('click', '.elodin-recently-edited-media-filename', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var $button = $(this);
		copyTextWithFeedback(
			$button,
			$button.attr('data-copy-text') || '',
			(ElodinRecentlyEdited.strings || {}).copiedFilename || 'Copied filename',
		);
	});

	$(document).on('click', '.elodin-recently-edited-media-copy-url', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var $button = $(this);
		copyTextWithFeedback(
			$button,
			$button.attr('data-copy-text') || '',
			(ElodinRecentlyEdited.strings || {}).copiedUrl || 'Copied URL',
		);
	});

	$(document).on('click', '.elodin-recently-edited-meta-trigger', function (e) {
		e.preventDefault();
		e.stopPropagation();
		e.stopImmediatePropagation();
		openMetaInspector($(this));
	});

	$(document).on('click', '.elodin-recently-edited-meta-back', function (e) {
		e.preventDefault();
		e.stopPropagation();
		closeMetaInspector(true);
	});

	$(document).on('input', '.elodin-recently-edited-meta-search', function (e) {
		e.stopPropagation();
		filterMetaInspector($(this).val());
	});

	$(document).on('click', '.elodin-recently-edited-meta-copy-key', function (e) {
		e.preventDefault();
		e.stopPropagation();
		copyTextWithFeedback(
			$(this),
			$(this).attr('data-copy-text') || '',
			(ElodinRecentlyEdited.strings || {}).copiedMetaKey || 'Copied meta key',
		);
	});

	$(document).on('click', '.elodin-recently-edited-meta-copy-value', function (e) {
		e.preventDefault();
		e.stopPropagation();
		copyTextWithFeedback(
			$(this),
			$(this).attr('data-copy-text') || '',
			(ElodinRecentlyEdited.strings || {}).copiedMetaValue || 'Copied meta value',
		);
	});

	$(document).on('click', '.elodin-recently-edited-meta-load-full', function (e) {
		e.preventDefault();
		e.stopPropagation();
		loadFullMetaKey($(this));
	});

	$(document).on('click', '.elodin-recently-edited-media-preview, .elodin-recently-edited-media-edit', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var url = $(this).attr('data-url');
		if (url) {
			window.open(url, '_blank', 'noopener');
		}
	});

	$(document).on('click focusin', '.elodin-recently-edited-media-card', function () {
		setSelectedMediaCard($(this));
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
				var postTitle = $select.attr('data-post-title') || 'this item';
				var confirmTemplate = (ElodinRecentlyEdited.strings || {}).moveToTrashConfirm || 'Move "%s" to the Trash?';
				if (!confirm(confirmTemplate.replace('%s', postTitle))) {
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
						updateSectionLabels();
						announce(status === 'delete' ? 'Moved to Trash' : 'Status updated');
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
						announce('Form status updated');
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
						announce('Content type updated');
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

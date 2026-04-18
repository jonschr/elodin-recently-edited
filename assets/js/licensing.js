(function ($) {
	'use strict';

	if (typeof ElodinRecentlyEditedLicense === 'undefined') {
		return;
	}

	var settings = ElodinRecentlyEditedLicense;
	var state = settings.initialState || {};
	var busy = false;
	var inputDirty = false;

	var $page = $('#elodin-recently-edited-license-page');
	var $input = $('#elodin_recently_edited_license_key');
	var $spinner = $('#elodin-recently-edited-license-spinner');
	var $activity = $('#elodin-recently-edited-license-activity');
	var $noticeRoot = $('#elodin-recently-edited-license-notices');
	var $status = $('#elodin-recently-edited-license-status');
	var $statusLabel = $('#elodin-recently-edited-license-status-label');
	var $statusDescription = $('#elodin-recently-edited-license-status-description');
	var $instanceName = $('#elodin-recently-edited-license-instance-name');
	var $customerName = $('#elodin-recently-edited-license-customer-name');
	var $customerEmail = $('#elodin-recently-edited-license-customer-email');
	var $refreshButton = $('#elodin-recently-edited-license-refresh');
	var $deactivateButton = $('#elodin-recently-edited-license-deactivate');

	function setBusy(isBusy, message) {
		busy = isBusy;
		$page.find('button').prop('disabled', isBusy);
		$input.prop('disabled', isBusy);
		$spinner.toggleClass('is-active', isBusy);

		if (message) {
			$activity.text(message).show();
		} else {
			$activity.text('').hide();
		}
	}

	function renderNotice(message, type) {
		if (!message) {
			$noticeRoot.empty();
			return;
		}

		var noticeClass = type === 'error' ? 'error' : 'success';
		$noticeRoot.html(
			'<div class="notice notice-' + noticeClass + ' is-dismissible"><p></p></div>'
		);
		$noticeRoot.find('p').text(message);
	}

	function toggleDescription($element, value) {
		if (value) {
			$element.text(value).show();
			return;
		}

		$element.text('').hide();
	}

	function applyState(nextState) {
		state = nextState || {};
		inputDirty = false;

		$input.val(state.licenseKey || '');
		$input.attr('data-current-license-key', state.licenseKey || '');
		$status.removeClass('notice-success notice-warning').addClass('notice-' + (state.statusClass || 'warning'));
		$statusLabel.text(state.statusLabel || '');
		toggleDescription($statusDescription, state.statusDescription || '');
		$instanceName.text(state.instanceName || '');
		$customerName.text(state.customerName || '');
		toggleDescription($customerEmail, state.customerEmail || '');
		$refreshButton.prop('disabled', busy || !state.canRefresh);
		$deactivateButton.prop('disabled', busy || !state.canDeactivate);
	}

	function handleResponse(response) {
		var payload = response && response.data ? response.data : {};

		if (payload.state) {
			applyState(payload.state);
		}

		if (response && response.success) {
			renderNotice('', 'success');
			toggleDescription($statusDescription, payload.message || '');
		} else {
			renderNotice(payload.message || '', 'error');
		}

		setBusy(false, '');
	}

	function request(action, extraData, busyMessage) {
		if (busy) {
			return;
		}

		setBusy(true, busyMessage);

		$.post(
			settings.ajaxUrl,
			$.extend(
				{
					action: action,
					nonce: settings.nonce
				},
				extraData || {}
			)
		)
				.done(handleResponse)
			.fail(function () {
				setBusy(false, '');
				renderNotice(settings.strings.requestFailed, 'error');
			});
	}

	function maybeActivateCurrentKey() {
		var licenseKey = $.trim($input.val());
		var currentKey = $.trim($input.attr('data-current-license-key') || '');

		if (!licenseKey) {
			renderNotice(settings.strings.empty, 'error');
			return;
		}

		if (!inputDirty && licenseKey === currentKey) {
			return;
		}

		request(
			'elodin_recently_edited_license_activate',
			{ license_key: licenseKey },
			settings.strings.checking
		);
	}

	$input.on('input', function () {
		inputDirty = $.trim($input.val()) !== $.trim($input.attr('data-current-license-key') || '');
	});

	$input.on('blur change', function () {
		maybeActivateCurrentKey();
	});

	$input.on('keydown', function (e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			maybeActivateCurrentKey();
		}
	});

	$input.on('paste', function () {
		window.setTimeout(function () {
			maybeActivateCurrentKey();
		}, 250);
	});

	$refreshButton.on('click', function () {
		request('elodin_recently_edited_license_refresh', {}, settings.strings.refreshing);
	});

	$deactivateButton.on('click', function () {
		request('elodin_recently_edited_license_deactivate', {}, settings.strings.deactivating);
	});

	applyState(state);
})(jQuery);

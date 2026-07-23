(function () {
	'use strict';

	var config = window.ElodinRecentlyEditedSettings || {};
	var strings = config.strings || {};
	var form = document.getElementById('elodin-recently-edited-settings-form');
	var editor = document.getElementById('elodin-recently-edited-review-states');
	var rows = editor ? editor.querySelector('.elodin-review-state-rows') : null;
	var status = document.getElementById('elodin-recently-edited-settings-status');
	var saveTimer = null;
	var saveRequest = null;
	var stateCounter = 0;

	if (!form || !editor || !rows) {
		return;
	}

	function setStatus(message, isError) {
		if (!status) {
			return;
		}
		status.textContent = message || '';
		status.style.color = isError ? '#b32d2e' : '';
	}

	function parseResponse(request) {
		try {
			return JSON.parse(request.responseText);
		} catch (error) {
			return null;
		}
	}

	function saveSettings() {
		var data = new window.FormData(form);
		data.set('action', 'elodin_recently_edited_save_settings');
		data.set('nonce', data.get('elodin_recently_edited_settings_nonce') || '');

		if (saveRequest && typeof saveRequest.abort === 'function') {
			saveRequest.abort();
		}

		setStatus(strings.saving || 'Saving...', false);
		saveRequest = new window.XMLHttpRequest();
		saveRequest.open('POST', config.ajaxUrl || window.ajaxurl, true);
		saveRequest.onload = function () {
			var response = parseResponse(saveRequest);
			if (saveRequest.status >= 200 && saveRequest.status < 300 && response && response.success) {
				setStatus(response.data && response.data.message ? response.data.message : (strings.saved || 'Saved.'), false);
				return;
			}
			setStatus(response && response.data && response.data.message ? response.data.message : (strings.saveFailed || 'Unable to save settings.'), true);
		};
		saveRequest.onerror = function () {
			setStatus(strings.saveFailed || 'Unable to save settings.', true);
		};
		saveRequest.send(data);
	}

	function scheduleSave(delay) {
		window.clearTimeout(saveTimer);
		saveTimer = window.setTimeout(saveSettings, delay || 250);
	}

	function dispatchInput(input) {
		input.dispatchEvent(new window.Event('input', { bubbles: true }));
	}

	function mountColorPicker(mount) {
		if (
			!mount ||
			mount.dataset.mounted === '1' ||
			!window.wp ||
			!wp.element ||
			!wp.components ||
			!wp.components.ColorPicker ||
			!wp.components.Dropdown
		) {
			return;
		}

		var input = document.getElementById(mount.dataset.colorInput || '');
		if (!input) {
			return;
		}

		var createElement = wp.element.createElement;
		var useState = wp.element.useState;
		var Button = wp.components.Button;
		var ColorPicker = wp.components.ColorPicker;
		var Dropdown = wp.components.Dropdown;

		function CompactColorPicker() {
			var colorState = useState(input.value || '#737c89');
			var color = colorState[0];
			var setColor = colorState[1];

			function changeColor(nextColor) {
				if (!nextColor) {
					return;
				}
				setColor(nextColor);
				input.value = nextColor;
				dispatchInput(input);
			}

			return createElement(Dropdown, {
				className: 'elodin-review-color-dropdown',
				contentClassName: 'elodin-review-color-popover',
				renderToggle: function (toggleProps) {
					return createElement(
						Button,
						{
							className: 'elodin-review-color-toggle',
							variant: 'secondary',
							onClick: toggleProps.onToggle,
							'aria-expanded': toggleProps.isOpen,
							'aria-label': strings.chooseColor || 'Choose color',
						},
						createElement('span', {
							className: 'elodin-review-color-swatch',
							style: { backgroundColor: color },
							'aria-hidden': 'true',
						}),
						createElement('code', null, color.toUpperCase())
					);
				},
				renderContent: function () {
					return createElement(ColorPicker, {
						color: color,
						onChange: changeColor,
						enableAlpha: false,
					});
				},
			});
		}

		if (typeof wp.element.createRoot === 'function') {
			wp.element.createRoot(mount).render(createElement(CompactColorPicker));
		} else {
			wp.element.render(createElement(CompactColorPicker), mount);
		}
		mount.dataset.mounted = '1';
		mount.closest('.elodin-review-state-color').classList.add('has-gutenberg-color-picker');
	}

	function mountColorPickers(scope) {
		(scope || document).querySelectorAll('.elodin-review-state-color-mount').forEach(mountColorPicker);
	}

	function createInput(type, className, name, value) {
		var input = document.createElement('input');
		input.type = type;
		input.className = className || '';
		input.name = name;
		input.value = value;
		return input;
	}

	function createStateRow() {
		if (rows.children.length >= 12) {
			setStatus(strings.maxStates || 'A maximum of 12 review states is supported.', true);
			return;
		}

		stateCounter += 1;
		var key = 'custom_' + Date.now().toString(36) + '_' + stateCounter;
		var optionPrefix = config.optionName + '[review_states][' + key + ']';
		var row = document.createElement('div');
		row.className = 'elodin-review-state-row is-custom';
		row.dataset.stateKey = key;
		row.dataset.builtin = '0';

		var enabledCell = document.createElement('div');
		enabledCell.className = 'elodin-review-state-enabled';
		var enabledHidden = createInput('hidden', '', optionPrefix + '[enabled]', '0');
		var enabled = createInput('checkbox', '', optionPrefix + '[enabled]', '1');
		enabled.checked = true;
		enabled.setAttribute('aria-label', 'Enable custom review state');
		enabledCell.append(enabledHidden, enabled);

		var nameCell = document.createElement('div');
		nameCell.className = 'elodin-review-state-name';
		var label = createInput('text', 'elodin-review-state-label', optionPrefix + '[label]', '');
		label.placeholder = strings.stateLabel || 'State label';
		label.setAttribute('aria-label', strings.stateLabel || 'State label');
		nameCell.appendChild(label);

		var colorCell = document.createElement('div');
		colorCell.className = 'elodin-review-state-color';
		var color = createInput('color', 'elodin-review-state-color-fallback', optionPrefix + '[color]', '#737c89');
		color.id = 'elodin-review-color-' + key;
		color.setAttribute('aria-label', strings.chooseColor || 'Choose color');
		var mount = document.createElement('div');
		mount.className = 'elodin-review-state-color-mount';
		mount.dataset.colorInput = color.id;
		colorCell.append(color, mount);

		var actionCell = document.createElement('div');
		actionCell.className = 'elodin-review-state-action';
		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'button-link-delete elodin-recently-edited-remove-review-state';
		remove.textContent = strings.remove || 'Remove';
		actionCell.appendChild(remove);

		row.append(enabledCell, nameCell, colorCell, actionCell);
		rows.appendChild(row);
		mountColorPickers(row);
		label.focus();
	}

	function clearReviewStates(button) {
		var postTypeLabel = button.dataset.postTypeLabel || button.dataset.postType || '';
		if (!window.confirm((strings.clearConfirm || 'Clear all of your review states for this content type?') + '\n\n' + postTypeLabel)) {
			return;
		}

		var data = new window.FormData();
		data.set('action', 'elodin_recently_edited_clear_review_states');
		data.set('nonce', form.querySelector('[name="elodin_recently_edited_settings_nonce"]').value);
		data.set('post_type', button.dataset.postType || '');
		setStatus(strings.clearing || 'Clearing...', false);

		var request = new window.XMLHttpRequest();
		request.open('POST', config.ajaxUrl || window.ajaxurl, true);
		request.onload = function () {
			var response = parseResponse(request);
			setStatus(
				response && response.data && response.data.message ? response.data.message : (strings.clearFailed || 'Unable to clear review states.'),
				!response || !response.success
			);
		};
		request.onerror = function () {
			setStatus(strings.clearFailed || 'Unable to clear review states.', true);
		};
		request.send(data);
	}

	form.addEventListener('change', function (event) {
		if (!event.target || event.target.type === 'button') {
			return;
		}
		var row = event.target.closest('.elodin-review-state-row');
		if (row && event.target.type === 'checkbox') {
			row.classList.toggle('is-disabled', !event.target.checked);
		}
		scheduleSave(250);
	});

	form.addEventListener('input', function (event) {
		if (event.target && (event.target.type === 'text' || event.target.type === 'color')) {
			scheduleSave(500);
		}
	});

	document.getElementById('elodin-recently-edited-add-review-state').addEventListener('click', createStateRow);
	form.addEventListener('click', function (event) {
		var remove = event.target.closest('.elodin-recently-edited-remove-review-state');
		if (remove) {
			var row = remove.closest('.elodin-review-state-row');
			if (row && row.dataset.builtin !== '1' && window.confirm(strings.removeConfirm || 'Remove this custom review state?')) {
				row.remove();
				scheduleSave(100);
			}
			return;
		}

		var clear = event.target.closest('.elodin-recently-edited-clear-review-states');
		if (clear) {
			clearReviewStates(clear);
		}
	});

	mountColorPickers(editor);
})();

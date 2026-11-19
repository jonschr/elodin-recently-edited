(function () {
	'use strict';
	var key = 'elodin_recently_edited_shortcut_training_off';
	var graceMs = 60000;
	var disabledAt = 0;
	var timer;

	function read() {
		try {
			var value = Number(window.localStorage.getItem(key));
			// The old value "1" is an existing opt-out whose grace period has ended.
			return Number.isFinite(value) && value > 0 ? value : 0;
		} catch (error) {
			return disabledAt;
		}
	}
	function canUndoFromMenu() {
		return !disabledAt || Date.now() - disabledAt < graceMs;
	}
	function notify() {
		window.clearTimeout(timer);
		var remaining = disabledAt + graceMs - Date.now();
		if (disabledAt && remaining > 0) timer = window.setTimeout(notify, remaining);
		window.dispatchEvent(new Event('elodin-shortcut-training-change'));
	}
	function refresh() {
		disabledAt = read();
		notify();
	}
	window.ElodinRecentlyEditedTraining = {
		isOff: function () { return disabledAt > 0; },
		canUndoFromMenu: canUndoFromMenu,
		setOff: function (off, fromSettings) {
			if (!off && !fromSettings && !canUndoFromMenu()) { notify(); return false; }
			disabledAt = off ? (disabledAt || Date.now()) : 0;
			try {
				window.localStorage.setItem(key, String(disabledAt));
			} catch (error) {
				// Storage may be blocked; retain the preference for this page.
			}
			notify();
			return true;
		},
	};
	window.addEventListener('storage', function (event) {
		if (event.key === key || event.key === null) refresh();
	});
	window.addEventListener('focus', refresh);
	document.addEventListener('visibilitychange', function () {
		if (!document.hidden) refresh();
	});
	refresh();
})();

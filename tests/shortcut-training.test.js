// Run: node --test tests/shortcut-training.test.js
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const source = readFileSync(require('node:path').join(__dirname, '../assets/js/shortcut-training.js'), 'utf8');
const key = 'elodin_recently_edited_shortcut_training_off';

function harness(storage = new Map(), blocked = false, startTime = 1_800_000_000_000) {
	let now = startTime, nextTimer = 0, notifications = 0;
	const timers = new Map(), listeners = new Map();
	const window = {
		localStorage: {
			getItem: name => { if (blocked) throw Error('Blocked'); return storage.get(name) ?? null; },
			setItem: (name, value) => { if (blocked) throw Error('Blocked'); storage.set(name, value); },
		},
		setTimeout: (callback, delay) => { const id = ++nextTimer; timers.set(id, { callback, at: now + delay }); return id; },
		clearTimeout: id => timers.delete(id),
		addEventListener: (type, callback) => listeners.set(type, callback),
		dispatchEvent: () => { notifications++; },
	};
	vm.runInNewContext(source, { window, document: { addEventListener() {} }, Date: { now: () => now }, Event: class {} });
	return {
		api: window.ElodinRecentlyEditedTraining,
		storage,
		notifications: () => notifications,
		time: () => now,
		advance: duration => {
			now += duration;
			for (const [id, timer] of timers) if (timer.at <= now) { timers.delete(id); timer.callback(); }
		},
		storageEvent: () => listeners.get('storage')({ key }),
	};
}

test('training defaults on; menu undo ends exactly one minute after opt-out', () => {
	const h = harness();
	assert.equal(h.api.isOff(), false);
	assert.equal(h.api.canUndoFromMenu(), true);
	h.api.setOff(true);
	h.advance(59_999);
	assert.equal(h.api.canUndoFromMenu(), true);
	const before = h.notifications();
	h.advance(1);
	assert.equal(h.api.canUndoFromMenu(), false);
	assert.ok(h.notifications() > before, 'expiry must update the open menu');
	assert.equal(h.api.setOff(false), false, 'expired menu cannot re-enable training');
	assert.equal(h.api.isOff(), true);
	assert.equal(h.api.setOff(false, true), true, 'settings can always re-enable training');
	assert.equal(h.api.isOff(), false);
	assert.equal(h.api.canUndoFromMenu(), true);
});

test('repeated opt-out and reload do not restart the undo window', () => {
	const h = harness();
	h.api.setOff(true);
	const timestamp = h.storage.get(key);
	h.advance(30_000);
	h.api.setOff(true);
	assert.equal(h.storage.get(key), timestamp);
	const reloaded = harness(h.storage, false, h.time());
	assert.equal(reloaded.api.isOff(), true);
	reloaded.advance(30_000);
	assert.equal(reloaded.api.canUndoFromMenu(), false, 'reload preserves the original deadline');
	h.advance(30_000);
	assert.equal(h.api.canUndoFromMenu(), false);
	h.api.setOff(false, true);
	h.api.setOff(true);
	assert.equal(h.api.canUndoFromMenu(), true, 'a new opt-out gets a new undo window');
});

test('legacy opt-outs are expired, tabs synchronize, and clearing storage restores default', () => {
	const storage = new Map([[key, '1']]);
	const a = harness(storage), b = harness(storage);
	assert.equal(a.api.isOff(), true);
	assert.equal(a.api.canUndoFromMenu(), false);
	a.api.setOff(false, true);
	b.storageEvent();
	assert.equal(b.api.isOff(), false);
	storage.set(key, '1'); b.storageEvent();
	assert.equal(b.api.isOff(), true);
	storage.clear(); b.storageEvent();
	assert.equal(b.api.isOff(), false);
});

test('blocked storage still supports the current page and one-minute expiry', () => {
	const h = harness(new Map(), true);
	h.api.setOff(true);
	assert.equal(h.api.isOff(), true);
	h.advance(60_000);
	assert.equal(h.api.canUndoFromMenu(), false);
	h.api.setOff(false, true);
	assert.equal(h.api.isOff(), false);
});

test('undoing within one minute cancels expiry and retains the menu control', () => {
	const h = harness();
	h.api.setOff(true);
	h.advance(30_000);
	assert.equal(h.api.setOff(false), true);
	h.advance(60_000);
	assert.equal(h.api.isOff(), false);
	assert.equal(h.api.canUndoFromMenu(), true);
});

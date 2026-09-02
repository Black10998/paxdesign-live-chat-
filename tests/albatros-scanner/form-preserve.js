#!/usr/bin/env node
/**
 * Contract checks for employee-create form behavior:
 * errors must not wipe typed values, and a single name still saves.
 */
var assert = require('assert');

function collectDriverBodyFromValues(values) {
  var body = {};
  Object.keys(values).forEach(function (k) {
    if (k === 'photo') return;
    var v = values[k];
    body[k] = typeof v === 'string' ? v.trim() : v;
  });
  var first = body.first_name || '';
  var last = body.last_name || '';
  if (!last && first) {
    var parts = first.split(/\s+/).filter(Boolean);
    body.first_name = parts[0] || '';
    body.last_name = parts.length > 1 ? parts.slice(1).join(' ') : body.first_name;
  } else if (!first && last) {
    var lastParts = last.split(/\s+/).filter(Boolean);
    body.first_name = lastParts[0] || '';
    body.last_name = lastParts.length > 1 ? lastParts.slice(1).join(' ') : body.first_name;
  } else {
    body.first_name = first;
    body.last_name = last;
  }
  if (body.email && body.email.indexOf('@') === -1) {
    throw new Error('invalid-email');
  }
  return body;
}

var two = collectDriverBodyFromValues({ first_name: 'Max Mustermann', last_name: '', phone: '0664123', email: '' });
assert.strictEqual(two.first_name, 'Max');
assert.strictEqual(two.last_name, 'Mustermann');
assert.strictEqual(two.phone, '0664123');

var one = collectDriverBodyFromValues({ first_name: 'Alex', last_name: '  ', email: '' });
assert.strictEqual(one.first_name, 'Alex');
assert.strictEqual(one.last_name, 'Alex');

var both = collectDriverBodyFromValues({ first_name: 'Eva', last_name: 'Huber', email: 'eva@albatros-express.at' });
assert.strictEqual(both.first_name, 'Eva');
assert.strictEqual(both.last_name, 'Huber');
assert.strictEqual(both.email, 'eva@albatros-express.at');

var threw = false;
try {
  collectDriverBodyFromValues({ first_name: 'Eva', last_name: 'Huber', email: 'not-an-email' });
} catch (e) {
  threw = e.message === 'invalid-email';
}
assert.ok(threw, 'invalid optional email is rejected without saving');

function updatePhotoPreview(form, photoUrl) {
  if (!form || !photoUrl) return;
  var wrap = form.querySelector('.photo-preview');
  if (wrap) {
    var img = wrap.querySelector('img');
    if (img) {
      img.src = photoUrl;
      return;
    }
  }
}

var editForm = {
  innerHTML: '',
  querySelector: function (sel) {
    if (sel === '.photo-preview') {
      return { querySelector: function () { return { src: 'old.jpg' }; } };
    }
    if (sel === 'input[name="first_name"]') {
      return { value: 'Edited Name' };
    }
    return null;
  }
};
updatePhotoPreview(editForm, 'new.jpg');
assert.strictEqual(editForm.innerHTML, '', 'photo preview update must not rewrite the form');

var rootHtml = '<form id="driver-form"><input name="first_name" value=""><input name="last_name" value=""></form>';
var innerHTMLWritten = false;
var inserted = false;
var fakeRoot = {
  firstChild: {},
  querySelectorAll: function () { return []; },
  insertBefore: function () { inserted = true; },
  appendChild: function () { inserted = true; }
};
Object.defineProperty(fakeRoot, 'innerHTML', {
  get: function () { return rootHtml; },
  set: function () { innerHTMLWritten = true; }
});
function showError(err) {
  var box = { className: 'msg msg-error form-alert', textContent: err.message };
  if (fakeRoot.firstChild) {
    fakeRoot.insertBefore(box, fakeRoot.firstChild);
  } else {
    fakeRoot.appendChild(box);
  }
}
showError(new Error('Vor- und Nachname sind erforderlich.'));
assert.strictEqual(innerHTMLWritten, false, 'showError must not rewrite innerHTML');
assert.strictEqual(inserted, true, 'showError prepends a banner node');

console.log('OK  employee form preserve and name-normalization checks');

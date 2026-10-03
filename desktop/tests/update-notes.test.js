'use strict';

const assert = require('node:assert/strict');
const test = require('node:test');
const { formatReleaseNotes } = require('../update-notes');

test('formats HTML release notes as readable plain-text sections and bullets', () => {
  const notes = [
    '<h2>Perbaikan dan fitur</h2>',
    '<ul>',
    '<li>Sinkronisasi Tendik &amp; Dosen diperbaiki.</li>',
    '<li>Backup disimpan di <code>storage/app/backups</code>.</li>',
    '</ul>',
    '<h2>Catatan</h2>',
    '<p>Data tetap aman.</p>',
  ].join('');

  assert.equal(
    formatReleaseNotes(notes),
    'Perbaikan dan fitur\n- Sinkronisasi Tendik & Dosen diperbaiki.\n- Backup disimpan di storage/app/backups.\n\nCatatan\n\nData tetap aman.',
  );
});

test('formats Markdown headings, emphasis, and lists without exposing markup', () => {
  const notes = '## Perbaikan\n- **Popup** `Update` dirapikan.\n- Catatan versi ditampilkan.';

  assert.equal(
    formatReleaseNotes(notes),
    'Perbaikan\n- Popup Update dirapikan.\n- Catatan versi ditampilkan.',
  );
});

test('returns an empty string when release notes are missing', () => {
  assert.equal(formatReleaseNotes(null), '');
});

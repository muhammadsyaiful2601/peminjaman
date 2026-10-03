/**
 * Kode Google Apps Script yang dipasang petugas di spreadsheet-nya.
 *
 * Disajikan apa adanya supaya petugas cukup menyalin-tempel tanpa menulis
 * kode sendiri. Skrip mencari baris berdasarkan NIM/NIP atau email,
 * lalu menulis ulang kolomnya — memperbarui bila baris ada, menambah bila
 * belum.
 *
 * Nama kolom dicari dengan normalisasi huruf kecil tanpa tanda baca/spasi,
 * jadi variations seperti "Jabatan / Unit Kerja", "Jabatan", "Unit Kerja",
 * "Prodi", dan "Program Studi" semuanya dikenali.
 */
export const APPS_SCRIPT_CODE = `function doPost(e) {
  var data = JSON.parse(e.postData.contents);
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
  var lastRow = sheet.getLastRow();
  if (lastRow < 1) return reply({ ok: false, error: 'Sheet kosong' });

  var headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];

  // Cari kolom identitas dan kolom lain dari baris header.
  var col = { id: -1 };
  for (var c = 0; c < headers.length; c++) {
    var key = String(headers[c]).toLowerCase().replace(/[^a-z0-9]/g, '');
    if (col.id < 0 && (key === 'nim' || key === 'nip' || key === 'nipopsional' || key === 'nimnip' || key === 'nomoridentitas')) {
      col.id = c;
    } else if (key === 'role' || key === 'jenis') {
      col.role = c;
    } else if (key === 'nama') {
      col.name = c;
    } else if (key === 'jabatan' || key === 'jabatanunitkerja' || key === 'unitkerja'
               || key === 'prodi' || key === 'programstudi' || key === 'fakultas') {
      col.position = c;
    } else if (key === 'email') {
      col.email = c;
    } else if (key === 'notelepon' || key === 'nohp' || key === 'phone') {
      col.phone = c;
    }
  }

  if (col.id < 0 && col.email === undefined) {
    return reply({ ok: false, error: 'Kolom Email tidak ditemukan untuk mencocokkan data' });
  }

  // Utamakan NIM/NIP; email menjadi kunci cadangan jika NIP tidak tersedia.
  var target = String(data.identity || '').trim();
  var targetEmail = String(data.columns['Email'] || '').trim().toLowerCase();
  var row = -1;
  if (col.id >= 0 && target && lastRow > 1) {
    var ids = sheet.getRange(2, col.id + 1, lastRow - 1, 1).getValues();
    for (var r = 0; r < ids.length; r++) {
      if (String(ids[r][0]).trim() === target) { row = r + 2; break; }
    }
  }

  if (row < 0 && col.email !== undefined && targetEmail && lastRow > 1) {
    var emails = sheet.getRange(2, col.email + 1, lastRow - 1, 1).getValues();
    for (var e = 0; e < emails.length; e++) {
      if (String(emails[e][0]).trim().toLowerCase() === targetEmail) { row = e + 2; break; }
    }
  }

  if (row < 0) {
    // Isi baris paling bawah hanya jika seluruh selnya kosong agar baris
    // pegawai tanpa NIP tidak tertimpa oleh data baru.
    var lastValues = lastRow > 1
      ? sheet.getRange(lastRow, 1, 1, sheet.getLastColumn()).getValues()[0]
      : [];
    var lastRowEmpty = lastValues.every(function (value) { return !String(value).trim(); });
    row = lastRowEmpty ? lastRow : lastRow + 1;
  }

  function write(index, value) {
    if (index === undefined || index < 0) return;
    sheet.getRange(row, index + 1).setValue(value == null ? '' : value);
  }

  write(col.id, target);
  write(col.role, data.columns['Role']);
  write(col.name, data.columns['Nama']);
  write(col.position, data.columns['Jabatan / Unit Kerja']);
  write(col.email, data.columns['Email']);
  write(col.phone, data.columns['No. Telepon']);

  return reply({ ok: true, row: row });
}

function reply(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}`

/**
 * Langkah pemasangan skrip, ditampilkan sebagai daftar di antarmuka.
 * Nomornya dipakai untuk merujuk dari panel pengaturan.
 */
export const APPS_SCRIPT_STEPS = [
  'Buka Google Sheets yang berisi data peminjam, lalu pilih **Ekstensi → Apps Script**.',
  'Di panel kiri, klik **+**, lalu pilih **Script**. Hapus seluruh kode yang ada dan tempelkan kode skrip di bawah.',
  'Tekan **Ctrl+S** (atau klik ikon Simpan) untuk menyimpan skrip.',
  'Di kanan atas, pilih **Deploy → New deployment**.',
  'Pada bagian **Select type**, pilih **Web app**, lalu klik **Next**.',
  'Isi **Description** (misalnya, "Tulis balik jabatan"). Pada **Execute as**, pilih akun Anda sendiri.',
  'Pada **Who has access**, pilih **Anyone** agar aplikasi dapat mengakses URL webhook.',
  'Klik **Deploy**, lalu salin **Web app URL** yang ditampilkan. URL tersebut berakhir dengan `/exec`.',
  'Kembali ke aplikasi, lalu tempel URL tersebut pada kolom **Tulis balik Jabatan ke spreadsheet**.',
  'Klik **Simpan Webhook**, lalu **Uji Kirim** untuk memastikan koneksi berhasil.',
]
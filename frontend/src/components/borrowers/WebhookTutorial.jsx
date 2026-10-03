import { useState } from 'react'
import { CheckCircle2, Copy, ExternalLink, X } from 'lucide-react'
import { APPS_SCRIPT_CODE, APPS_SCRIPT_STEPS } from './appsScript'

/**
 * Tutorial memasang Google Apps Script untuk tulis balik jabatan.
 *
 * Ditampilkan sebagai lapisan penuh di atas panel pengaturan supaya langkah-
 * langkahnya terbaca utuh tanpa membuat panel memanjang. Kode yang
 * ditampilkan disalin dari `appsScript.js` yang sama dengan tombol salin di
 * panel, jadi tutorial dan kode tidak mungkin berbeda.
 *
 * @param {object}   props
 * @param {string}   props.label      Nama kelompok (Mahasiswa/Tendik/Dosen).
 * @param {boolean}  props.copied     Tombol salin baru saja dipakai.
 * @param {Function} props.copyScript Fungsi menyalin kode skrip.
 * @param {Function} props.onClose    Dipanggil saat tutorial ditutup.
 */
export default function WebhookTutorial({ label, copied, copyScript, onClose }) {
  // Bagian yang sedang dibuka. Maksimal satu terbuka supaya halaman ringkas.
  const [openSection, setOpenSection] = useState('langkah')

  const sections = [
    { key: 'langkah', title: 'Langkah 1–10: Pasang skrip' },
    { key: 'kode', title: 'Kode skrip' },
    { key: 'cek', title: 'Langkah 11–13: Hubungkan & uji' },
    { key: 'tips', title: 'Cara kerja & solusi masalah' },
  ]

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 p-4 py-8">
      <div className="w-full max-w-3xl rounded-xl bg-white shadow-2xl">
        <div className="sticky top-0 z-10 flex items-start justify-between gap-4 rounded-t-xl border-b border-slate-200 bg-white px-6 py-4">
          <div>
            <h2 className="text-lg font-bold text-slate-900">Panduan Menghubungkan Jabatan ke Google Sheets</h2>
            <p className="text-sm text-slate-500">
              Untuk kelompok <strong>{label}</strong>. Setelah konfigurasi dilakukan, perubahan jabatan di aplikasi
              akan otomatis dikirim ke Google Sheets.
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
            aria-label="Tutup tutorial"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="px-6 py-4">
          <div className="mb-4 flex flex-wrap gap-2">
            {sections.map((section) => (
              <button
                key={section.key}
                type="button"
                onClick={() => setOpenSection(section.key)}
                className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                  openSection === section.key
                    ? 'bg-cyan-600 text-white'
                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                }`}
              >
                {section.title}
              </button>
            ))}
          </div>

          {openSection === 'langkah' && <InstallSteps />}
          {openSection === 'kode' && <ScriptCode copied={copied} copyScript={copyScript} />}
          {openSection === 'cek' && <ConnectSteps label={label} />}
          {openSection === 'tips' && <HowItWorks label={label} />}
        </div>
      </div>
    </div>
  )
}

/** Langkah 1-10: memasang skrip di Google Sheets. */
function InstallSteps() {
  return (
    <section>
      <p className="mb-3 text-sm text-slate-600">
        Selesaikan 10 langkah berikut. Proses ini memerlukan waktu sekitar 2 menit.
      </p>
      <ol className="space-y-3">
        {APPS_SCRIPT_STEPS.map((step, index) => (
          <li key={step} className="flex gap-3">
            <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-cyan-600 text-xs font-bold text-white">
              {index + 1}
            </span>
            <span className="pt-0.5 text-sm text-slate-700">{formatStep(step)}</span>
          </li>
        ))}
      </ol>
    </section>
  )
}

/** Kode skrip lengkap dengan tombol salin. */
function ScriptCode({ copied, copyScript }) {
  return (
    <section>
      <div className="mb-3 flex items-center justify-between gap-3">
        <p className="text-sm text-slate-600">
          Salin seluruh kode ini ke <code className="rounded bg-slate-100 px-1">Code.gs</code> di Apps Script.
        </p>
        <button
          type="button"
          onClick={copyScript}
          className="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-cyan-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-cyan-700"
        >
          {copied ? <CheckCircle2 className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
          {copied ? 'Tersalin' : 'Salin kode'}
        </button>
      </div>
      <pre className="max-h-96 overflow-auto rounded-lg bg-slate-900 p-4 text-[11px] leading-relaxed text-slate-100">
        {APPS_SCRIPT_CODE}
      </pre>
    </section>
  )
}

/** Langkah 11-13: menghubungkan webhook di aplikasi dan mengujinya. */
function ConnectSteps({ label }) {
  return (
    <section className="space-y-4">
      <ol className="space-y-3">
        <StepRow number={11} text="Tutup panduan ini, lalu salin Web app URL yang ditampilkan (berakhir dengan /exec)." />
        <StepRow number={12} text="Tempel URL tersebut pada kolom Tulis balik Jabatan ke spreadsheet, lalu klik Simpan Webhook." />
        <StepRow number={13} text="Klik Uji Kirim. Jika pesan konfirmasi berwarna hijau muncul, webhook berhasil terhubung." />
      </ol>

      <div className="rounded-lg border border-amber-200 bg-amber-50 p-4">
        <p className="text-sm font-semibold text-amber-900">Verifikasi koneksi</p>
        <ol className="mt-2 list-inside list-decimal space-y-1 text-sm text-amber-800">
          <li>Buka spreadsheet {label.toLowerCase()} di tab lain.</li>
          <li>Ubah jabatan salah satu peminjam di aplikasi, lalu klik Simpan Perubahan.</li>
          <li>Pastikan pesan konfirmasi &ldquo;Perubahan data juga dikirim ke spreadsheet&rdquo; muncul.</li>
          <li>Muat ulang spreadsheet dan pastikan kolom Jabatan / Unit Kerja telah diperbarui.</li>
        </ol>
      </div>

      <p className="text-xs text-slate-500">
        <strong>Catatan:</strong> jika muncul peringatan bahwa data gagal dikirim ke spreadsheet, jabatan tetap
        tersimpan di aplikasi. Periksa konfigurasi webhook, lalu coba kembali. Data Anda tidak akan hilang.
      </p>
    </section>
  )
}

/** Cara kerja skrip, kolom yang dikenali, dan pemecahan masalah. */
function HowItWorks({ label }) {
  return (
    <section className="space-y-4 text-sm text-slate-700">
      <div>
        <h3 className="mb-1 font-semibold text-slate-900">Cara kerja</h3>
        <p>
          Skrip mencari baris berdasarkan NIM/NIP. Jika NIP tidak tersedia, email digunakan untuk mencari baris
          yang sesuai. Data yang sudah ada akan diperbarui; data baru ditambahkan pada baris di bagian bawah.
        </p>
      </div>

      <div>
        <h3 className="mb-1 font-semibold text-slate-900">Kolom yang dikenali</h3>
        <p>
          Pencarian nama kolom mengabaikan perbedaan huruf besar dan tanda baca. Kolom peran dapat bernama Role
          atau Jenis. Kolom tugas/unit kerja dapat bernama Jabatan / Unit Kerja, Jabatan, atau Unit Kerja.
          Kolom identitas dapat bernama NIM atau NIP (opsional untuk pegawai); email menjadi kunci pencocokan
          jika identitas tidak tersedia.
        </p>
      </div>

      <div>
        <h3 className="mb-2 font-semibold text-slate-900">Jika ada masalah</h3>
        <ul className="space-y-2">
          <li>
            <strong>Data tidak dapat dicocokkan</strong> — pastikan spreadsheet memiliki kolom Email. Jika NIP
            digunakan, pastikan header-nya bernama NIP.
          </li>
          <li>
            <strong>URL ditolak</strong> — pastikan URL diawali dengan https://script.google.com/macros/ dan diakhiri dengan /exec.
          </li>
          <li>
            <strong>Kesalahan 403 saat membuat Apps Script</strong> — pada pengaturan Who has access, pilih Anyone (bukan
            Anyone with Google account).
          </li>
          <li>
            <strong>Uji Kirim gagal: data belum tersedia</strong> — tambahkan setidaknya satu data {label.toLowerCase()},
            lalu ulangi pengujian.
          </li>
          <li>
            <strong>Untuk menonaktifkan</strong> — hapus URL, lalu klik Simpan Webhook. Spreadsheet akan kembali
            bersifat hanya-baca, dan aplikasi tetap berfungsi seperti biasa.
          </li>
        </ul>
      </div>

      <div className="rounded-lg border border-cyan-200 bg-cyan-50 p-3">
        <p className="flex items-start gap-2 text-sm text-cyan-900">
          <ExternalLink className="mt-0.5 h-4 w-4 shrink-0" />
          <span>
            Setiap kelompok menggunakan webhook tersendiri. Ulangi pemasangan untuk kelompok{' '}
            <strong>{label}</strong> bila kelompok lain (misalnya Dosen) juga ingin memakai.
          </span>
        </p>
      </div>
    </section>
  )
}

/** Satu baris langkah bernomor. */
function StepRow({ number, text }) {
  return (
    <li className="flex gap-3">
      <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-cyan-600 text-xs font-bold text-white">
        {number}
      </span>
      <span className="pt-0.5 text-sm text-slate-700">{text}</span>
    </li>
  )
}

function formatStep(text) {
  return text.split(/(\*\*[^*]+\*\*)/g).map((part, index) => {
    if (part.startsWith('**') && part.endsWith('**')) {
      return <strong key={index}>{part.slice(2, -2)}</strong>
    }
    return part
  })
}

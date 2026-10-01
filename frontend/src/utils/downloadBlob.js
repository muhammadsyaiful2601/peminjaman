/**
 * Ambil nama berkas dari header Content-Disposition yang dikirim server.
 * Dipakai agar nama unduhan (mis. nama backup yang sudah berisi tanggal &
 * jam) sama persis dengan yang disiapkan backend.
 */
export function filenameFromResponse(response, fallback = 'berkas') {
  const disposition = response?.headers?.['content-disposition'] || ''
  const match = disposition.match(/filename="?([^";]+)"?/)

  return match && match[1] ? match[1] : fallback
}

/**
 * Nama cadangan yang tetap memuat tanggal & jam, dipakai bila server tidak
 * mengirim Content-Disposition. Formatnya sama dengan backend:
 * `YYYY-MM-DD-HHmmss` sehingga mudah diurutkan dari yang lama ke yang baru.
 */
export function timestampedFilename(prefix, extension, date = new Date()) {
  const pad = (value) => String(value).padStart(2, '0')
  const stamp = [
    date.getFullYear(),
    pad(date.getMonth() + 1),
    pad(date.getDate()),
  ].join('-') + `-${pad(date.getHours())}${pad(date.getMinutes())}${pad(date.getSeconds())}`

  return `${prefix}-${stamp}.${extension}`
}

/**
 * Pastikan respons unduhan benar-benar dokumen, bukan pesan galat.
 *
 * Tanpa pemeriksaan ini, galat server (401/419/500) yang dikembalikan sebagai
 * Blob akan disimpan sebagai berkas `.pdf` dan saat dibuka tampak seperti
 * halaman putih. Melempar galat membuat pengguna melihat pesan yang jelas.
 */
async function assertUsableDocument(blob, filename) {
  if (!blob || typeof blob.size !== 'number') {
    throw new Error('Respons unduhan tidak berisi berkas.')
  }

  if (blob.size === 0) {
    throw new Error(`Berkas ${filename} kosong. Silakan coba lagi.`)
  }

  if (!String(filename).toLowerCase().endsWith('.pdf')) return

  const head = new Uint8Array(await blob.slice(0, 5).arrayBuffer())
  const signature = String.fromCharCode(...head)
  if (!signature.startsWith('%PDF')) {
    throw new Error('Server tidak mengembalikan dokumen PDF yang sah. Silakan muat ulang halaman lalu coba lagi.')
  }
}

export async function downloadBlob(blob, filename) {
  await assertUsableDocument(blob, filename)

  const isPdf = String(filename).toLowerCase().endsWith('.pdf')

  if (window.desktop?.isDesktop && isPdf && window.desktop.savePdf) {
    return window.desktop.savePdf(await blob.arrayBuffer(), filename)
  }

  if (window.desktop?.isDesktop && window.desktop.saveFile) {
    // ArrayBuffer dikirim lewat IPC (bukan array angka) supaya berkas besar
    // seperti arsip backup ZIP tidak menghabiskan memori.
    return window.desktop.saveFile(await blob.arrayBuffer(), filename)
  }

  if (window.desktop?.isDesktop && window.desktop.savePdf) {
    return window.desktop.savePdf(new Uint8Array(await blob.arrayBuffer()), filename)
  }

  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.style.display = 'none'
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.setTimeout(() => URL.revokeObjectURL(url), 1000)
}
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

export async function downloadBlob(blob, filename) {
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
/**
 * Tunggu gambar & font pada iframe supaya tidak ada bagian dokumen yang
 * tercetak kosong.
 */
async function settleDocument(doc) {
  await new Promise((resolve) => {
    let resolved = false
    const done = () => {
      if (resolved) return
      resolved = true
      resolve()
    }
    const images = Array.from(doc.images || [])
    if (images.length === 0 || images.every((img) => img.complete)) {
      setTimeout(done, 150)
      return
    }
    let pending = images.length
    images.forEach((img) => {
      const settle = () => {
        pending -= 1
        if (pending <= 0) done()
      }
      if (img.complete) {
        settle()
      } else {
        img.addEventListener('load', settle)
        img.addEventListener('error', settle)
      }
    })
    setTimeout(done, 2000)
  })

  await Promise.race([
    doc.fonts?.ready || Promise.resolve(),
    new Promise((resolve) => setTimeout(resolve, 2000)),
  ])
  await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)))
}

/**
 * Cetak dokumen HTML dari jendela cetak terpisah.
 *
 * Di aplikasi desktop, `iframe.contentWindow.print()` tidak dapat dipakai:
 * Electron mencetak seluruh jendela utama (dan iframe-nya berada di luar
 * layar), sehingga hasilnya keluar kosong. Karena itu dokumen diserahkan ke
 * main process yang mencetak lewat BrowserWindow khusus.
 *
 * Di browser, dokumen ditulis ke iframe tersembunyi lalu dicetak dari sana —
 * bukan dengan `window.print()` pada halaman aplikasi, karena yang tercetak
 * akan berupa kerangka aplikasi (sidebar & footer tetap, serta offset
 * `md:pl-*` yang ikut aktif pada lebar kertas A4) sehingga isi bergeser,
 * terpotong, atau tampak kosong.
 *
 * @param {string} html dokumen HTML lengkap dari server
 * @returns {Promise<{ok: boolean, canceled?: boolean, message?: string}>}
 */
export async function printHtmlDocument(html) {
  if (window.desktop?.printDocument) {
    return window.desktop.printDocument(html)
  }

  const iframe = document.createElement('iframe')
  iframe.setAttribute('aria-hidden', 'true')
  iframe.style.position = 'fixed'
  iframe.style.left = '-10000px'
  iframe.style.top = '0'
  iframe.style.width = '210mm'
  iframe.style.height = '297mm'
  iframe.style.border = '0'
  document.body.appendChild(iframe)

  const doc = iframe.contentWindow.document
  doc.open()
  doc.write(html)
  doc.close()

  await settleDocument(doc)

  try {
    iframe.contentWindow.focus()
    iframe.contentWindow.print()
    return { ok: true }
  } finally {
    setTimeout(() => {
      iframe.remove()
    }, 60000)
  }
}

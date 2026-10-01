/**
 * Mencetak dokumen HTML di jendela cetak terpisah.
 *
 * Dokumen ditulis ke iframe tersembunyi lalu dicetak dari iframe itu, bukan
 * dengan `window.print()` pada halaman aplikasi. Alasannya: mencetak halaman
 * aplikasi berarti mencetak kerangka aplikasi juga (sidebar & footer tetap,
 * serta offset `md:pl-*` yang ikut aktif pada lebar kertas A4) sehingga hasil
 * cetak bergeser, terpotong, bahkan tampak kosong.
 *
 * @param {string} html dokumen HTML lengkap dari server
 */
export async function printHtmlDocument(html) {
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

  // Tunggu seluruh gambar (logo kop surat & tanda tangan) selesai dimuat agar
  // tidak ada bagian dokumen yang tercetak kosong.
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
        if (pending === 0) done()
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

  try {
    iframe.contentWindow.focus()
    iframe.contentWindow.print()
  } finally {
    setTimeout(() => {
      iframe.remove()
    }, 60000)
  }
}

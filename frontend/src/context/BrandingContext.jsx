import { createContext, useContext, useEffect, useState } from 'react'
import api from '../api/axios'
import defaultLogo from '../assets/Logo_Politeknik_Negeri_Padang_(2014).svg'

const defaults = {
  app_name: 'Sistem Peminjaman Barang',
  organization_ministry: 'KEMENTERIAN PENDIDIKAN DAN KEBUDAYAAN',
  organization_name: 'POLITEKNIK NEGERI PADANG',
  organization_unit: 'PROGRAM STUDI DILUAR KAMPUS UTAMA',
  organization_department: 'PRODI D-3 SISTEM INFORMASI',
  organization_address: 'Jl. Raya Tigo Jangko Kec. Lintau Buo - 27292',
  login_description: 'Kelola peminjaman, verifikasi foto dan QR, serta pantau stok barang dalam satu tempat.',
  app_logo_path: null,
  landing_photo_path: null,
  letterhead_logo_path: null,
}

const BrandingContext = createContext(defaults)

export function BrandingProvider({ children }) {
  const [branding, setBranding] = useState(defaults)

  useEffect(() => {
    const favicon = document.querySelector('link[rel="icon"]') || document.createElement('link')
    favicon.rel = 'icon'
    favicon.type = 'image/png'
    document.head.appendChild(favicon)
    favicon.href = defaultLogo
    document.title = defaults.app_name

    return () => {
      document.title = defaults.app_name
    }
  }, [])

  useEffect(() => {
    api.get('/branding').then((response) => {
      const nextBranding = { ...defaults, ...response.data.branding }
      setBranding(nextBranding)

      const favicon = document.querySelector('link[rel="icon"]')
      if (favicon) {
        favicon.href = nextBranding.app_logo_path || defaultLogo
      }
      document.title = nextBranding.app_name
    }).catch(() => {})
  }, [])

  return (
    <BrandingContext.Provider value={{ ...branding, setBranding }}>
      {children}
    </BrandingContext.Provider>
  )
}

export function useBranding() {
  return useContext(BrandingContext)
}

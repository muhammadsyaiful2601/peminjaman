import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: {
    Accept: 'application/json',
  },
})

// Add token + desktop key to requests
api.interceptors.request.use(async (config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  // Mode hybrid: endpoint /api/hybrid/* dilindungi X-Desktop-Key yang
  // hanya diketahui aplikasi desktop (dijembatani lewat preload).
  try {
    if (window.desktop?.getDesktopKey) {
      const key = await window.desktop.getDesktopKey()
      if (key) {
        config.headers['X-Desktop-Key'] = key
      }
    }
  } catch {
    /* di luar desktop: abaikan */
  }
  return config
})

// Handle 401 responses
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default api
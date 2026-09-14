import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import api from '../api/axios'
import { Plus, Package, Search, Pencil, Trash2, PackageX, ChevronLeft, ChevronRight, Expand, X } from 'lucide-react'

function Items() {
  const { user } = useAuth()
  const [items, setItems] = useState([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [activeImages, setActiveImages] = useState({})
  const [expandedImage, setExpandedImage] = useState(null)

  const isStaff = user?.role === 'admin' || user?.role === 'assistant'

  const fetchItems = async () => {
    setLoading(true)
    try {
      const params = { page, per_page: 12 }
      if (search) params.search = search
      const response = await api.get('/items', { params })
      setItems(response.data.data || [])
      setLastPage(response.data.last_page || 1)
      setTotal(response.data.total || 0)
    } catch (error) {
      // ignore
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchItems()
  }, [page])

  useEffect(() => {
    const timer = setTimeout(() => {
      setPage(1)
      fetchItems()
    }, 500)
    return () => clearTimeout(timer)
  }, [search])

  const handleDelete = async (id) => {
    if (!window.confirm('Yakin ingin menghapus barang ini?')) return
    try {
      await api.delete(`/items/${id}`)
      fetchItems()
    } catch (error) {
      alert(error.response?.data?.message || 'Gagal menghapus barang')
    }
  }

  const getItemImages = (item) => [
    ...(item.image ? [`/storage/${item.image}`] : []),
    ...(item.images || []).map((image) => `/storage/${image.path}`),
  ]

  const showPreviousImage = (itemId, imageCount) => {
    setActiveImages((current) => ({
      ...current,
      [itemId]: ((current[itemId] || 0) - 1 + imageCount) % imageCount,
    }))
  }

  const showNextImage = (itemId, imageCount) => {
    setActiveImages((current) => ({
      ...current,
      [itemId]: ((current[itemId] || 0) + 1) % imageCount,
    }))
  }

  return (
    <div>
      <div className="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Katalog Barang</h1>
          <p className="text-slate-500 mt-1">Total {total} barang tersedia</p>
        </div>
        {isStaff && (
          <Link
            to="/items/new"
            className="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white px-4 py-2.5 rounded-lg font-medium transition-colors"
          >
            <Plus className="w-5 h-5" />
            Tambah Barang
          </Link>
        )}
      </div>

      {/* Search */}
      <div className="mb-6 max-w-md">
        <div className="relative">
          <Search className="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Cari barang, kode, atau kategori..."
            className="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
          />
        </div>
      </div>

      {loading ? (
        <div className="text-center py-12 text-slate-500">Memuat data...</div>
      ) : items.length === 0 ? (
        <div className="text-center py-12 bg-white rounded-xl border border-slate-200">
          <PackageX className="w-16 h-16 text-slate-300 mx-auto mb-4" />
          <p className="text-slate-500">Tidak ada barang ditemukan</p>
        </div>
      ) : (
        <>
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
            {items.map((item) => {
              const itemImages = getItemImages(item)
              const imageIndex = Math.min(activeImages[item.id] || 0, Math.max(itemImages.length - 1, 0))

              return (
              <div key={item.id} className="bg-white rounded-lg border border-slate-200 overflow-hidden hover:shadow-lg transition-shadow">
                <div className="relative aspect-square bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center">
                  {itemImages.length ? (
                    <>
                      <button
                        type="button"
                        onClick={() => setExpandedImage({ item, images: itemImages, index: imageIndex })}
                        className="w-full h-full cursor-zoom-in"
                        title="Perbesar foto"
                      >
                        <img src={itemImages[imageIndex]} alt={`${item.name} ${imageIndex + 1}`} className="w-full h-full object-cover" />
                      </button>
                      {itemImages.length > 1 && (
                        <>
                          <button type="button" onClick={() => showPreviousImage(item.id, itemImages.length)} className="absolute left-1 top-1/2 -translate-y-1/2 rounded-full bg-slate-900/65 p-1 text-white hover:bg-slate-900" aria-label="Foto sebelumnya">
                            <ChevronLeft className="w-4 h-4" />
                          </button>
                          <button type="button" onClick={() => showNextImage(item.id, itemImages.length)} className="absolute right-1 top-1/2 -translate-y-1/2 rounded-full bg-slate-900/65 p-1 text-white hover:bg-slate-900" aria-label="Foto berikutnya">
                            <ChevronRight className="w-4 h-4" />
                          </button>
                          <span className="absolute bottom-1 right-1 rounded bg-slate-900/65 px-1.5 py-0.5 text-[10px] text-white">
                            {imageIndex + 1}/{itemImages.length}
                          </span>
                        </>
                      )}
                      <Expand className="pointer-events-none absolute right-2 top-2 h-4 w-4 text-white drop-shadow" />
                    </>
                  ) : (
                    <Package className="w-10 h-10 text-slate-400" />
                  )}
                </div>
                <div className="p-3">
                  <p className="text-[11px] font-medium text-cyan-600 mb-1">{item.item_code}</p>
                  <h3 className="text-sm font-semibold text-slate-900 mb-1 truncate" title={item.name}>{item.name}</h3>
                  <p className="text-xs text-slate-500 mb-2 truncate" title={item.category}>{item.category}</p>
                  <div className="flex items-center justify-between">
                    <span
                      className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${
                        item.stock > 0
                          ? 'bg-emerald-100 text-emerald-700'
                          : 'bg-red-100 text-red-700'
                      }`}
                    >
                      Stok: {item.stock}
                    </span>
                    {isStaff && (
                      <div className="flex gap-2">
                        <Link
                          to={`/items/${item.id}/edit`}
                          className="p-1.5 text-slate-500 hover:text-cyan-600 hover:bg-cyan-50 rounded-lg"
                        >
                          <Pencil className="w-4 h-4" />
                        </Link>
                        <button
                          onClick={() => handleDelete(item.id)}
                          className="p-1.5 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-lg"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    )}
                  </div>
                </div>
              </div>
              )
            })}
          </div>

          {/* Pagination */}
          {lastPage > 1 && (
            <div className="mt-6 flex items-center justify-center gap-2">
              <button
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page === 1}
                className="px-4 py-2 rounded-lg border border-slate-300 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-50"
              >
                Sebelumnya
              </button>
              <span className="text-sm text-slate-500">
                Halaman {page} dari {lastPage}
              </span>
              <button
                onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
                disabled={page === lastPage}
                className="px-4 py-2 rounded-lg border border-slate-300 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-50"
              >
                Berikutnya
              </button>
            </div>
          )}
        </>
      )}

      {expandedImage && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/85 p-4" onClick={() => setExpandedImage(null)}>
          <div className="relative max-h-full max-w-5xl" onClick={(event) => event.stopPropagation()}>
            <img src={expandedImage.images[expandedImage.index]} alt={expandedImage.item.name} className="max-h-[85vh] max-w-full object-contain rounded-lg" />
            <button type="button" onClick={() => setExpandedImage(null)} className="absolute right-2 top-2 rounded-full bg-slate-900/70 p-2 text-white hover:bg-slate-900" aria-label="Tutup foto">
              <X className="w-5 h-5" />
            </button>
            {expandedImage.images.length > 1 && (
              <>
                <button type="button" onClick={() => setExpandedImage((current) => ({ ...current, index: (current.index - 1 + current.images.length) % current.images.length }))} className="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-slate-900/70 p-2 text-white hover:bg-slate-900" aria-label="Foto sebelumnya">
                  <ChevronLeft className="w-5 h-5" />
                </button>
                <button type="button" onClick={() => setExpandedImage((current) => ({ ...current, index: (current.index + 1) % current.images.length }))} className="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-slate-900/70 p-2 text-white hover:bg-slate-900" aria-label="Foto berikutnya">
                  <ChevronRight className="w-5 h-5" />
                </button>
              </>
            )}
          </div>
        </div>
      )}
    </div>
  )
}

export default Items
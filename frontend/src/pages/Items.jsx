import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import api from '../api/axios'
import { Plus, Package, Search, Pencil, Trash2, PackageX } from 'lucide-react'

function Items() {
  const { user } = useAuth()
  const [items, setItems] = useState([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)

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
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {items.map((item) => (
              <div key={item.id} className="bg-white rounded-xl border border-slate-200 overflow-hidden hover:shadow-lg transition-shadow">
                <div className="h-40 bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center">
                  {item.image ? (
                    <img
                      src={`/storage/${item.image}`}
                      alt={item.name}
                      className="w-full h-full object-cover"
                    />
                  ) : (
                    <Package className="w-16 h-16 text-slate-400" />
                  )}
                </div>
                <div className="p-4">
                  <p className="text-xs font-medium text-cyan-600 mb-1">{item.item_code}</p>
                  <h3 className="font-semibold text-slate-900 mb-1">{item.name}</h3>
                  <p className="text-sm text-slate-500 mb-3">{item.category}</p>
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
            ))}
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
    </div>
  )
}

export default Items
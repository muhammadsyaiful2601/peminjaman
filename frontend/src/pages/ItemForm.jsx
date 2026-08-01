import { useEffect, useRef, useState } from 'react'
import { useNavigate, useParams, Link } from 'react-router-dom'
import api from '../api/axios'
import { ArrowLeft, Save, Upload, Image as ImageIcon, X } from 'lucide-react'

function ItemForm() {
  const navigate = useNavigate()
  const { id } = useParams()
  const isEdit = Boolean(id)
  const fileInputRef = useRef(null)

  const [form, setForm] = useState({
    item_code: '',
    name: '',
    category: '',
    stock: '',
    image: null,
  })
  const [imagePreview, setImagePreview] = useState(null)
  const [loading, setLoading] = useState(isEdit)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    if (isEdit) {
      const fetchItem = async () => {
        try {
          const response = await api.get(`/items/${id}`)
          const item = response.data.item
          setForm({
            item_code: item.item_code,
            name: item.name,
            category: item.category,
            stock: item.stock,
            image: null,
          })
          if (item.image) {
            setImagePreview(`/storage/${item.image}`)
          }
        } catch (err) {
          setError('Gagal memuat data barang')
        } finally {
          setLoading(false)
        }
      }
      fetchItem()
    }
  }, [id, isEdit])

  const handleChange = (e) => {
    const { name, value, files } = e.target
    if (name === 'image' && files && files[0]) {
      const file = files[0]
      setForm({ ...form, image: file })
      setImagePreview(URL.createObjectURL(file))
    } else if (name !== 'image') {
      setForm({ ...form, [name]: value })
    }
  }

  const handleRemoveImage = () => {
    setForm({ ...form, image: null })
    setImagePreview(null)
    if (fileInputRef.current) {
      fileInputRef.current.value = ''
    }
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setSubmitting(true)

    const formData = new FormData()
    formData.append('item_code', form.item_code)
    formData.append('name', form.name)
    formData.append('category', form.category)
    formData.append('stock', form.stock)
    if (form.image) {
      formData.append('image', form.image)
    }

    try {
      if (isEdit) {
        // For update, use POST with _method=PUT to handle file upload
        formData.append('_method', 'PUT')
        await api.post(`/items/${id}`, formData)
      } else {
        await api.post('/items', formData)
      }
      navigate('/items')
    } catch (err) {
      const data = err.response?.data
      if (data?.errors) {
        setError(Object.values(data.errors).flat().join(', '))
      } else {
        setError(data?.message || 'Gagal menyimpan data barang')
      }
    } finally {
      setSubmitting(false)
    }
  }

  if (loading) {
    return <div className="text-center py-12 text-slate-500">Memuat data...</div>
  }

  return (
    <div className="max-w-2xl mx-auto">
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">
            {isEdit ? 'Edit Barang' : 'Tambah Barang'}
          </h1>
          <p className="text-slate-500 mt-1">
            {isEdit ? 'Perbarui informasi barang' : 'Tambahkan barang baru ke inventaris'}
          </p>
        </div>
        <Link
          to="/items"
          className="inline-flex items-center gap-2 text-slate-500 hover:text-slate-700"
        >
          <ArrowLeft className="w-4 h-4" />
          Kembali
        </Link>
      </div>

      <form onSubmit={handleSubmit} className="bg-white rounded-xl border border-slate-200 p-6 space-y-5">
        {error && (
          <div className="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3">
            {error}
          </div>
        )}

        <div>
          <label className="block text-sm font-medium text-slate-700 mb-1">Kode Barang</label>
          <input
            type="text"
            name="item_code"
            value={form.item_code}
            onChange={handleChange}
            className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
            placeholder="Contoh: ELE-001"
            required
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-slate-700 mb-1">Nama Barang</label>
          <input
            type="text"
            name="name"
            value={form.name}
            onChange={handleChange}
            className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
            placeholder="Nama barang"
            required
          />
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">Kategori</label>
            <input
              type="text"
              name="category"
              value={form.category}
              onChange={handleChange}
              className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
              placeholder="Contoh: Elektronik, Olahraga"
              required
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">Stok</label>
            <input
              type="number"
              name="stock"
              value={form.stock}
              onChange={handleChange}
              min="0"
              className="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none"
              placeholder="0"
              required
            />
          </div>
        </div>

        {/* Image upload with preview */}
        <div>
          <label className="block text-sm font-medium text-slate-700 mb-2">Gambar Barang</label>

          {imagePreview ? (
            <div className="relative inline-block">
              <img
                src={imagePreview}
                alt="Preview"
                className="w-full max-w-xs h-48 object-cover rounded-lg border border-slate-200"
              />
              <button
                type="button"
                onClick={handleRemoveImage}
                className="absolute top-2 right-2 bg-red-600 text-white rounded-full p-1.5 hover:bg-red-700 transition-colors"
              >
                <X className="w-4 h-4" />
              </button>
            </div>
          ) : (
            <div
              onClick={() => fileInputRef.current?.click()}
              className="border-2 border-dashed border-slate-300 hover:border-cyan-500 hover:bg-cyan-50/50 rounded-lg p-8 text-center cursor-pointer transition-colors"
            >
              <div className="inline-flex items-center justify-center w-12 h-12 bg-slate-100 rounded-full mb-3">
                <Upload className="w-6 h-6 text-slate-400" />
              </div>
              <p className="text-sm text-slate-600 font-medium">Klik untuk upload gambar</p>
              <p className="text-xs text-slate-400 mt-1">PNG, JPG, JPEG (max 2MB)</p>
            </div>
          )}

          <input
            ref={fileInputRef}
            type="file"
            name="image"
            onChange={handleChange}
            accept="image/png,image/jpeg,image/jpg"
            className="hidden"
          />
        </div>

        <button
          type="submit"
          disabled={submitting}
          className="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white px-6 py-2.5 rounded-lg font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <Save className="w-5 h-5" />
          {submitting ? 'Menyimpan...' : isEdit ? 'Perbarui' : 'Simpan'}
        </button>
      </form>
    </div>
  )
}

export default ItemForm
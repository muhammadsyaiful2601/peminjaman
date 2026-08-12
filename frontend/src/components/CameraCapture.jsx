import { useEffect, useRef, useState } from 'react'
import { Camera, RefreshCw, CheckCircle, XCircle } from 'lucide-react'

/**
 * Helper: Convert base64 dataURL to File for FormData upload
 */
export function dataURLtoFile(dataURL, filename = 'photo.jpg') {
  const parts = dataURL.split(';base64,')
  const mime = parts[0].split(':')[1]
  const bytes = atob(parts[1])
  const arr = new Uint8Array(bytes.length)
  for (let i = 0; i < bytes.length; i++) arr[i] = bytes.charCodeAt(i)
  return new File([arr], filename, { type: mime })
}

/**
 * CameraCapture component for photo capture using MediaDevices API.
 * Props:
 *   - onCapture: (dataURL: string) => void  // Called with photo as base64
 *   - onCancel?: () => void                    // Optional cancel handler
 */
export default function CameraCapture({ onCapture, onCancel }) {
  const videoRef = useRef(null)
  const canvasRef = useRef(null)
  const [stream, setStream] = useState(null)
  const [photo, setPhoto] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    startCamera()
    return () => stopCamera()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const stopCamera = () => {
    if (stream) {
      stream.getTracks().forEach((t) => t.stop())
    }
    setStream(null)
  }

  const startCamera = async () => {
    stopCamera()
    setError('')
    try {
      const s = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment' },
      })
      setStream(s)
      if (videoRef.current) videoRef.current.srcObject = s
    } catch (err) {
      setError('Tidak dapat mengakses kamera. Pastikan izin kamera diberikan dan gunakan HTTPS atau localhost.')
    }
  }

  const takePhoto = () => {
    const video = videoRef.current
    const canvas = canvasRef.current
    if (!video || !canvas || video.videoWidth === 0) return
    canvas.width = video.videoWidth
    canvas.height = video.videoHeight
    const ctx = canvas.getContext('2d')
    ctx.drawImage(video, 0, 0)
    const dataUrl = canvas.toDataURL('image/jpeg', 0.85)
    setPhoto(dataUrl)
    stopCamera()
  }

  const retake = () => {
    setPhoto(null)
    startCamera()
  }

  const confirm = () => {
    if (photo) onCapture(photo)
  }

  return (
    <div className="text-center space-y-4">
      <canvas ref={canvasRef} className="hidden" />
      {error && (
        <p className="text-red-600 text-sm flex items-center gap-2 justify-center">
          <XCircle className="w-5 h-5" />
          {error}
        </p>
      )}
      {photo ? (
        <div className="space-y-4">
          <img
            src={photo}
            alt="Bukti Pengembalian"
            className="w-full max-w-sm mx-auto h-56 object-cover object-center rounded-lg border border-slate-200"
          />
          <div className="flex flex-wrap gap-2 justify-center">
            <button
              type="button"
              onClick={retake}
              className="inline-flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg font-medium transition-colors"
            >
              <RefreshCw className="w-4 h-4" />
              Ambil Ulang
            </button>
            <button
              type="button"
              onClick={confirm}
              className="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white px-4 py-2 rounded-lg font-medium transition-colors"
            >
              <CheckCircle className="w-4 h-4" />
              Gunakan Foto
            </button>
            {onCancel && (
              <button
                type="button"
                onClick={onCancel}
                className="inline-flex items-center gap-2 bg-white border border-slate-300 text-slate-700 px-4 py-2.5 rounded-lg font-medium hover:bg-slate-50 transition-colors"
              >
                <XCircle className="w-4 h-4" />
                Batal
              </button>
            )}
          </div>
        </div>
      ) : (
        <div className="space-y-4">
          <video
            ref={videoRef}
            autoPlay
            playsInline
            muted
            className="w-full max-w-md mx-auto rounded-lg border border-slate-200 object-cover bg-black"
          />
          <div className="flex flex-wrap gap-2 justify-center">
            <button
              type="button"
              onClick={takePhoto}
              disabled={!stream}
              className="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors disabled:opacity-50"
            >
              <Camera className="w-5 h-5" />
              Ambil Foto
            </button>
            {onCancel && (
              <button
                type="button"
                onClick={onCancel}
                className="inline-flex items-center gap-2 bg-white border border-slate-300 text-slate-700 px-4 py-2.5 rounded-lg font-medium hover:bg-slate-50 transition-colors"
              >
                <XCircle className="w-4 h-4" />
                Batal
              </button>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
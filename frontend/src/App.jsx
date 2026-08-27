import { Routes, Route, Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from './context/AuthContext'
import useIdleTimeout from './hooks/useIdleTimeout'
import Layout from './components/Layout'
import Login from './pages/Login'
import ForgotPassword from './pages/ForgotPassword'
import ResetPassword from './pages/ResetPassword'
import Dashboard from './pages/Dashboard'
import Items from './pages/Items'
import ItemForm from './pages/ItemForm'
import Loans from './pages/Loans'
import LoanDetail from './pages/LoanDetail'
import NewLoan from './pages/NewLoan'
import ScanQR from './pages/ScanQR'
import Users from './pages/Users'
import Profile from './pages/Profile'
import Reports from './pages/Reports'
import OfficialLoan from './pages/OfficialLoan'

// Sesi kerja berakhir setelah 30 menit tanpa aktivitas.
const SESSION_IDLE_TIMEOUT_MS = 30 * 60 * 1000

// Memantau aktivitas pengguna dan logout otomatis setelah idle.
function IdleTimeoutHandler() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  useIdleTimeout({
    timeout: SESSION_IDLE_TIMEOUT_MS,
    enabled: !!user,
    storageKey: 'lastActivityAt',
    onTimeout: async () => {
      await logout()
      navigate('/login', { replace: true })
    },
  })

  return null
}

function ProtectedRoute({ children, roles = [] }) {
  const { user } = useAuth()
  const location = useLocation()

  if (!user) {
    return <Navigate to="/login" replace />
  }

  if (!user.email_verified && !user.email_verified_at && location.pathname !== '/profile') {
    return <Navigate to="/profile" replace />
  }

  if (roles.length > 0 && !roles.includes(user.role)) {
    return <Navigate to="/" replace />
  }

  return children
}

function App() {
  return (
    <>
      <IdleTimeoutHandler />
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/forgot-password" element={<ForgotPassword />} />
        <Route path="/reset-password" element={<ResetPassword />} />

      <Route
        path="/"
        element={
          <ProtectedRoute>
            <Layout />
          </ProtectedRoute>
        }
      >
        <Route index element={<Dashboard />} />
        <Route path="items" element={<Items />} />
        <Route
          path="items/new"
          element={
            <ProtectedRoute roles={['admin', 'assistant']}>
              <ItemForm />
            </ProtectedRoute>
          }
        />
        <Route
          path="items/:id/edit"
          element={
            <ProtectedRoute roles={['admin', 'assistant']}>
              <ItemForm />
            </ProtectedRoute>
          }
        />
        <Route path="loans" element={<Loans />} />
        <Route
          path="loans/new"
          element={
            <ProtectedRoute roles={['admin', 'assistant']}>
              <NewLoan />
            </ProtectedRoute>
          }
        />
        <Route
          path="loans/official"
          element={<ProtectedRoute roles={['admin', 'assistant']}><OfficialLoan /></ProtectedRoute>}
        />
        <Route path="loans/:id" element={<LoanDetail />} />
        <Route path="reports" element={<Reports />} />
        <Route
          path="scan"
          element={
            <ProtectedRoute roles={['admin', 'assistant']}>
              <ScanQR />
            </ProtectedRoute>
          }
        />
        <Route
          path="users"
          element={
            <ProtectedRoute roles={['admin']}>
              <Users />
            </ProtectedRoute>
          }
        />
        <Route path="profile" element={<Profile />} />
      </Route>
      </Routes>
    </>
  )
}

export default App
import { Routes, Route, Navigate } from 'react-router-dom'
import { useAuth } from './context/AuthContext'
import Layout from './components/Layout'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import Items from './pages/Items'
import ItemForm from './pages/ItemForm'
import Loans from './pages/Loans'
import LoanDetail from './pages/LoanDetail'
import NewLoan from './pages/NewLoan'
import ScanQR from './pages/ScanQR'
import Users from './pages/Users'
import Profile from './pages/Profile'

function ProtectedRoute({ children, roles = [] }) {
  const { user } = useAuth()

  if (!user) {
    return <Navigate to="/login" replace />
  }

  if (roles.length > 0 && !roles.includes(user.role)) {
    return <Navigate to="/" replace />
  }

  return children
}

function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />

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
        <Route path="loans/:id" element={<LoanDetail />} />
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
  )
}

export default App
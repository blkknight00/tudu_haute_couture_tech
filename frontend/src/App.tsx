import { HashRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import { TaskModalProvider } from './contexts/TaskModalContext';
import { ProjectFilterProvider } from './contexts/ProjectFilterContext';
import { ProjectModalProvider } from './contexts/ProjectModalContext';
import Login from './pages/Login';
import LandingPage from './pages/LandingPage';
import PricingPage from './pages/PricingPage';
import RegisterPage from './pages/RegisterPage';
import OnboardingPage from './pages/OnboardingPage';
import AdminPage from './pages/AdminPage';
import MainLayout from './layouts/MainLayout';
import Dashboard from './pages/Dashboard';
import KanbanBoard from './pages/KanbanBoard';
import Calendar from './pages/Calendar';
import Resources from './pages/Resources';
import ReminderService from './components/reminders/ReminderService';
import GradientBg from './components/ui/GradientBg';

import BackofficeLayout from './layouts/BackofficeLayout';
import BackofficeDashboard from './pages/admin/BackofficeDashboard';
import BackofficeTenants from './pages/admin/BackofficeTenants';
import BackofficeAudit from './pages/admin/BackofficeAudit';

// Smart home: si está logueado → dashboard, si no → landing
function SmartHome() {
  const { user, isLoading } = useAuth();
  if (isLoading) return null;
  return user ? <MainLayout /> : <LandingPage />;
}

// Backoffice Guard (Only SuperAdmin)
function SuperAdminRoute({ children }: { children: React.ReactNode }) {
  const { user, isLoading } = useAuth();
  if (isLoading) return null;
  if (!user || user.rol !== 'super_admin') {
    return <Navigate to="/dashboard" replace />;
  }
  return <>{children}</>;
}

function App() {
  return (
    <AuthProvider>
      <ReminderService />
      <GradientBg />
      <TaskModalProvider>
        <ProjectFilterProvider>
          <ProjectModalProvider>
            <Router>
              <Routes>
                {/* ── Rutas públicas ── */}
                <Route path="/welcome"    element={<LandingPage />} />
                <Route path="/pricing"    element={<PricingPage />} />
                <Route path="/login"      element={<Login />} />
                <Route path="/register"   element={<RegisterPage />} />
                <Route path="/register/:token" element={<RegisterPage />} />
                <Route path="/onboarding" element={<OnboardingPage />} />

                {/* ── Ruta de Prueba Antigua ── */}
                <Route path="/admin" element={<AdminPage />} />

                {/* ── 🚀 SAAS BACKOFFICE (God Mode) ── */}
                <Route path="/backoffice" element={<SuperAdminRoute><BackofficeLayout><BackofficeDashboard /></BackofficeLayout></SuperAdminRoute>} />
                <Route path="/backoffice/tenants" element={<SuperAdminRoute><BackofficeLayout><BackofficeTenants /></BackofficeLayout></SuperAdminRoute>} />
                <Route path="/backoffice/audit" element={<SuperAdminRoute><BackofficeLayout><BackofficeAudit /></BackofficeLayout></SuperAdminRoute>} />

                {/* ── App principal (ruta raíz) ── */}
                <Route path="/" element={<SmartHome />}>
                  <Route index element={<Dashboard />} />
                  <Route path="kanban"    element={<KanbanBoard />} />
                  <Route path="calendar"  element={<Calendar />} />
                  <Route path="resources" element={<Resources />} />
                </Route>

                <Route path="*" element={<Navigate to="/" replace />} />
              </Routes>
            </Router>
          </ProjectModalProvider>
        </ProjectFilterProvider>
      </TaskModalProvider>
    </AuthProvider>
  );
}

export default App;

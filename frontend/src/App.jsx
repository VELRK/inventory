import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { can, getToken, getUser } from './api';
import Layout from './components/Layout';
import Login from './pages/Login';
import ForgotPassword from './pages/ForgotPassword';
import ResetPassword from './pages/ResetPassword';
import Dashboard from './pages/Dashboard';
import Projects from './pages/Projects';
import Inventory from './pages/Inventory';
import Requests from './pages/Requests';
import Reports from './pages/Reports';
import Teams from './pages/Teams';
import UsersPage from './pages/Users';
import ActivityPage from './pages/Activity';
import SettingsPage from './pages/Settings';
import SchemaStudio from './pages/SchemaStudio';
import ApiTester from './pages/ApiTester';
import EmailTemplates from './pages/EmailTemplates';
import AccessPage from './pages/Access';

function Guard({ children }) {
  return getToken() ? children : <Navigate to="/login" replace />;
}

function CanAccess({ permission, children }) {
  return can(permission, getUser()) ? children : <Navigate to="/" replace />;
}

export default function App() {
  return (
    <BrowserRouter basename="/plots/app">
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/forgot" element={<ForgotPassword />} />
        <Route path="/reset" element={<ResetPassword />} />
        <Route path="/" element={<Guard><Layout /></Guard>}>
          <Route index element={<Dashboard />} />
          <Route path="projects" element={<Projects />} />
          <Route path="inventory" element={<Inventory />} />
          <Route path="requests" element={<Requests />} />
          <Route path="reports" element={<Reports />} />
          <Route path="teams" element={<Teams />} />
          <Route path="users" element={<UsersPage />} />
          <Route path="activity" element={<ActivityPage />} />
          <Route path="settings" element={<SettingsPage />} />
          <Route path="email-templates" element={<CanAccess permission="nav.email_templates"><EmailTemplates /></CanAccess>} />
          <Route path="access" element={<CanAccess permission="nav.access"><AccessPage /></CanAccess>} />
          <Route path="schema" element={<CanAccess permission="nav.schema"><SchemaStudio /></CanAccess>} />
          <Route path="api-tester" element={<CanAccess permission="nav.api_tester"><ApiTester /></CanAccess>} />
        </Route>
      </Routes>
    </BrowserRouter>
  );
}

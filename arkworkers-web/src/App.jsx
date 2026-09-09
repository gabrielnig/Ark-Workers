import { BrowserRouter, Routes, Route } from 'react-router-dom';
import LoginScreen from './screens/LoginScreen.jsx';
import SignUpScreen from './screens/SignUpScreen.jsx';
import AdminRequestsScreen from './screens/AdminRequestsScreen.jsx';
import MyWorkScreen from './screens/MyWorkScreen.jsx';
import TaskDetailScreen from './screens/TaskDetailScreen.jsx';
import RequireAdmin from './components/RequireAdmin.jsx';
import RequireAuth from './components/RequireAuth.jsx';

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<LoginScreen />} />
        <Route path="/signup" element={<SignUpScreen />} />
        <Route
          path="/admin/requests"
          element={
            <RequireAdmin>
              <AdminRequestsScreen />
            </RequireAdmin>
          }
        />
        <Route
          path="/my-work"
          element={
            <RequireAuth>
              <MyWorkScreen />
            </RequireAuth>
          }
        />
        <Route
          path="/my-work/:taskId"
          element={
            <RequireAuth>
              <TaskDetailScreen />
            </RequireAuth>
          }
        />
        <Route path="/" element={<RequireAuth><MyWorkScreen /></RequireAuth>} />
        <Route path="*" element={<LoginScreen />} />
      </Routes>
    </BrowserRouter>
  );
}

export default App;

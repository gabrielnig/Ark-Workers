import { BrowserRouter, Routes, Route } from 'react-router-dom';
import LoginScreen from './screens/LoginScreen.jsx';
import SignUpScreen from './screens/SignUpScreen.jsx';
import AdminRequestsScreen from './screens/AdminRequestsScreen.jsx';
import RequireAdmin from './components/RequireAdmin.jsx';

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<LoginScreen />} />
        <Route path="/signup" element={<SignUpScreen />} />
        <Route
          path="/"
          element={
            <RequireAdmin>
              <AdminRequestsScreen />
            </RequireAdmin>
          }
        />
        <Route path="*" element={<LoginScreen />} />
      </Routes>
    </BrowserRouter>
  );
}

export default App;

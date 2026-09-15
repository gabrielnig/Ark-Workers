import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import AuthLayout from '../components/AuthLayout.jsx';
import PasswordField from '../components/PasswordField.jsx';
import { login } from '../api/auth.js';

export default function LoginScreen() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState(null);
  const [pendingApproval, setPendingApproval] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(event) {
    event.preventDefault();
    setError(null);
    setPendingApproval(false);
    setSubmitting(true);

    try {
      await login(email, password);
      await queryClient.invalidateQueries({ queryKey: ['currentUser'] });
      navigate('/');
    } catch (err) {
      if (err.status === 403) {
        setPendingApproval(true);
      } else {
        setError(err.body?.message || 'Could not sign in. Check your email and password.');
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AuthLayout tagline="Laborers in the vineyard">
      <h1 className="form-heading">Sign in</h1>
      <p className="form-subheading">Welcome back.</p>

      {pendingApproval && (
        <div className="info-box">
          <span className="dot"></span>
          <p>Your account is still awaiting admin approval. You'll be able to log in once it's approved.</p>
        </div>
      )}

      {error && <p className="form-error">{error}</p>}

      <form onSubmit={handleSubmit} noValidate>
        <label className="field-label" htmlFor="login-email">Email</label>
        <input
          id="login-email"
          type="email"
          className="text-input"
          placeholder="name@church.org"
          autoComplete="username"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          required
        />

        <label className="field-label" htmlFor="login-password">Password</label>
        <PasswordField
          id="login-password"
          placeholder="Enter your password"
          autoComplete="current-password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          required
        />

        <button type="submit" className="btn-primary" disabled={submitting}>
          {submitting ? 'Signing in...' : 'Sign in'}
        </button>
      </form>

      <p className="form-footnote">
        Don't have an account? <Link to="/signup">Sign up</Link>
      </p>
    </AuthLayout>
  );
}

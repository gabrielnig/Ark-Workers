import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import AuthLayout from '../components/AuthLayout.jsx';
import Dropdown from '../components/Dropdown.jsx';
import { fetchDepartments } from '../api/departments.js';
import { submitAccountRequest } from '../api/accountRequests.js';

const MINISTRY_OFFICE_OPTIONS = [
  { value: '', label: 'None' },
  ...['Brother', 'Sister', 'Evangelist', 'Deacon', 'Deaconess', 'Pastor'].map((office) => ({
    value: office,
    label: office,
  })),
];

export default function SignUpScreen() {
  const { data: departments, isLoading: departmentsLoading } = useQuery({
    queryKey: ['departments'],
    queryFn: fetchDepartments,
  });

  const [name, setName] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [title, setTitle] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [departmentIds, setDepartmentIds] = useState([]);
  const [error, setError] = useState(null);
  const [submitting, setSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);

  function toggleDepartment(id) {
    setDepartmentIds((current) =>
      current.includes(id) ? current.filter((d) => d !== id) : [...current, id]
    );
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setError(null);

    if (departmentIds.length === 0) {
      setError('Select at least one department.');
      return;
    }

    if (password.length < 12) {
      setError('Password must be at least 12 characters.');
      return;
    }

    if (password !== passwordConfirmation) {
      setError('Passwords do not match.');
      return;
    }

    setSubmitting(true);
    try {
      await submitAccountRequest({
        name,
        displayName,
        title,
        email,
        phone,
        password,
        passwordConfirmation,
        departmentIds,
      });
      setSubmitted(true);
    } catch (err) {
      setError(err.body?.message || 'Could not create your account.');
    } finally {
      setSubmitting(false);
    }
  }

  if (submitted) {
    return (
      <AuthLayout tagline="Laborers in the vineyard">
        <h1 className="form-heading">Account created</h1>
        <p className="form-subheading">
          Your admin will review it. You'll be able to log in with the email and
          password you just set, once approved.
        </p>
        <p className="form-footnote">
          <Link to="/login">Back to sign in</Link>
        </p>
      </AuthLayout>
    );
  }

  return (
    <AuthLayout tagline="Laborers in the vineyard">
      <h1 className="form-heading">Sign up</h1>

      <div className="info-box">
        <span className="dot"></span>
        <p>
          Your account needs admin approval before you can log in. Set your
          password now, you'll use it once approved.
        </p>
      </div>

      {error && <p className="form-error">{error}</p>}

      <form onSubmit={handleSubmit} noValidate>
        <label className="field-label" htmlFor="signup-name">Full name</label>
        <input
          id="signup-name"
          type="text"
          className="text-input"
          placeholder="Full name or baptismal name, if that's what you go by"
          value={name}
          onChange={(e) => setName(e.target.value)}
          required
        />

        <label className="field-label" htmlFor="signup-display-name">Display name (optional)</label>
        <input
          id="signup-display-name"
          type="text"
          className="text-input"
          placeholder="What you'd like to be called in the app"
          value={displayName}
          onChange={(e) => setDisplayName(e.target.value)}
        />

        <label className="field-label" htmlFor="signup-title">Ministry office (optional)</label>
        <Dropdown
          id="signup-title"
          value={title}
          onChange={setTitle}
          options={MINISTRY_OFFICE_OPTIONS}
          placeholder="None"
        />

        <label className="field-label" htmlFor="signup-email">Email</label>
        <input
          id="signup-email"
          type="email"
          className="text-input"
          placeholder="name@church.org"
          autoComplete="username"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          required
        />

        <label className="field-label" htmlFor="signup-phone">Phone</label>
        <input
          id="signup-phone"
          type="tel"
          className="text-input"
          placeholder="+234 800 000 0000"
          value={phone}
          onChange={(e) => setPhone(e.target.value)}
          required
        />

        <label className="field-label" htmlFor="signup-password">Password</label>
        <input
          id="signup-password"
          type="password"
          className="text-input"
          placeholder="At least 12 characters"
          autoComplete="new-password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          required
        />

        <label className="field-label" htmlFor="signup-password-confirmation">Confirm password</label>
        <input
          id="signup-password-confirmation"
          type="password"
          className="text-input"
          placeholder="Type your password again"
          autoComplete="new-password"
          value={passwordConfirmation}
          onChange={(e) => setPasswordConfirmation(e.target.value)}
          required
        />

        <label className="field-label">Departments</label>
        <p className="form-subheading" style={{ marginBottom: 8 }}>Select all that apply.</p>
        <div className="dept-checklist">
          {departmentsLoading && <p className="form-subheading">Loading departments...</p>}
          {departments?.map((department) => (
            <label key={department.id} className="dept-check-row">
              <input
                type="checkbox"
                checked={departmentIds.includes(department.id)}
                onChange={() => toggleDepartment(department.id)}
              />
              <span>{department.name}</span>
            </label>
          ))}
        </div>

        <button type="submit" className="btn-primary" disabled={submitting}>
          {submitting ? 'Creating account...' : 'Create account'}
        </button>
      </form>

      <p className="form-footnote">
        Already have an account? <Link to="/login">Sign in</Link>
      </p>
    </AuthLayout>
  );
}

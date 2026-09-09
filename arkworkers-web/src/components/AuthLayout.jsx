import './AuthLayout.css';

export default function AuthLayout({ tagline, children }) {
  return (
    <div className="auth-layout">
      <div className="auth-brand-col">
        <img src="/images/logo-icon.png" alt="ArkWorkers" className="auth-brand-icon" />
        <p className="auth-brand-name">ArkWorkers</p>
        <p className="auth-brand-tagline">{tagline}</p>
      </div>
      <div className="auth-form-col">
        <div className="auth-form-inner">{children}</div>
      </div>
    </div>
  );
}

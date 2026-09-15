import { useState } from 'react';
import './PasswordField.css';

export default function PasswordField({ id, value, onChange, placeholder, autoComplete, required }) {
  const [visible, setVisible] = useState(false);

  return (
    <div className="password-field">
      <input
        id={id}
        type={visible ? 'text' : 'password'}
        className="text-input"
        placeholder={placeholder}
        autoComplete={autoComplete}
        value={value}
        onChange={onChange}
        required={required}
      />
      <button
        type="button"
        className="password-toggle"
        onClick={() => setVisible((v) => !v)}
        aria-label={visible ? 'Hide password' : 'Show password'}
      >
        <span aria-hidden="true">&#128065;</span> {visible ? 'Hide' : 'Show'}
      </button>
    </div>
  );
}

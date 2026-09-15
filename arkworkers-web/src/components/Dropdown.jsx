import { useEffect, useRef, useState } from 'react';
import './Dropdown.css';

/**
 * A fully custom single-select dropdown, deliberately not a native
 * <select>. iOS Safari renders native selects using OS chrome that
 * ignores most of our CSS (height/padding in particular), which can
 * make the control look broken or fail to open reliably depending on
 * device and browser. This renders and controls every pixel
 * ourselves instead.
 */
export default function Dropdown({ id, value, onChange, options, placeholder = 'Select' }) {
  const [open, setOpen] = useState(false);
  const rootRef = useRef(null);

  useEffect(() => {
    if (!open) return;

    function handleOutsideClick(event) {
      if (rootRef.current && !rootRef.current.contains(event.target)) {
        setOpen(false);
      }
    }

    function handleEscape(event) {
      if (event.key === 'Escape') setOpen(false);
    }

    document.addEventListener('pointerdown', handleOutsideClick);
    document.addEventListener('keydown', handleEscape);
    return () => {
      document.removeEventListener('pointerdown', handleOutsideClick);
      document.removeEventListener('keydown', handleEscape);
    };
  }, [open]);

  const selectedLabel = options.find((o) => o.value === value)?.label;

  function selectOption(optionValue) {
    onChange(optionValue);
    setOpen(false);
  }

  return (
    <div className="dropdown" ref={rootRef}>
      <button
        type="button"
        id={id}
        className="dropdown-trigger"
        aria-haspopup="listbox"
        aria-expanded={open}
        onClick={() => setOpen((current) => !current)}
      >
        <span className={selectedLabel ? '' : 'dropdown-placeholder'}>
          {selectedLabel || placeholder}
        </span>
        <span className="dropdown-chevron" aria-hidden="true">&#9662;</span>
      </button>

      {open && (
        <ul className="dropdown-list" role="listbox">
          {options.map((option) => (
            <li key={option.value || '__empty'} role="option" aria-selected={option.value === value}>
              <button
                type="button"
                className="dropdown-option"
                onClick={() => selectOption(option.value)}
              >
                {option.label}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

import { useState } from 'react';
import AppShell from '../components/AppShell.jsx';
import { useAssetTypes, useCreateAssetType } from '../hooks/useAssetTypes.js';
import { imageForAssetType, KNOWN_CATEGORIES } from '../lib/assetTypeImages.js';
import './AssetTypesScreen.css';

/**
 * Lets an admin (or a grants_management manager) add a new Asset Type
 * with no code change, per PRD's "fully modular" requirement. Category
 * is a dropdown of the small curated list assetTypeImages.js already
 * matches against, plus an "Other" free-text fallback, so the type
 * still works correctly (falls back to the default image) even for a
 * category outside the curated set.
 */
export default function AssetTypesScreen() {
  const { data: assetTypes, isLoading } = useAssetTypes();
  const createAssetType = useCreateAssetType();

  const [showForm, setShowForm] = useState(false);
  const [name, setName] = useState('');
  const [category, setCategory] = useState('');
  const [customCategory, setCustomCategory] = useState('');

  function handleSubmit(e) {
    e.preventDefault();
    if (!name.trim()) return;
    const resolvedCategory = category === '__other__' ? customCategory.trim() : category;
    createAssetType.mutate(
      { name: name.trim(), category: resolvedCategory },
      {
        onSuccess: () => {
          setName('');
          setCategory('');
          setCustomCategory('');
          setShowForm(false);
        },
      }
    );
  }

  return (
    <AppShell>
      <div className="asset-types-screen">
        <div className="asset-types-header">
          <div>
            <h1 className="asset-types-title">Asset Types</h1>
            <p className="asset-types-sub">
              The categories an asset can be, e.g. AC Unit, Vehicle, Pool. New
              types can be added here any time, no code change needed.
            </p>
          </div>
          {!showForm && (
            <button className="btn-add-space" onClick={() => setShowForm(true)}>
              + Add an Asset Type
            </button>
          )}
        </div>

        {showForm && (
          <form className="add-space-form" onSubmit={handleSubmit}>
            <label className="field-label" htmlFor="new-type-name">Name</label>
            <input
              id="new-type-name"
              className="text-input"
              type="text"
              placeholder="e.g. Generator, Projector, Piano"
              value={name}
              onChange={(e) => setName(e.target.value)}
              autoFocus
            />

            <label className="field-label" htmlFor="new-type-category">Category (for photo matching)</label>
            <select
              id="new-type-category"
              className="text-input"
              value={category}
              onChange={(e) => setCategory(e.target.value)}
            >
              <option value="">None</option>
              {KNOWN_CATEGORIES.map((c) => (
                <option key={c.value} value={c.value}>{c.label}</option>
              ))}
              <option value="__other__">Other (type below)</option>
            </select>

            {category === '__other__' && (
              <input
                className="text-input"
                type="text"
                placeholder="Custom category"
                value={customCategory}
                onChange={(e) => setCustomCategory(e.target.value)}
              />
            )}

            {createAssetType.isError && (
              <div className="form-error">
                {createAssetType.error?.body?.message || 'Could not create the asset type. Please try again.'}
              </div>
            )}

            <div className="add-space-actions">
              <button type="submit" className="btn-primary" disabled={createAssetType.isPending || !name.trim()}>
                {createAssetType.isPending ? 'Creating...' : 'Create'}
              </button>
              <button
                type="button"
                className="btn-secondary"
                onClick={() => {
                  setShowForm(false);
                  setName('');
                  setCategory('');
                  setCustomCategory('');
                }}
              >
                Cancel
              </button>
            </div>
          </form>
        )}

        {isLoading && <p className="asset-types-sub">Loading...</p>}

        {!isLoading && (!assetTypes || assetTypes.length === 0) && (
          <div className="empty-state">
            <h3>No asset types yet</h3>
            <p>Add the first one above to start creating assets.</p>
          </div>
        )}

        {!isLoading && assetTypes?.length > 0 && (
          <div className="asset-type-list">
            {assetTypes.map((type) => (
              <div key={type.id} className="asset-type-row">
                <div className="asset-thumb">
                  <img src={imageForAssetType(type.category)} alt="" />
                </div>
                <div className="asset-type-info">
                  <h4>{type.name}</h4>
                  {type.category && <span className="asset-type-category">{type.category}</span>}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </AppShell>
  );
}

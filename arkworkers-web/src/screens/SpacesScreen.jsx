import { useMemo, useState } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import AppShell from '../components/AppShell.jsx';
import { useSpaces, useCreateSpace } from '../hooks/useSpaces.js';
import { useAssets, useCreateAsset } from '../hooks/useAssets.js';
import { useAssetTypes, useCreateAssetType } from '../hooks/useAssetTypes.js';
import { useCurrentUser } from '../hooks/useCurrentUser.js';
import { imageForAssetType, DEFAULT_SPACE_IMAGE, KNOWN_CATEGORIES } from '../lib/assetTypeImages.js';
import './SpacesScreen.css';

/**
 * Space -> Asset drill-down per DESIGN-SYSTEM.md §5: breadcrumb-style
 * back nav (not a modal stack), restricted badge visible at every
 * level, not just on entry. currentSpaceId comes from the URL, not
 * component state, so back/forward and a direct link both work.
 */
export default function SpacesScreen() {
  const { spaceId } = useParams();
  const navigate = useNavigate();
  const currentSpaceId = spaceId ? Number(spaceId) : null;

  const { data: spaces, isLoading: spacesLoading } = useSpaces();
  const { data: assets, isLoading: assetsLoading } = useAssets();
  const { data: assetTypes } = useAssetTypes();
  const { data: currentUser } = useCurrentUser();
  const createSpace = useCreateSpace();
  const createAsset = useCreateAsset();
  const createAssetType = useCreateAssetType();

  const [showForm, setShowForm] = useState(false);
  const [name, setName] = useState('');
  const [isRestricted, setIsRestricted] = useState(false);

  const [showAssetForm, setShowAssetForm] = useState(false);
  const [assetName, setAssetName] = useState('');
  const [assetTypeId, setAssetTypeId] = useState('');
  const [showQuickType, setShowQuickType] = useState(false);
  const [quickTypeName, setQuickTypeName] = useState('');
  const [quickTypeCategory, setQuickTypeCategory] = useState('');

  const currentSpace = useMemo(
    () => spaces?.find((s) => s.id === currentSpaceId) ?? null,
    [spaces, currentSpaceId]
  );

  const childSpaces = useMemo(
    () => (spaces ?? []).filter((s) => s.parent_space_id === currentSpaceId),
    [spaces, currentSpaceId]
  );

  const spaceAssets = useMemo(
    () => (assets ?? []).filter((a) => a.space_id === currentSpaceId),
    [assets, currentSpaceId]
  );

  const breadcrumb = useMemo(() => {
    if (!currentSpace) return [];
    const trail = [];
    let node = currentSpace;
    while (node) {
      trail.unshift(node);
      node = spaces?.find((s) => s.id === node.parent_space_id) ?? null;
    }
    return trail;
  }, [currentSpace, spaces]);

  const spaceCounts = useMemo(() => {
    const counts = {};
    (spaces ?? []).forEach((s) => {
      counts[s.id] = { subSpaces: 0, assets: 0 };
    });
    (spaces ?? []).forEach((s) => {
      if (s.parent_space_id != null && counts[s.parent_space_id]) {
        counts[s.parent_space_id].subSpaces += 1;
      }
    });
    (assets ?? []).forEach((a) => {
      if (counts[a.space_id]) {
        counts[a.space_id].assets += 1;
      }
    });
    return counts;
  }, [spaces, assets]);

  const isLoading = spacesLoading || assetsLoading;

  const existingAssetNames = useMemo(
    () => Array.from(new Set((assets ?? []).map((a) => a.name))).sort(),
    [assets]
  );

  function handleCreateSpace(e) {
    e.preventDefault();
    if (!name.trim()) return;
    createSpace.mutate(
      { name: name.trim(), parent_space_id: currentSpaceId, is_restricted: isRestricted },
      {
        onSuccess: () => {
          setName('');
          setIsRestricted(false);
          setShowForm(false);
        },
      }
    );
  }

  function handleCreateAsset(e) {
    e.preventDefault();
    if (!assetName.trim() || !assetTypeId || !currentSpaceId) return;
    createAsset.mutate(
      { name: assetName.trim(), asset_type_id: Number(assetTypeId), space_id: currentSpaceId },
      {
        onSuccess: () => {
          setAssetName('');
          setAssetTypeId('');
          setShowAssetForm(false);
        },
      }
    );
  }

  function handleQuickCreateType(e) {
    e.preventDefault();
    if (!quickTypeName.trim()) return;
    createAssetType.mutate(
      { name: quickTypeName.trim(), category: quickTypeCategory },
      {
        onSuccess: (newType) => {
          setAssetTypeId(String(newType.id));
          setQuickTypeName('');
          setQuickTypeCategory('');
          setShowQuickType(false);
        },
      }
    );
  }

  return (
    <AppShell>
      <div className="spaces-screen">
        {breadcrumb.length > 0 && (
          <div className="spaces-breadcrumb">
            <span className="crumb" onClick={() => navigate('/spaces')}>Spaces</span>
            {breadcrumb.map((node, i) => (
              <span key={node.id}>
                <span className="sep">/</span>
                {i === breadcrumb.length - 1 ? (
                  <span className="current">{node.name}</span>
                ) : (
                  <span className="crumb" onClick={() => navigate(`/spaces/${node.id}`)}>{node.name}</span>
                )}
              </span>
            ))}
          </div>
        )}

        <div className="spaces-header-row">
          <div>
            <h1 className="spaces-title">
              {currentSpace ? currentSpace.name : 'Spaces'}
              {currentSpace?.is_restricted && <span className="plum-badge">Restricted</span>}
            </h1>
            <p className="spaces-sub">
              {currentSpace
                ? 'Only staff with an explicit access grant can view this space\u2019s assets and tasks.'
                : 'Everything the church manages, organized by physical location.'}
            </p>
          </div>

          {/* can_manage mirrors SpacePolicy::create() exactly (admin OR a
              grants_management department role) - not just is_admin, so a
              manager sees this too, not only an admin. */}
          {currentUser?.can_manage && !showForm && !showAssetForm && (
            <div className="spaces-header-actions">
              <button className="btn-add-space" onClick={() => setShowForm(true)}>
                + Add a Space
              </button>
              {/* Assets require a real space_id, no top-level "unassigned"
                  bucket in the schema, so this only makes sense once
                  you're actually inside a space. */}
              {currentSpace && (
                <button className="btn-add-space secondary" onClick={() => setShowAssetForm(true)}>
                  + Add an Asset
                </button>
              )}
            </div>
          )}
        </div>

        {showAssetForm && (
          <form className="add-space-form" onSubmit={handleCreateAsset}>
            <label className="field-label" htmlFor="new-asset-name">
              New asset in {currentSpace?.name}
            </label>
            <input
              id="new-asset-name"
              className="text-input"
              type="text"
              list="existing-asset-names"
              placeholder="Asset name (e.g. AC Unit - Wall Mount)"
              value={assetName}
              onChange={(e) => setAssetName(e.target.value)}
              autoFocus
            />
            <datalist id="existing-asset-names">
              {existingAssetNames.map((n) => (
                <option key={n} value={n} />
              ))}
            </datalist>

            {assetTypes?.length > 0 && !showQuickType && (
              <select
                className="text-input"
                value={assetTypeId}
                onChange={(e) => {
                  if (e.target.value === '__new__') {
                    setShowQuickType(true);
                    return;
                  }
                  setAssetTypeId(e.target.value);
                }}
              >
                <option value="">Select an asset type...</option>
                {assetTypes.map((t) => (
                  <option key={t.id} value={t.id}>{t.name}</option>
                ))}
                <option value="__new__">+ New asset type...</option>
              </select>
            )}

            {(!assetTypes?.length || showQuickType) && (
              <div className="quick-type-panel">
                <label className="field-label" htmlFor="quick-type-name">
                  {assetTypes?.length ? 'New asset type' : 'No asset types exist yet, add the first one'}
                </label>
                <input
                  id="quick-type-name"
                  className="text-input"
                  type="text"
                  placeholder="e.g. Generator, Projector, Piano"
                  value={quickTypeName}
                  onChange={(e) => setQuickTypeName(e.target.value)}
                />
                <select
                  className="text-input"
                  value={quickTypeCategory}
                  onChange={(e) => setQuickTypeCategory(e.target.value)}
                >
                  <option value="">Category: none</option>
                  {KNOWN_CATEGORIES.map((c) => (
                    <option key={c.value} value={c.value}>{c.label}</option>
                  ))}
                </select>

                {createAssetType.isError && (
                  <div className="form-error">
                    {createAssetType.error?.body?.message || 'Could not create the asset type. Please try again.'}
                  </div>
                )}

                <div className="add-space-actions">
                  <button
                    type="button"
                    className="btn-primary"
                    onClick={handleQuickCreateType}
                    disabled={createAssetType.isPending || !quickTypeName.trim()}
                  >
                    {createAssetType.isPending ? 'Adding...' : 'Add type'}
                  </button>
                  {assetTypes?.length > 0 && (
                    <button type="button" className="btn-secondary" onClick={() => setShowQuickType(false)}>
                      Cancel
                    </button>
                  )}
                </div>
              </div>
            )}

            <Link to="/admin/asset-types" className="manage-types-link">Manage all asset types</Link>

            {createAsset.isError && (
              <div className="form-error">
                {createAsset.error?.body?.message || 'Could not create the asset. Please try again.'}
              </div>
            )}

            <div className="add-space-actions">
              <button
                type="submit"
                className="btn-primary"
                disabled={createAsset.isPending || !assetName.trim() || !assetTypeId}
              >
                {createAsset.isPending ? 'Creating...' : 'Create'}
              </button>
              <button
                type="button"
                className="btn-secondary"
                onClick={() => {
                  setShowAssetForm(false);
                  setAssetName('');
                  setAssetTypeId('');
                  setShowQuickType(false);
                }}
              >
                Cancel
              </button>
            </div>
          </form>
        )}

        {showForm && (
          <form className="add-space-form" onSubmit={handleCreateSpace}>
            <label className="field-label" htmlFor="new-space-name">
              {currentSpace ? `New sub-space of ${currentSpace.name}` : 'New top-level Space'}
            </label>
            <input
              id="new-space-name"
              className="text-input"
              type="text"
              placeholder="Space name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              autoFocus
            />
            <label className="dept-check-row" style={{ padding: '0 0 14px' }}>
              <input
                type="checkbox"
                checked={isRestricted}
                onChange={(e) => setIsRestricted(e.target.checked)}
              />
              Restricted (only staff with an explicit access grant can see it)
            </label>

            {createSpace.isError && (
              <div className="form-error">
                {createSpace.error?.body?.message || 'Could not create the space. Please try again.'}
              </div>
            )}

            <div className="add-space-actions">
              <button type="submit" className="btn-primary" disabled={createSpace.isPending || !name.trim()}>
                {createSpace.isPending ? 'Creating\u2026' : 'Create'}
              </button>
              <button
                type="button"
                className="btn-secondary"
                onClick={() => {
                  setShowForm(false);
                  setName('');
                  setIsRestricted(false);
                }}
              >
                Cancel
              </button>
            </div>
          </form>
        )}

        {isLoading && (
          <div className="space-grid" aria-hidden="true">
            {[0, 1, 2].map((i) => (
              <div key={i} className="space-card skeleton-card">
                <div className="space-thumb skeleton-block" />
                <div className="skeleton-block" style={{ width: '60%', height: 14, marginTop: 10 }} />
              </div>
            ))}
          </div>
        )}

        {!isLoading && childSpaces.length > 0 && (
          <>
            <div className="section-label">Sub-spaces</div>
            <div className="space-grid">
              {childSpaces.map((space) => {
                const counts = spaceCounts[space.id] ?? { subSpaces: 0, assets: 0 };
                return (
                  <div key={space.id} className="space-card" onClick={() => navigate(`/spaces/${space.id}`)}>
                    <div className="space-thumb">
                      <img src={DEFAULT_SPACE_IMAGE} alt="" />
                    </div>
                    <div className="space-card-top">
                      <h3>{space.name}</h3>
                      {space.is_restricted && <span className="plum-badge small">Restricted</span>}
                    </div>
                    <div className="space-card-counts">
                      {counts.subSpaces} sub-space{counts.subSpaces === 1 ? '' : 's'} · {counts.assets} asset{counts.assets === 1 ? '' : 's'}
                    </div>
                  </div>
                );
              })}
            </div>
          </>
        )}

        {!isLoading && (
          <>
            <div className="section-label">
              {currentSpace ? 'Assets directly in this space' : 'Assets not yet in a sub-space'}
            </div>
            {spaceAssets.length > 0 ? (
              <div className="asset-list">
                {spaceAssets.map((asset) => (
                  // Not a link yet: there's no Asset detail screen built
                  // (next item in the build plan after this one). A
                  // plain row now, not a dead /assets/:id link.
                  <div key={asset.id} className="asset-row">
                    <div className="asset-thumb">
                      <img src={imageForAssetType(asset.asset_type?.category)} alt="" />
                    </div>
                    <div className="asset-info">
                      <h4>{asset.name}</h4>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="empty-state">
                <h3>No assets here yet</h3>
                <p>Assets added to this space will show up here.</p>
              </div>
            )}
          </>
        )}
      </div>
    </AppShell>
  );
}

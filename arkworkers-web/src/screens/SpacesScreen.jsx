import { useMemo } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import AppShell from '../components/AppShell.jsx';
import { useSpaces } from '../hooks/useSpaces.js';
import { useAssets } from '../hooks/useAssets.js';
import { imageForAssetType, DEFAULT_SPACE_IMAGE } from '../lib/assetTypeImages.js';
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

  const isLoading = spacesLoading || assetsLoading;

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

        <h1 className="spaces-title">
          {currentSpace ? currentSpace.name : 'Spaces'}
          {currentSpace?.is_restricted && <span className="plum-badge">Restricted</span>}
        </h1>
        <p className="spaces-sub">
          {currentSpace
            ? 'Only staff with an explicit access grant can view this space\u2019s assets and tasks.'
            : 'Everything the church manages, organized by physical location.'}
        </p>

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
              {childSpaces.map((space) => (
                <div key={space.id} className="space-card" onClick={() => navigate(`/spaces/${space.id}`)}>
                  <div className="space-thumb">
                    <img src={DEFAULT_SPACE_IMAGE} alt="" />
                  </div>
                  <div className="space-card-top">
                    <h3>{space.name}</h3>
                    {space.is_restricted && <span className="plum-badge small">Restricted</span>}
                  </div>
                </div>
              ))}
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

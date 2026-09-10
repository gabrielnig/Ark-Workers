/**
 * Maps AssetType.category (a free-text field an admin can set to
 * anything, since asset types are fully modular per PRD's "no code
 * changes" requirement) to a representative photo.
 *
 * This is a stopgap, not a real icon system. The actual icon-set
 * decision is still open (DESIGN-SYSTEM.md §8, BUILD-PLAN.md Phase 5).
 * A brand-new AssetType category that isn't in this list still works
 * correctly, it just falls back to DEFAULT_ASSET_IMAGE rather than
 * breaking. Nothing here blocks an admin from creating a new asset
 * type today.
 *
 * Images are real, openly licensed photos originally sourced from
 * Wikimedia Commons (CC-BY-SA), downloaded once and served locally
 * from /images/asset-types/ rather than hotlinked. The site's CSP
 * (img-src 'self' data: blob:, see SECURITY.md) blocks loading images
 * from any external domain, on purpose, so a hotlinked Wikimedia URL
 * silently fails to load. Bundling the files locally is the actual
 * fix, not loosening that header, since the header is a deliberate
 * security control, not an oversight.
 */

const asset = (filename) => `/images/asset-types/${filename}`;

export const DEFAULT_ASSET_IMAGE = asset('asset-default.jpg');
export const DEFAULT_SPACE_IMAGE = asset('space-default.jpg');

const CATEGORY_IMAGES = {
  hvac: asset('hvac.jpg'),
  ac: asset('hvac.jpg'),
  audio: asset('audio.jpg'),
  sound: asset('audio.jpg'),
  vehicle: asset('vehicle.jpg'),
  furniture: asset('furniture.jpg'),
};

/**
 * Curated dropdown options for the Asset Type creation form, kept in
 * this file (not duplicated in the screen component) so the labels
 * shown to an admin and the keys imageForAssetType actually matches
 * against can never drift apart.
 */
export const KNOWN_CATEGORIES = [
  { value: 'HVAC', label: 'HVAC / air conditioning' },
  { value: 'Audio', label: 'Audio / sound equipment' },
  { value: 'Vehicle', label: 'Vehicle' },
  { value: 'Furniture', label: 'Furniture' },
];

export function imageForAssetType(category) {
  if (!category) return DEFAULT_ASSET_IMAGE;

  const normalized = category.toLowerCase();
  const match = Object.keys(CATEGORY_IMAGES).find((key) => normalized.includes(key));

  return match ? CATEGORY_IMAGES[match] : DEFAULT_ASSET_IMAGE;
}

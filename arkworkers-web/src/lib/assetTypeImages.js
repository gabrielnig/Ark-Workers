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
 * Images are real, openly licensed photos on Wikimedia Commons
 * (CC-BY-SA), chosen once and fixed here, not a random keyword feed,
 * so the same category always shows the same image.
 */

const wm = (filename, width = 150) =>
  `https://commons.wikimedia.org/wiki/Special:FilePath/File:${filename.replaceAll(' ', '_')}?width=${width}`;

export const DEFAULT_ASSET_IMAGE = wm('Empty classroom.jpg');
export const DEFAULT_SPACE_IMAGE = wm('Cades Cove Missionary Baptist Church - October 2023 - Sarah Stierch 04.jpg', 200);

const CATEGORY_IMAGES = {
  hvac: wm('Wall mount air conditioner.jpg'),
  ac: wm('Wall mount air conditioner.jpg'),
  audio: wm('ADT Mixing Console.jpg'),
  sound: wm('ADT Mixing Console.jpg'),
  vehicle: wm('Copenhagen parking triangle.jpg'),
  furniture: wm('Office with desk and chair at the Physiology Department Wellcome L0022518.jpg'),
};

export function imageForAssetType(category) {
  if (!category) return DEFAULT_ASSET_IMAGE;

  const normalized = category.toLowerCase();
  const match = Object.keys(CATEGORY_IMAGES).find((key) => normalized.includes(key));

  return match ? CATEGORY_IMAGES[match] : DEFAULT_ASSET_IMAGE;
}

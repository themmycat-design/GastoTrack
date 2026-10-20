const TYPES = {'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp'};
export const MAX_PRODUCT_PHOTO_BYTES = 5 * 1024 * 1024;

export const productPhotoUpload = asset => {
  const reject = message => {
    const error = new Error(message);
    error.code = 'PRODUCT_PHOTO_INVALID';
    throw error;
  };
  if (!asset?.uri) reject('The selected photo could not be read. Choose it again.');
  if (asset.fileSize > MAX_PRODUCT_PHOTO_BYTES) {
    reject('This photo is larger than 5 MB. Choose a smaller photo.');
  }
  const extension = (asset.fileName || asset.uri).split('?')[0].split('.').pop().toLowerCase();
  const inferredType = {jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp'}[extension];
  const suppliedType = asset.type?.toLowerCase();
  const type = suppliedType === 'image/jpg' ? 'image/jpeg' : suppliedType || inferredType;
  if (!TYPES[type]) reject('Choose a JPG, PNG, or WebP photo. This image format is not supported.');
  // Use the actual MIME type, not the original name of a converted HEIC photo.
  return {uri: asset.uri, type, name: 'product-photo.' + TYPES[type]};
};

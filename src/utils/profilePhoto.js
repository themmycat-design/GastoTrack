import {API_URL} from '../services/api';

const PHOTO_TYPES = {
  'image/jpeg': 'jpg',
  'image/png': 'png',
  'image/webp': 'webp',
};
export const MAX_PROFILE_PHOTO_BYTES = 5 * 1024 * 1024;

export const profilePhotoUpload = asset => {
  const reject = message => {
    const error = new Error(message);
    error.code = 'PROFILE_PHOTO_INVALID';
    throw error;
  };

  if (!asset?.uri) reject('The selected photo could not be read. Choose it again.');
  if (asset.fileSize && asset.fileSize > MAX_PROFILE_PHOTO_BYTES) {
    reject('This photo is larger than 5 MB. Choose a smaller photo.');
  }

  const extension = (asset.fileName || asset.uri).split('?')[0].split('.').pop().toLowerCase();
  const inferredType = {jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp'}[extension];
  const suppliedType = asset.type?.toLowerCase();
  const type = suppliedType === 'image/jpg' ? 'image/jpeg' : suppliedType || inferredType;
  if (!PHOTO_TYPES[type]) reject('Choose a JPG, PNG, or WebP photo. This image format is not supported.');

  return {uri: asset.uri, type, name: 'profile-photo.' + PHOTO_TYPES[type]};
};

export const getProfilePhotoSource = (user, token) => {
  if (!user?.has_profile_photo || !token || !API_URL) return null;

  return {
    uri: `${API_URL}/user/profile-photo?v=${encodeURIComponent(user.profile_photo_version || '')}`,
    headers: {Authorization: `Bearer ${token}`},
  };
};

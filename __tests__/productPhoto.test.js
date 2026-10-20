import {productPhotoUpload} from '../src/utils/productPhoto';

test('uses the converted MIME type for a photo filename', () => {
  expect(productPhotoUpload({uri: 'file:///photo.jpg', type: 'image/jpeg', fileName: 'original.heic'}))
    .toEqual({uri: 'file:///photo.jpg', type: 'image/jpeg', name: 'product-photo.jpg'});
});

test('infers supported formats when the picker omits MIME metadata', () => {
  expect(productPhotoUpload({uri: 'file:///photo.PNG'}).type).toBe('image/png');
});

test('rejects oversized and unsupported photos before sending a request', () => {
  expect(() => productPhotoUpload({uri: 'file:///large.png', fileSize: 5 * 1024 * 1024 + 1})).toThrow('larger than 5 MB');
  expect(() => productPhotoUpload({uri: 'file:///photo.heic', type: 'image/heic'})).toThrow('not supported');
  expect(() => productPhotoUpload({})).toThrow('could not be read');
});

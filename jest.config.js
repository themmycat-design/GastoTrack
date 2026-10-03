module.exports = {
  preset: '@react-native/jest-preset',
  testPathIgnorePatterns: ['/node_modules/', '/web/'],
  setupFiles: ['<rootDir>/jest.setup.js'],
  transformIgnorePatterns: [
    'node_modules/(?!(@react-native|react-native|@react-navigation|react-native-vector-icons|@react-native-async-storage)/)',
  ],
};

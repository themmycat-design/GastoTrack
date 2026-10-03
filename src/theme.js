export const COLORS = {
  // Background
  bgDark: '#00C897',
  bgCard: '#FFFFFF',
  bgSection: '#FFFFFF',

  // Accent
  accent: '#00C897',
  accentDark: '#00A87E',

  // Text
  textWhite: '#FFFFFF',
  textDark: '#0A2E2A',
  textGray: '#888888',
  textMuted: '#BBBBBB',

  // Transaction
  income: '#00A87E',
  expense: '#E53935',

  // Tags
  tagIncBg: '#E8F5E9',
  tagIncText: '#2E7D32',
  tagExpBg: '#FFF3E0',
  tagExpText: '#E65100',

  // Cards
  miniCardBg: '#F5FBF9',
  toggleBg: '#F0F0F0',
  divider: '#F0F0F0',

  // Nav
  navActive: '#00C897',
  navInactive: '#BBBBBB',

  // Semantic surfaces
  background: '#F4F8F7',
  surface: '#FFFFFF',
  surfaceMuted: '#ECF7F3',
  border: '#DDE9E5',
  warning: '#F59E0B',
  danger: '#DC4C4C',
  success: '#138A68',
};

export const SPACING = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 20,
  xxl: 24,
};

export const RADIUS = {
  sm: 8,
  md: 12,
  lg: 18,
  pill: 999,
};

export const SHADOWS = {
  card: {
    shadowColor: '#0A2E2A',
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.08,
    shadowRadius: 8,
    elevation: 2,
  },
};

// Font configuration
// To use custom fonts:
// 1. Add .ttf font files to assets/fonts/ folder
// 2. Create react-native.config.js with font asset configuration
// 3. Run: npx react-native-asset
// 4. Rebuild the app

export const FONTS = {
  // Titles - Poppins SemiBold, 20px
  title: {
    fontFamily: 'Poppins-SemiBold', // Will use System font until custom fonts are added
    fontSize: 20,
    fontWeight: '600',
  },
  
  // Subtitles - Poppins Medium, 15px
  subtitle: {
    fontFamily: 'Poppins-Medium',
    fontSize: 15,
    fontWeight: '500',
  },
  
  // Paragraph - Poppins Light, 13px
  paragraph: {
    fontFamily: 'Poppins-Light',
    fontSize: 13,
    fontWeight: '300',
  },
  
  // Subtext - League Spartan Regular, 14px
  subtext: {
    fontFamily: 'LeagueSpartan-Regular',
    fontSize: 14,
    fontWeight: '400',
  },
};

// System fallback fonts (use these until custom fonts are installed)
export const FONTS_SYSTEM = {
  title: {
    fontSize: 20,
    fontWeight: '600',
  },
  subtitle: {
    fontSize: 15,
    fontWeight: '500',
  },
  paragraph: {
    fontSize: 13,
    fontWeight: '300',
  },
  subtext: {
    fontSize: 14,
    fontWeight: '400',
  },
};

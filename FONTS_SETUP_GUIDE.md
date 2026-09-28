# Custom Fonts Setup Guide for GastoTrack

## Required Fonts

### Poppins Font Family
- **Poppins-Light.ttf** (Weight: 300) - For paragraphs (13px)
- **Poppins-Medium.ttf** (Weight: 500) - For subtitles (15px)
- **Poppins-SemiBold.ttf** (Weight: 600) - For titles (20px)

### League Spartan Font Family
- **LeagueSpartan-Regular.ttf** (Weight: 400) - For subtext (14px)

---

## Installation Steps

### Step 1: Create Fonts Folder
```bash
mkdir -p assets/fonts
```

### Step 2: Download Fonts
1. **Poppins**: Download from [Google Fonts](https://fonts.google.com/specimen/Poppins)
   - Download the font family
   - Extract: Poppins-Light.ttf, Poppins-Medium.ttf, Poppins-SemiBold.ttf
   
2. **League Spartan**: Download from [Google Fonts](https://fonts.google.com/specimen/League+Spartan)
   - Download the font family
   - Extract: LeagueSpartan-Regular.ttf

### Step 3: Add Font Files
Place all .ttf files in the `assets/fonts/` folder:
```
assets/
└── fonts/
    ├── Poppins-Light.ttf
    ├── Poppins-Medium.ttf
    ├── Poppins-SemiBold.ttf
    └── LeagueSpartan-Regular.ttf
```

### Step 4: Create Configuration File
Create `react-native.config.js` in the root directory:

```javascript
module.exports = {
  project: {
    ios: {},
    android: {},
  },
  assets: ['./assets/fonts/'],
};
```

### Step 5: Link Fonts
Run the following command:
```bash
npx react-native-asset
```

This will:
- Copy fonts to iOS project (Info.plist)
- Copy fonts to Android project (android/app/src/main/assets/fonts/)

### Step 6: Rebuild the App

**For Android:**
```bash
cd android
./gradlew clean
cd ..
npx react-native run-android
```

**For iOS (if applicable):**
```bash
cd ios
pod install
cd ..
npx react-native run-ios
```

---

## Usage in Code

The font styles are already configured in `src/theme.js`:

```javascript
import { FONTS_SYSTEM } from './theme';

// For titles (20px, Poppins SemiBold)
<Text style={[styles.title, FONTS_SYSTEM.title]}>Title Text</Text>

// For subtitles (15px, Poppins Medium)
<Text style={[styles.subtitle, FONTS_SYSTEM.subtitle]}>Subtitle Text</Text>

// For paragraphs (13px, Poppins Light)
<Text style={[styles.paragraph, FONTS_SYSTEM.paragraph]}>Paragraph Text</Text>

// For subtext (14px, League Spartan Regular)
<Text style={[styles.subtext, FONTS_SYSTEM.subtext]}>Subtext</Text>
```

**Note:** Currently using `FONTS_SYSTEM` which provides system fonts with correct sizes and weights. Once custom fonts are installed, you can switch to `FONTS` to use Poppins and League Spartan.

---

## Font Mapping

| Text Type | Font Family | Weight | Size | Usage |
|-----------|-------------|--------|------|-------|
| **Title** | Poppins SemiBold | 600 | 20px | Headers, main titles |
| **Subtitle** | Poppins Medium | 500 | 15px | Section headers, card titles |
| **Paragraph** | Poppins Light | 300 | 13px | Body text, descriptions |
| **Subtext** | League Spartan Regular | 400 | 14px | Labels, metadata, secondary info |

---

## Troubleshooting

### Fonts not showing on Android
- Make sure fonts are in `android/app/src/main/assets/fonts/`
- Clean and rebuild: `cd android && ./gradlew clean && cd ..`
- Rebuild: `npx react-native run-android`

### Fonts not showing on iOS
- Check `ios/[ProjectName]/Info.plist` has font entries
- Run: `cd ios && pod install && cd ..`
- Rebuild: `npx react-native run-ios`

### Font names incorrect
- Font file name must match the font family name
- Use exact names: `Poppins-Light`, `Poppins-Medium`, `Poppins-SemiBold`, `LeagueSpartan-Regular`

---

## Current Status

✅ Font configuration added to `src/theme.js`
✅ System font fallback configured (FONTS_SYSTEM)
⏳ Waiting for font files to be added
⏳ Waiting for fonts to be linked
⏳ Waiting for app rebuild

**Next Steps:**
1. Download the font files
2. Follow installation steps above
3. The app will automatically use custom fonts once configured!

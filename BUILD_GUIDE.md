# VitalWear Desktop Application Build Guide

## 🚀 Build Commands

Now that electron-builder is properly configured, you can use these commands:

### 📦 Build Commands

**For Development:**
```bash
npm start              # Run in development mode
npm run dev            # Development with XAMPP
npm run start:xampp    # XAMPP server mode
npm run start:server   # Built-in server mode
```

**For Production Builds:**
```bash
npm run build          # Build for current platform
npm run build:win      # Build Windows installer
npm run build:mac      # Build macOS app
npm run build:linux    # Build Linux app
npm run dist           # Build without publishing
```

### 🎯 Build Outputs

**Windows:**
- `dist/VitalWear Setup x.x.x.exe` - NSIS installer
- `dist/VitalWear x.x.x.exe` - Portable executable

**macOS:**
- `dist/VitalWear x.x.x.dmg` - DMG installer
- `dist/VitalWear x.x.x-mac.zip` - ZIP archive

**Linux:**
- `dist/VitalWear x.x.x.AppImage` - AppImage
- `dist/vitalwear_1.0.0_amd64.deb` - Debian package
- `dist/vitalwear-1.0.0-1.x86_64.rpm` - RPM package

### 🔧 Build Configuration

**Features Configured:**
- ✅ **Multi-platform support** - Windows, macOS, Linux
- ✅ **Multiple formats** - Installer, portable, archive
- ✅ **Auto-updater ready** - Can be configured for updates
- ✅ **Code signing** - Ready for digital signatures
- ✅ **Desktop shortcuts** - Auto-created during install
- ✅ **Start menu integration** - Windows start menu support

### 📋 Prerequisites for Building

**Required Files:**
- `main.js` - Main Electron process
- `preload.js` - Preload script
- `package.json` - Build configuration
- Application files (HTML, CSS, JS, PHP)

**Optional Files:**
- `assets/icon.ico` - Windows icon (256x256)
- `assets/icon.icns` - macOS icon
- `assets/icon.png` - Linux icon (512x512)
- `assets/dmg-background.png` - macOS DMG background

### 🚨 Build Requirements

**System Requirements:**
- **Node.js** 16+ installed
- **npm** or **yarn** package manager
- **Platform-specific tools** for each target platform

**Windows Build:**
- Windows 10+ with Visual Studio Build Tools
- Or Windows 10+ with Windows SDK

**macOS Build:**
- macOS 10.13+ with Xcode Command Line Tools
- For notarization: Apple Developer account

**Linux Build:**
- Linux with build essentials
- For packages: dpkg (Debian) or rpmbuild (RPM)

### 🎯 Build Process

**Step 1: Prepare Application**
```bash
# Ensure all files are ready
npm install           # Install dependencies
npm run dev          # Test application
```

**Step 2: Build for Target Platform**
```bash
# Windows
npm run build:win

# macOS
npm run build:mac

# Linux
npm run build:linux
```

**Step 3: Test Build**
```bash
# Test installer
dist/VitalWear\ Setup\ 1.0.0.exe
```

### 🔧 Customization Options

**Change App Information:**
```json
"build": {
  "appId": "com.vitalwear.desktop",
  "productName": "VitalWear",
  "directories": {
    "output": "dist"
  }
}
```

**Modify Build Targets:**
```json
"win": {
  "target": ["nsis", "portable"],
  "icon": "assets/icon.ico"
}
```

**Configure Installer:**
```json
"nsis": {
  "oneClick": false,
  "allowToChangeInstallationDirectory": true,
  "createDesktopShortcut": true,
  "createStartMenuShortcut": true
}
```

### 📱 Distribution

**For Development:**
- Share portable executables
- Use development builds
- Test on target systems

**For Production:**
- Sign installers (recommended)
- Notarize macOS builds
- Distribute through app stores or direct download

**Version Management:**
- Update `package.json` version
- Use semantic versioning (x.y.z)
- Keep changelog of changes

### 🚀 Troubleshooting

**Common Issues:**
- **Missing icons** - Create placeholder icons in assets folder
- **Build failures** - Check platform-specific requirements
- **Large file size** - Exclude unnecessary files in build config
- **Permission errors** - Run as administrator on Windows

**Debug Mode:**
```bash
# Enable debug logging
DEBUG=electron-builder npm run build
```

**Clean Build:**
```bash
# Clean previous builds
rm -rf dist/
npm run build
```

---

**🎉 Your VitalWear desktop application is now ready for building and distribution!**

Use the build commands to create installers for your target platform and distribute your healthcare monitoring application.

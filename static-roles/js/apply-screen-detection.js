/**
 * Apply Screen Detection to All Management Pages
 * This script should be run to update all management pages with screen detection
 */

const fs = require('fs');
const path = require('path');

// List of management pages to update
const managementPages = [
    'management/dashboard.html',
    'management/users.html', 
    'management/devices.html',
    'management/assign-device.html',
    'management/reports.html',
    'management/verify-return.html',
    'management/dashboard-new.html'
];

// Function to update a single file
function updateManagementPage(filePath) {
    try {
        let content = fs.readFileSync(filePath, 'utf8');
        
        // Check if screen detection is already added
        if (content.includes('screen-detection.js')) {
            console.log(`✅ Screen detection already exists in ${filePath}`);
            return;
        }
        
        // Add screen detection script after database.js
        content = content.replace(
            /<script src="\.\.\/js\/database\.js"><\/script>/,
            '<script src="../js/database.js"></script>\n    <script src="../js/screen-detection.js"></script>'
        );
        
        // Add screen check to DOMContentLoaded
        content = content.replace(
            /document\.addEventListener\('DOMContentLoaded', function\(\) \{\s*checkAuth\(\);/,
            `document.addEventListener('DOMContentLoaded', function() {
            // Check if screen is too small for management
            if (window.screenDetector && window.screenDetector.disableForSmallScreens()) {
                console.warn('Management page disabled due to small screen');
                return;
            }
            
            checkAuth();`
        );
        
        // Add screen check to initialization functions
        content = content.replace(
            /function initialize\(\) \{\s*const user = getCurrentUser\(\);/,
            `function initialize() {
            // Check if screen is too small for management
            if (window.screenDetector && window.screenDetector.disableForSmallScreens()) {
                console.warn('Management page disabled due to small screen');
                return;
            }
            
            const user = getCurrentUser();`
        );
        
        fs.writeFileSync(filePath, content);
        console.log(`✅ Updated ${filePath} with screen detection`);
        
    } catch (error) {
        console.error(`❌ Error updating ${filePath}:`, error.message);
    }
}

// Update all management pages
console.log('🔧 Applying screen detection to management pages...\n');

managementPages.forEach(page => {
    const fullPath = path.join(__dirname, '..', page);
    updateManagementPage(fullPath);
});

console.log('\n✅ Screen detection applied to all management pages!');
console.log('\n📱 Features added:');
console.log('- Small screen detection (< 1024px × 768px)');
console.log('- Warning overlay with countdown');
console.log('- Option to continue anyway');
console.log('- Redirect to mobile view');
console.log('- Persistent warning banner');
console.log('- Session override rememberance');

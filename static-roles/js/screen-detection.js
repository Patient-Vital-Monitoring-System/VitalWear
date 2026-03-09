/**
 * Screen Size Detection and Management Page Restrictions
 * VitalWear - Management Role Mobile Restrictions
 */

class ScreenDetector {
    constructor() {
        this.minWidth = 1024; // Minimum width for management pages
        this.minHeight = 768; // Minimum height for management pages
        this.isManagement = false;
        this.currentRole = null;
        
        this.init();
    }

    init() {
        // Check current role
        this.currentRole = sessionStorage.getItem('current_role');
        this.isManagement = this.currentRole === 'management';
        
        if (this.isManagement) {
            this.checkScreenSize();
            this.setupEventListeners();
            this.showScreenWarningIfNeeded();
        }
    }

    checkScreenSize() {
        const width = window.innerWidth;
        const height = window.innerHeight;
        
        console.log(`Screen size: ${width}x${height}, Management: ${this.isManagement}`);
        
        if (width < this.minWidth || height < this.minHeight) {
            this.handleSmallScreen();
        } else {
            this.handleValidScreen();
        }
    }

    handleSmallScreen() {
        if (!this.isManagement) return;
        
        console.warn('Small screen detected for management role');
        
        // Show warning overlay
        this.showScreenWarning();
        
        // Optional: Redirect after delay
        setTimeout(() => {
            this.redirectToSuitablePage();
        }, 5000);
    }

    handleValidScreen() {
        // Hide warning if shown
        this.hideScreenWarning();
    }

    showScreenWarning() {
        // Remove existing warning
        this.hideScreenWarning();
        
        const warningHTML = `
            <div id="screen-warning" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.95);
                color: white;
                z-index: 10000;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
                padding: 20px;
                font-family: 'Inter', sans-serif;
            ">
                <div style="max-width: 500px;">
                    <i class="fa fa-desktop" style="font-size: 64px; color: #ff6b6b; margin-bottom: 20px;"></i>
                    <h2 style="color: #ff6b6b; margin-bottom: 16px; font-size: 24px;">Screen Size Too Small</h2>
                    <p style="margin-bottom: 16px; font-size: 16px; line-height: 1.5;">
                        Management dashboard requires a larger screen for optimal functionality.
                    </p>
                    <p style="margin-bottom: 24px; font-size: 14px; opacity: 0.8;">
                        Minimum required: ${this.minWidth}px × ${this.minHeight}px<br>
                        Current screen: ${window.innerWidth}px × ${window.innerHeight}px
                    </p>
                    <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                        <button onclick="screenDetector.continueAnyway()" style="
                            background: #ff6b6b;
                            color: white;
                            border: none;
                            padding: 12px 24px;
                            border-radius: 8px;
                            cursor: pointer;
                            font-size: 14px;
                            font-weight: 600;
                        ">
                            Continue Anyway
                        </button>
                        <button onclick="screenDetector.redirectToSuitablePage()" style="
                            background: #6c757d;
                            color: white;
                            border: none;
                            padding: 12px 24px;
                            border-radius: 8px;
                            cursor: pointer;
                            font-size: 14px;
                            font-weight: 600;
                        ">
                            Go to Mobile View
                        </button>
                    </div>
                    <p style="margin-top: 20px; font-size: 12px; opacity: 0.6;">
                        Auto-redirecting in <span id="countdown">5</span> seconds...
                    </p>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', warningHTML);
        this.startCountdown();
    }

    hideScreenWarning() {
        const warning = document.getElementById('screen-warning');
        if (warning) {
            warning.remove();
        }
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
    }

    startCountdown() {
        let seconds = 5;
        const countdownEl = document.getElementById('countdown');
        
        this.countdownInterval = setInterval(() => {
            seconds--;
            if (countdownEl) {
                countdownEl.textContent = seconds;
            }
            if (seconds <= 0) {
                clearInterval(this.countdownInterval);
                this.redirectToSuitablePage();
            }
        }, 1000);
    }

    continueAnyway() {
        this.hideScreenWarning();
        // Add a flag to session storage to remember user choice
        sessionStorage.setItem('management_screen_override', 'true');
        
        // Show a persistent warning banner
        this.showWarningBanner();
    }

    showWarningBanner() {
        const bannerHTML = `
            <div id="screen-banner" style="
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: #ff6b6b;
                color: white;
                padding: 8px 16px;
                text-align: center;
                z-index: 9999;
                font-size: 14px;
                font-weight: 600;
            ">
                <i class="fa fa-exclamation-triangle"></i>
                Management dashboard not optimized for this screen size
                <button onclick="screenDetector.hideWarningBanner()" style="
                    background: transparent;
                    border: 1px solid white;
                    color: white;
                    padding: 2px 8px;
                    border-radius: 4px;
                    margin-left: 10px;
                    cursor: pointer;
                    font-size: 12px;
                ">×</button>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', bannerHTML);
        
        // Adjust topbar position
        const topbar = document.querySelector('.topbar');
        if (topbar) {
            topbar.style.top = '40px';
        }
    }

    hideWarningBanner() {
        const banner = document.getElementById('screen-banner');
        if (banner) {
            banner.remove();
        }
        
        // Reset topbar position
        const topbar = document.querySelector('.topbar');
        if (topbar) {
            topbar.style.top = '0';
        }
    }

    redirectToSuitablePage() {
        // Clear management session
        sessionStorage.removeItem('current_role');
        sessionStorage.removeItem('management_screen_override');
        
        // Redirect to main index
        window.location.href = '../index.html';
    }

    setupEventListeners() {
        // Check screen size on resize
        window.addEventListener('resize', () => {
            this.checkScreenSize();
        });
        
        // Check screen size on orientation change
        window.addEventListener('orientationchange', () => {
            setTimeout(() => {
                this.checkScreenSize();
            }, 100);
        });
    }

    showScreenWarningIfNeeded() {
        // Check if user previously chose to continue anyway
        const override = sessionStorage.getItem('management_screen_override');
        
        if (override !== 'true') {
            this.checkScreenSize();
        } else {
            // Show banner if screen is still small but user chose to continue
            if (window.innerWidth < this.minWidth || window.innerHeight < this.minHeight) {
                this.showWarningBanner();
            }
        }
    }

    // Public API methods
    disableForSmallScreens() {
        return this.isManagement && (window.innerWidth < this.minWidth || window.innerHeight < this.minHeight);
    }

    getScreenInfo() {
        return {
            width: window.innerWidth,
            height: window.innerHeight,
            isSmall: window.innerWidth < this.minWidth || window.innerHeight < this.minHeight,
            isManagement: this.isManagement,
            minWidth: this.minWidth,
            minHeight: this.minHeight
        };
    }
}

// Initialize screen detector
let screenDetector;

document.addEventListener('DOMContentLoaded', function() {
    screenDetector = new ScreenDetector();
    
    // Make it globally available
    window.screenDetector = screenDetector;
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ScreenDetector;
}

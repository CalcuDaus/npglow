<!-- PWA: Floating Install Button & Service Worker Registration -->
<style>
/* Floating PWA Install Button Styles */
#pwa-floating-container {
    position: fixed;
    bottom: 5.5rem; /* Above mobile bottom-nav */
    right: 1.25rem;
    z-index: 9999;
    display: none; /* Controlled via JS */
    align-items: center;
    animation: pwaSlideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@media (min-width: 640px) {
    #pwa-floating-container {
        bottom: 1.75rem;
        right: 1.75rem;
    }
}

.pwa-floating-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.65rem 1.15rem;
    background: linear-gradient(135deg, #3ca6f2 0%, #1d72b8 100%);
    color: #ffffff;
    border-radius: 9999px;
    box-shadow: 0 10px 25px -5px rgba(60, 166, 242, 0.5), 0 8px 10px -6px rgba(60, 166, 242, 0.3);
    border: 1.5px solid rgba(255, 255, 255, 0.35);
    backdrop-filter: blur(8px);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    font-size: 0.875rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    user-select: none;
    text-decoration: none;
}

.pwa-floating-btn:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 16px 30px -5px rgba(60, 166, 242, 0.6), 0 10px 12px -5px rgba(60, 166, 242, 0.4);
    background: linear-gradient(135deg, #4cb0f5 0%, #1765a3 100%);
}

.pwa-floating-btn:active {
    transform: translateY(0) scale(0.98);
}

.pwa-btn-icon-wrap {
    width: 2rem;
    height: 2rem;
    background: rgba(255, 255, 255, 0.22);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    position: relative;
}

.pwa-btn-icon-wrap svg {
    width: 1.15rem;
    height: 1.15rem;
    color: #ffffff;
    animation: pwaBounceIcon 2s infinite ease-in-out;
}

.pwa-btn-text-wrap {
    display: flex;
    flex-direction: column;
    text-align: left;
    line-height: 1.15;
}

.pwa-btn-title {
    font-size: 0.825rem;
    font-weight: 800;
    letter-spacing: -0.01em;
}

.pwa-btn-sub {
    font-size: 0.65rem;
    font-weight: 500;
    opacity: 0.9;
}

.pwa-dismiss-btn {
    width: 1.35rem;
    height: 1.35rem;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    border: none;
    color: white;
    font-size: 0.75rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: 0.25rem;
    transition: background 0.2s;
}

.pwa-dismiss-btn:hover {
    background: rgba(255, 255, 255, 0.4);
}

@keyframes pwaSlideUp {
    from {
        opacity: 0;
        transform: translateY(24px) scale(0.9);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes pwaBounceIcon {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-2.5px); }
}

/* iOS Install Modal */
#pwa-ios-modal {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    z-index: 10000;
    display: none;
    align-items: flex-end;
    justify-content: center;
    padding: 1rem;
}

@media (min-width: 640px) {
    #pwa-ios-modal {
        align-items: center;
    }
}

.pwa-ios-card {
    background: #ffffff;
    border-radius: 1.25rem;
    max-width: 24rem;
    width: 100%;
    padding: 1.5rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    animation: pwaSlideUp 0.3s ease-out forwards;
    position: relative;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}
</style>

<!-- Floating PWA Install Button HTML -->
<div id="pwa-floating-container" aria-label="Install App NPGLOW">
    <button type="button" class="pwa-floating-btn" id="pwa-floating-install-btn" onclick="handlePWAInstallClick()">
        <div class="pwa-btn-icon-wrap">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
            </svg>
        </div>
        <div class="pwa-btn-text-wrap">
            <span class="pwa-btn-title">Install NPGLOW</span>
            <span class="pwa-btn-sub">Aplikasi Lebih Cepat</span>
        </div>
        <span class="pwa-dismiss-btn" onclick="dismissPWAInstall(event)" title="Tutup">✕</span>
    </button>
</div>

<!-- iOS Install Guide Modal -->
<div id="pwa-ios-modal" onclick="closePWAiOSModal(event)">
    <div class="pwa-ios-card" onclick="event.stopPropagation()">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <img src="/npglow/assets/icons/icon-192.png" alt="NPGLOW" style="width: 2.5rem; height: 2.5rem; border-radius: 0.5rem; object-fit: contain;">
                <div>
                    <h3 style="margin: 0; font-size: 1rem; font-weight: 800; color: #1e293b;">Install NPGLOW di iOS</h3>
                    <p style="margin: 0; font-size: 0.75rem; color: #64748b;">Gunakan NPGLOW layaknya aplikasi native</p>
                </div>
            </div>
            <button onclick="closePWAiOSModal()" style="border: none; background: #f1f5f9; border-radius: 50%; width: 1.75rem; height: 1.75rem; cursor: pointer; color: #64748b; font-weight: bold;">✕</button>
        </div>

        <div style="font-size: 0.85rem; color: #334155; line-height: 1.6; background: #f8fafc; padding: 1rem; border-radius: 0.75rem; border: 1px solid #e2e8f0; margin-bottom: 1rem;">
            <p style="margin: 0 0 0.5rem 0;">1. Ketuk tombol <strong>Bagikan (Share)</strong> <svg style="display:inline; width:1.1rem; height:1.1rem; vertical-align:middle; color:#3ca6f2;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg> pada browser Safari.</p>
            <p style="margin: 0;">2. Gulir ke bawah lalu pilih <strong>"Tambah ke Layar Utama" (Add to Home Screen)</strong>.</p>
        </div>

        <button onclick="closePWAiOSModal()" style="width: 100%; padding: 0.75rem; background: #3ca6f2; color: white; border: none; border-radius: 0.75rem; font-weight: 700; font-size: 0.875rem; cursor: pointer;">
            Mengerti
        </button>
    </div>
</div>

<script>
// PWA Logic & Service Worker Registration
(function() {
    // 1. Check if application is already installed or in standalone mode
    function isPWAInstalled() {
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches;
        const isIOSStandalone = window.navigator.standalone === true;
        const isStoredInstalled = localStorage.getItem('npglow_pwa_installed') === 'true';
        const isAndroidApp = document.referrer && document.referrer.startsWith('android-app://');
        
        return isStandalone || isIOSStandalone || isStoredInstalled || isAndroidApp;
    }

    // 2. Register Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/npglow/sw.js')
                .then((reg) => {
                    console.log('[PWA] Service Worker registered scope:', reg.scope);
                })
                .catch((err) => {
                    console.warn('[PWA] Service Worker registration failed:', err);
                });
        });
    }

    // Global references
    let deferredPrompt = null;
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;

    function showFloatingInstallButton() {
        // If already installed or dismissed in current session, do not show
        if (isPWAInstalled()) {
            hideFloatingInstallButton();
            return;
        }
        if (sessionStorage.getItem('pwa_floating_dismissed') === 'true') {
            return;
        }

        const container = document.getElementById('pwa-floating-container');
        if (container) {
            container.style.display = 'flex';
        }
    }

    function hideFloatingInstallButton() {
        const container = document.getElementById('pwa-floating-container');
        if (container) {
            container.style.display = 'none';
        }
    }

    // 3. Handle beforeinstallprompt (Chrome, Edge, Android, etc.)
    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent immediate default mini-infobar
        e.preventDefault();
        deferredPrompt = e;

        // If not installed, show the floating button
        if (!isPWAInstalled()) {
            showFloatingInstallButton();
        }
    });

    // 4. Handle appinstalled event
    window.addEventListener('appinstalled', () => {
        console.log('[PWA] App successfully installed!');
        deferredPrompt = null;
        localStorage.setItem('npglow_pwa_installed', 'true');
        hideFloatingInstallButton();
    });

    // 5. Initial display check on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', () => {
        if (isPWAInstalled()) {
            hideFloatingInstallButton();
        } else {
            // If iOS Safari, show button so user can view iOS instructions
            if (isIOS && !isPWAInstalled()) {
                showFloatingInstallButton();
            }
        }
    });

    // 6. Global trigger functions for user click
    window.handlePWAInstallClick = function() {
        if (deferredPrompt) {
            // Trigger native installation prompt
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('[PWA] User accepted installation');
                    localStorage.setItem('npglow_pwa_installed', 'true');
                    hideFloatingInstallButton();
                } else {
                    console.log('[PWA] User dismissed installation');
                }
                deferredPrompt = null;
            });
        } else if (isIOS) {
            // Show iOS guide modal
            const iosModal = document.getElementById('pwa-ios-modal');
            if (iosModal) iosModal.style.display = 'flex';
        } else {
            // Fallback for browsers when prompt already consumed or not ready
            console.log('[PWA] Installation prompt not ready or not supported');
        }
    };

    window.dismissPWAInstall = function(e) {
        if (e) e.stopPropagation();
        sessionStorage.setItem('pwa_floating_dismissed', 'true');
        hideFloatingInstallButton();
    };

    window.closePWAiOSModal = function() {
        const iosModal = document.getElementById('pwa-ios-modal');
        if (iosModal) iosModal.style.display = 'none';
    };

    // Keep compatibility for any legacy installPWA() calls
    window.installPWA = window.handlePWAInstallClick;
})();
</script>

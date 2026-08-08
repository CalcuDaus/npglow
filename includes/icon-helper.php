<?php
/**
 * NPGLOW Two-Tone SVG Icon Helper
 * Provides crisp, lightweight, accessible two-tone SVG icons for a consistent UI.
 */

if (!function_exists('npglow_icon')) {
    function npglow_icon($name, $class = 'w-4 h-4 inline-block', $duotone = true) {
        $icons = [
            // Camera (Foto)
            'camera' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 8C4 6.89543 4.89543 6 6 6H8.27924C8.80964 6 9.31835 5.78929 9.69299 5.41465L10.5858 4.52184C10.9604 4.1472 11.4691 3.93649 11.9995 3.93649H12.0005C12.5309 3.93649 13.0396 4.1472 13.4142 4.52184L14.307 5.41465C14.6816 5.78929 15.1904 6 15.7208 6H18C19.1046 6 20 6.89543 20 8V18C20 19.1046 19.1046 20 18 20H6C4.89543 20 4 19.1046 4 18V8Z" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.16" ' : '') . 'stroke-width="1.8" stroke-linejoin="round"/>
                <circle cx="12" cy="13" r="3.5" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.25" ' : '') . 'stroke-width="1.8"/>
            </svg>',

            // Sparkles / Progress (✨)
            'sparkles' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 3L14.09 8.26L19 9.27L15.18 12.97L16.18 18.23L12 15.77L7.82 18.23L8.82 12.97L5 9.27L9.91 8.26L12 3Z" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.2" ' : '') . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M19 3L19.7 4.5L21.2 5.2L19.7 5.9L19 7.4L18.3 5.9L16.8 5.2L18.3 4.5L19 3Z" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.4" ' : '') . 'stroke-width="1.2"/>
            </svg>',

            // Package / Box (📦)
            'package' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 3L20 7.5V16.5L12 21L4 16.5V7.5L12 3Z" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.16" ' : '') . 'stroke-width="1.8" stroke-linejoin="round"/>
                <path d="M12 12L20 7.5M12 12V21M12 12L4 7.5" stroke-width="1.8" stroke-linejoin="round"/>
                <path d="M7.5 5.5L15.5 10" stroke-width="1.5" stroke-linecap="round"/>
            </svg>',

            // Location Pin (📍)
            'pin' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 21C16 17 20 13.4183 20 9C20 4.58172 16.4183 1 12 1C7.58172 1 4 4.58172 4 9C4 13.4183 8 17 12 21Z" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.18" ' : '') . 'stroke-width="1.8" stroke-linejoin="round"/>
                <circle cx="12" cy="9" r="3" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.3" ' : '') . 'stroke-width="1.8"/>
            </svg>',

            // Lightbulb (💡)
            'lightbulb' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M9 18H15M10 21H14M12 2C7.58172 2 4 5.58172 4 10C4 12.892 5.535 15.424 7.828 16.828C8.253 17.088 8.5 17.54 8.5 18.036V18H15.5V18.036C15.5 17.54 15.747 17.088 16.172 16.828C18.465 15.424 20 12.892 20 10C20 5.58172 16.4183 2 12 2Z" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.18" ' : '') . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>',

            // Bot / AI (🤖)
            'bot' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="6" width="18" height="14" rx="4" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.16" ' : '') . 'stroke-width="1.8"/>
                <circle cx="8.5" cy="12" r="1.5" fill="currentColor"/>
                <circle cx="15.5" cy="12" r="1.5" fill="currentColor"/>
                <path d="M12 2V6M9 16H15M2 13H3M21 13H22" stroke-width="1.8" stroke-linecap="round"/>
            </svg>',

            // User / Profile (👤)
            'user' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 21V19C20 16.7909 18.2091 15 16 15H8C5.79086 15 4 16.7909 4 19V21" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.16" ' : '') . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="12" cy="7" r="4" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.25" ' : '') . 'stroke-width="1.8"/>
            </svg>',

            // Cart (🛒)
            'cart' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <circle cx="9" cy="20" r="1.5" fill="currentColor"/>
                <circle cx="18" cy="20" r="1.5" fill="currentColor"/>
                <path d="M3 3H5.2L7.36 14.34C7.47 14.92 7.97 15.34 8.56 15.34H17.8C18.36 15.34 18.84 14.95 18.97 14.4L20.8 7.34C20.94 6.78 20.52 6.25 19.94 6.25H6" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.14" ' : '') . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>',

            // Wallet (👛/💳)
            'wallet' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 7H4C2.89543 7 2 7.89543 2 9V19C2 20.1046 2.89543 21 4 21H20C21.1046 21 22 20.1046 22 19V9C22 7.89543 21.1046 7 20 7Z" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.16" ' : '') . 'stroke-width="1.8" stroke-linejoin="round"/>
                <path d="M16 3H4C2.89543 3 2 3.89543 2 5V7H20V5C20 3.89543 19.1046 3 18 3H16Z" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.25" ' : '') . 'stroke-width="1.8" stroke-linejoin="round"/>
                <circle cx="16" cy="14" r="1.5" fill="currentColor"/>
            </svg>',

            // Chat / Konsultasi (💬)
            'chat' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M8 12H8.01M12 12H12.01M16 12H16.01M21 12C21 16.4183 16.9706 20 12 20C10.4566 20 9.00609 19.6547 7.74549 19.0506L3 20L4.39511 16.2797C3.51221 15.0422 3 13.5739 3 12C3 7.58172 7.02944 4 12 4C16.9706 4 21 7.58172 21 12Z" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.18" ' : '') . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>',

            // Lightning / Fast (⚡)
            'lightning' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M13 2L3 14H12L11 22L21 10H12L13 2Z" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.25" ' : '') . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>',

            // Checkmark (✓)
            'check' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M5 13L9 17L19 7" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>',

            // Check Circle (Two-tone Check)
            'check-circle' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.16" ' : '') . 'stroke-width="1.8"/>
                <path d="M8 12L11 15L16 9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>',

            // Cross / X Circle (Two-tone Reject)
            'x-circle' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.16" ' : '') . 'stroke-width="1.8"/>
                <path d="M15 9L9 15M9 9L15 15" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>',

            // Mail (📧)
            'mail' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="5" width="18" height="14" rx="3" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.16" ' : '') . 'stroke-width="1.8"/>
                <path d="M3 7L12 13L21 7" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>',

            // Calendar (📅)
            'calendar' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="4" width="18" height="17" rx="3" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.16" ' : '') . 'stroke-width="1.8"/>
                <path d="M3 9H21M8 2V5M16 2V5" stroke-width="1.8" stroke-linecap="round"/>
            </svg>',

            // Book / Journal (📖)
            'book' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5A2.5 2.5 0 0 0 6.5 22H20V2H6.5A2.5 2.5 0 0 0 4 4.5V19.5Z" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.16" ' : '') . 'stroke-width="1.8" stroke-linejoin="round"/>
            </svg>',

            // Warning Triangle (⚠️)
            'warning' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2L1 21H23L12 2Z" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.16" ' : '') . 'stroke-width="1.8" stroke-linejoin="round"/>
                <path d="M12 9V14M12 17.5V18" stroke-width="2" stroke-linecap="round"/>
            </svg>',

            // Star (★)
            'star' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.25" ' : '') . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>',

            // Hand wave / Greeting (👋)
            'wave' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M18 11V7.5C18 6.11929 16.8807 5 15.5 5C14.1193 5 13 6.11929 13 7.5V11M13 7.5C13 6.11929 11.8807 5 10.5 5C9.11929 5 8 6.11929 8 7.5V12M8 8.5C8 7.11929 6.88071 6 5.5 6C4.11929 6 3 7.11929 3 8.5V14.5C3 18.0899 5.91015 21 9.5 21H13.5C17.0899 21 20 18.0899 20 14.5V10.5C20 9.11929 18.8807 8 17.5 8C16.1193 8 15 9.11929 15 10.5V11" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.16" ' : '') . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>',

            // Home (🏠)
            'home' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 12L5 10M5 10L12 3L19 10M5 10V20C5 20.5523 5.44772 21 6 21H9M19 10L21 12M19 10V20C19 20.5523 18.5523 21 18 21H15M9 21C9.55228 21 10 20.5523 10 20V16C10 15.4477 10.4477 15 11 15H13C13.5523 15 14 15.4477 14 16V20C14 20.5523 14.4477 21 15 21M9 21H15" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.14" ' : '') . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>',

            // Home filled (for active state)
            'home-filled' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M12.707 2.293a1 1 0 00-1.414 0l-9 9a1 1 0 001.414 1.414L4 12.414V20a2 2 0 002 2h3a1 1 0 001-1v-4a2 2 0 012-2h0a2 2 0 012 2v4a1 1 0 001 1h3a2 2 0 002-2v-7.586l.293.293a1 1 0 001.414-1.414l-9-9z"/>
            </svg>',

            // Shopping Bag (🛍️)
            'shop-bag' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M6 2L3 6V20C3 20.5304 3.21071 21.0391 3.58579 21.4142C3.96086 21.7893 4.46957 22 5 22H19C19.5304 22 20.0391 21.7893 20.4142 21.4142C20.7893 21.0391 21 20.5304 21 20V6L18 2H6Z" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.14" ' : '') . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M3 6H21M16 10C16 11.0609 15.5786 12.0783 14.8284 12.8284C14.0783 13.5786 13.0609 14 12 14C10.9391 14 9.92172 13.5786 9.17157 12.8284C8.42143 12.0783 8 11.0609 8 10" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>',

            // Shopping Bag filled
            'shop-bag-filled' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" d="M5.254 1.636A1 1 0 016.08 1h11.84a1 1 0 01.826.636l2.18 5.452A1 1 0 0121 7.5V20a2 2 0 01-2 2H5a2 2 0 01-2-2V7.5a1 1 0 01.074-.412l2.18-5.452zM8 10a1 1 0 012 0 2 2 0 004 0 1 1 0 112 0 4 4 0 01-8 0z" clip-rule="evenodd"/>
            </svg>',

            // Clipboard / Orders (📋)
            'clipboard' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.14" ' : '') . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                <rect x="9" y="3" width="6" height="4" rx="1" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.25" ' : '') . 'stroke-width="1.8"/>
                <path d="M9 12H15M9 16H13" stroke-width="1.8" stroke-linecap="round"/>
            </svg>',

            // Clipboard filled
            'clipboard-filled' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 3a1 1 0 00-1 1v1H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2V4a1 1 0 00-1-1h-4zm1 7a1 1 0 100 2h4a1 1 0 100-2h-4zm-1 5a1 1 0 011-1h2a1 1 0 110 2h-2a1 1 0 01-1-1z"/>
            </svg>',

            // Chat filled
            'chat-filled' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 3C6.477 3 2 6.943 2 12c0 1.767.571 3.408 1.55 4.795L2 21l4.373-1.38A10.718 10.718 0 0012 21c5.523 0 10-3.943 10-9s-4.477-9-10-9zm-2.5 10.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm5 0a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/>
            </svg>',

            // User filled
            'user-filled' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2a5 5 0 100 10 5 5 0 000-10zM4 20a8 8 0 1116 0H4z"/>
            </svg>',

            // Truck / Shipping (🚚)
            'truck' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M1 3H16V16H1V3Z" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.14" ' : '') . 'stroke-width="1.8" stroke-linejoin="round"/>
                <path d="M16 8H20L23 11V16H16V8Z" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.25" ' : '') . 'stroke-width="1.8" stroke-linejoin="round"/>
                <circle cx="5.5" cy="18.5" r="2.5" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.2" ' : '') . 'stroke-width="1.8"/>
                <circle cx="18.5" cy="18.5" r="2.5" ' . ($duotone ? 'fill="currentColor" fill-opacity="0.2" ' : '') . 'stroke-width="1.8"/>
            </svg>',
        ];

        return $icons[$name] ?? '';
    }
}

/**
 * Returns formatted two-tone photo badge (Initial vs Progress)
 */
if (!function_exists('npglow_photo_badge')) {
    function npglow_photo_badge($type, $label = null) {
        $isInitial = ($type === 'initial');
        if ($label === null) {
            $label = $isInitial ? 'Foto Awal' : 'Foto Progress';
        }
        if ($isInitial) {
            return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-purple-50 text-purple-700 border border-purple-200/80 shadow-xs">' .
                npglow_icon('camera', 'w-3.5 h-3.5 text-purple-600') . ' ' .
                htmlspecialchars($label) .
            '</span>';
        } else {
            return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200/80 shadow-xs">' .
                npglow_icon('sparkles', 'w-3.5 h-3.5 text-amber-500') . ' ' .
                htmlspecialchars($label) .
            '</span>';
        }
    }
}

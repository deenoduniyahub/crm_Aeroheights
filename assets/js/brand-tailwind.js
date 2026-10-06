/* Aeroheights brand theme for the Tailwind CDN (load right after cdn.tailwindcss.com). */
tailwind.config = {
    darkMode: 'class',
    theme: {
        extend: {
            // Aeroheights palette (aeroheightstravels.com): indigo #26206f, blue #2183DF, navy #161a35.
            // slate/blue/amber are remapped so every existing screen picks up the brand without per-view edits.
            colors: {
                brand: { 50: '#eeedf8', 100: '#dcd9f1', 200: '#b7b1e3', 300: '#8d84d0', 400: '#5f55b4', 500: '#3a3292', 600: '#26206f', 700: '#1f1a5c', 800: '#191549', 900: '#161a35' },
                slate: { 50: '#f6f8fc', 100: '#eef2f9', 200: '#dce5ed', 300: '#c4cfdf', 400: '#8e9ab6', 500: '#636e8d', 600: '#4a5374', 700: '#343b5e', 800: '#24284a', 900: '#161a35', 950: '#0e1124' },
                blue: { 50: '#eef6fd', 100: '#d9eafb', 200: '#b5d6f6', 300: '#84bbef', 400: '#4f9de7', 500: '#3590e3', 600: '#2183DF', 700: '#1a6bb8', 800: '#1a5893', 900: '#1b4975', 950: '#122e4d' },
                indigo: { 50: '#eeedf8', 100: '#dcd9f1', 200: '#b7b1e3', 300: '#8d84d0', 400: '#5f55b4', 500: '#3a3292', 600: '#26206f', 700: '#1f1a5c', 800: '#191549', 900: '#130f38', 950: '#0b0922' },
                amber: { 50: '#fffaeb', 100: '#fff1c6', 200: '#ffe388', 300: '#fed54d', 400: '#FEC624', 500: '#f2b20b', 600: '#d18b05', 700: '#a76308', 800: '#874d0e', 900: '#6f3f10', 950: '#402004' }
            },
            fontFamily: {
                sans: ['Jost', 'Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'sans-serif'],
                mono: ['JetBrains Mono', 'ui-monospace', 'monospace'],
                urdu: ['Amiri', 'serif']
            }
        }
    }
}

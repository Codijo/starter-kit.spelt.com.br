/** @type {import('tailwindcss').Config} */
export default {
  content: ['./resources/**/*.blade.php', './resources/**/*.js', './node_modules/flowbite/**/*.js'],
  darkMode: 'class',
  theme: {
    extend: {
      // Design system herdado do App do Spelt.
      colors: {
        'canvas-white': '#ffffff',
        'jet-black': '#000000',
        'ink-black': '#0a0a0a',
        'thunder-gray': '#171717',
        'shadow-gray': '#262626',
        'steel-gray': '#404040',
        'subtle-ash': '#f5f5f5',
        'border-light': '#e5e5e5',
        'border-muted': '#d4d4d4',
        'accent-blue': '#3b82f6',
        'fresh-green': '#16a34a',
        'warm-orange': '#ea580c',
        'deep-violet': '#7c3aed',
        'linear-gray-dark': '#525252',
        'linear-gray-light': '#737373',
        'info-tint': '#dcfce7',
      },
      fontFamily: {
        display: ['Satoshi', 'Montserrat', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        title: ['Satoshi', 'Montserrat', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
        body: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
        mono: ['GeistMono', 'Roboto Mono', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
      },
      borderRadius: { md: '8px', lg: '12px', xl: '16px', xxl: '20px', full: '9999px' },
      boxShadow: {
        subtle: 'rgba(0, 0, 0, 0.05) 0px 1px 2px 0px',
        sm: 'rgba(0, 0, 0, 0.1) 0px 4px 6px -1px, rgba(0, 0, 0, 0.1) 0px 2px 4px -2px',
        md: 'rgba(0, 0, 0, 0.1) 0px 10px 15px -3px, rgba(0, 0, 0, 0.1) 0px 4px 6px -4px',
        lg: 'rgba(0, 0, 0, 0.09) 0px 20px 20px 0px',
      },
    },
  },
  plugins: [require('flowbite/plugin')],
};

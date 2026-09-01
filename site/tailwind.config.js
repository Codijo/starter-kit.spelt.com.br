/** @type {import('tailwindcss').Config} */
export default {
  content: ['./resources/**/*.blade.php', './resources/**/*.js'],
  theme: {
    extend: {
      // Sistema de design de MARKETING, herdado do site do Spelt (DESIGN.md).
      // Estética editorial: canvas quente + serif de display + um único accent de marca.
      colors: {
        // Superfícies
        canvas: '#f2efe9', // fundo principal da página
        'canvas-raised': '#f5f3ef', // painéis, cards, dropdowns
        white: '#ffffff',
        well: '#ece8df', // "poço" neutro de screenshot/feature
        'well-blue': '#c0d8dc',
        'well-purple': '#c8c7d9',

        // Texto (níveis de ênfase)
        ink: '#2a2823', // títulos e texto forte
        'ink-mid': '#5b554a', // corpo, descrições
        'ink-low': '#aca69a', // metadados finos, placeholder
        'ink-soft': '#7d7667', // rótulos mono, ícones sutis

        // Linhas
        line: '#dcd6cb',
        'line-soft': '#e8e3d9',

        // MARCA — troque estes dois para a cor do seu produto.
        brand: '#f2690d',
        'brand-hover': '#f05100',

        // Accents de apoio (variedade em features)
        'accent-blue': '#47a8b8',
        'accent-purple': '#928fbc',
      },
      fontFamily: {
        // Display serif dá o tom editorial. Troque por uma webfont se quiser.
        display: ['"Instrument Serif"', 'Georgia', 'ui-serif', 'Cambria', 'Times New Roman', 'serif'],
        title: ['"Instrument Serif"', 'Georgia', 'ui-serif', 'serif'],
        body: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
        mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Roboto Mono', 'monospace'],
      },
      borderRadius: { md: '8px', lg: '12px', xl: '16px', xxl: '24px', full: '9999px' },
      boxShadow: {
        subtle: 'rgba(12, 11, 9, 0.05) 0px 1px 2px 0px',
        sm: 'rgba(12, 11, 9, 0.08) 0px 4px 6px -1px, rgba(12, 11, 9, 0.06) 0px 2px 4px -2px',
        md: 'rgba(12, 11, 9, 0.10) 0px 10px 15px -3px, rgba(12, 11, 9, 0.08) 0px 4px 6px -4px',
        lg: 'rgba(12, 11, 9, 0.10) 0px 20px 30px -8px',
      },
      maxWidth: { content: '72rem' },
    },
  },
  plugins: [],
};

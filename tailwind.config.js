// tailwind.config.js
module.exports = {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './resources/**/*.vue',
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          primary: '#1B261D',
          accent: '#8fc99a',
          dark: '#2d4a35',
          muted: '#7E8A74',
          surface: '#F7F6F1',
          'surface-dark': '#0b0f19',
        },
        'surface-border': '#f0efe8', // used in cards
      },
    },
  },
  plugins: [],
};
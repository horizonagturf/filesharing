/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  theme: {
    fontFamily: {
      sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'sans-serif'],
    },
    extend: {
      colors: {
        'primary': 'rgb(var(--color-primary, 126 34 206) / <alpha-value>)',
        'primary-light': 'rgb(var(--color-primary-light, 147 51 234) / <alpha-value>)',
        'primary-superlight': 'rgb(var(--color-primary-superlight, 216 180 254) / <alpha-value>)',
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
  ],
}

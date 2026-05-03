/** @type {import('tailwindcss').Config} */

module.exports = {
  content: [
    "./universalstore_managementsystem/**/*.php",
    "./universalstore_managementsystem/**/*.js",
    "./templates/**/*.php",
    "./core/**/*.php",
    "./internal_logic/**/*.php",
    "./universalstore_managementsystem/dashboard/**/*.php",
    "./dashboard/**/*.js",
    "./universalstore_managementsystem/**/*.{php,html,js}",
  ],
  theme: {
    extend: {
      borderRadius: {
      'extra': '10px',
      }
     },
  },
  plugins: [],
}
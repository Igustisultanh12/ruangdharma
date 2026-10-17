/** @type {import('tailwindcss').Config} */
export default {
  content: ['./resources/**/*.blade.php','./resources/**/*.vue','./resources/**/*.js'],
  theme: { extend: { colors: { primary: { DEFAULT:'#7c3aed', dark:'#5b21b6' }, gold:'#f59e0b' } } },
  plugins: [],
};

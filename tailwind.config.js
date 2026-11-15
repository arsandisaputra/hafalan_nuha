/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                paper: {
                    DEFAULT: '#FAF8F5',
                    warm: '#F3EFE6',
                    border: '#E5DEC9',
                },
                night: {
                    DEFAULT: '#0F172A',
                    surface: '#1E293B',
                    border: '#334155',
                },
            },
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                arabic: ['"Scheherazade New"', '"Amiri"', 'serif'],
            },
        },
    },
    plugins: [],
};

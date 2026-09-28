import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    DEFAULT: '#00C897',
                    50: '#E6FFF8',
                    100: '#CCFFF1',
                    200: '#99FFE3',
                    300: '#66FFD5',
                    400: '#33FFC7',
                    500: '#00C897',
                    600: '#00A87E',
                    700: '#008465',
                    800: '#00604B',
                    900: '#003C32',
                },
                accent: {
                    DEFAULT: '#00A87E',
                    light: '#00C897',
                    dark: '#008465',
                },
            },
        },
    },

    plugins: [forms],
};

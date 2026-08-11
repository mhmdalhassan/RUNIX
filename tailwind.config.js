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
                sans: [
                    'Inter',
                    '-apple-system',
                    'BlinkMacSystemFont',
                    'Segoe UI',
                    ...defaultTheme.fontFamily.sans,
                ],
            },
            colors: {
                // Lets Tailwind utilities (bg-runix-primary, text-runix-text, …)
                // read the same tokens as resources/css/runix/variables.css, so
                // one-off utility classes and the runix/*.css component layer
                // never drift out of sync.
                runix: {
                    primary: 'var(--runix-primary)',
                    'primary-hover': 'var(--runix-primary-hover)',
                    'primary-active': 'var(--runix-primary-active)',
                    'primary-soft': 'var(--runix-primary-soft)',
                    background: 'var(--runix-background)',
                    surface: 'var(--runix-surface)',
                    'surface-secondary': 'var(--runix-surface-secondary)',
                    'surface-hover': 'var(--runix-surface-hover)',
                    text: 'var(--runix-text)',
                    'text-secondary': 'var(--runix-text-secondary)',
                    'text-tertiary': 'var(--runix-text-tertiary)',
                    border: 'var(--runix-border)',
                    'border-strong': 'var(--runix-border-strong)',
                    success: 'var(--runix-success)',
                    warning: 'var(--runix-warning)',
                    danger: 'var(--runix-danger)',
                    info: 'var(--runix-info)',
                },
            },
            borderRadius: {
                'runix-sm': 'var(--runix-radius-sm)',
                'runix-md': 'var(--runix-radius-md)',
                'runix-lg': 'var(--runix-radius-lg)',
            },
            boxShadow: {
                'runix-xs': 'var(--runix-shadow-xs)',
                'runix-sm': 'var(--runix-shadow-sm)',
                'runix-md': 'var(--runix-shadow-md)',
                'runix-lg': 'var(--runix-shadow-lg)',
            },
        },
    },

    plugins: [forms],
};

import js from '@eslint/js';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    { ignores: ['public/**', 'vendor/**', 'node_modules/**', 'bootstrap/ssr/**'] },
    {
        files: ['resources/js/**/*.{ts,tsx}'],
        extends: [js.configs.recommended, ...tseslint.configs.strict],
        languageOptions: { globals: globals.browser },
        plugins: { 'react-hooks': reactHooks },
        rules: {
            ...reactHooks.configs.recommended.rules,
            // XSS: React escapes by default; raw HTML is never rendered (docs/ARCHITECTURE.md §9).
            'no-restricted-syntax': [
                'error',
                { selector: "JSXAttribute[name.name='dangerouslySetInnerHTML']", message: 'Raw HTML is banned.' },
            ],
            'no-restricted-properties': [
                'error',
                { object: 'Math', property: 'random', message: 'Use crypto.randomUUID() for identifiers.' },
            ],
            '@typescript-eslint/consistent-type-imports': 'error',
        },
    },
);

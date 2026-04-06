const vueParser = require('vue-eslint-parser');
const tsParser = require('@typescript-eslint/parser');
const tsPlugin = require('@typescript-eslint/eslint-plugin');
const vuePlugin = require('eslint-plugin-vue');
const prettierPlugin = require('eslint-plugin-prettier');

module.exports = [
  {
    ignores: [
      'node_modules/**',
      'dist/**',
      'vendor/**',
      'public/**',
      'vendor-bundle/**',
      'tests/**',
      'bin/**',
      'src/**',
      'docker/**',
      '*.json',
      '*.neon',
      '*.xml',
      '*.yml',
      '*.md',
      'LICENSE',
      'package-lock.json',
      'webpack.config.js',
      'vite.config.ts',
      'tsconfig.json',
      'tsconfig.node.json',
    ],
  },
  {
    files: ['assets/**/*.{ts,vue}'],
    languageOptions: {
      parser: vueParser,
      parserOptions: {
        parser: tsParser,
        ecmaVersion: 2022,
        sourceType: 'module',
        extraFileExtensions: ['.vue'],
      },
    },
    plugins: {
      '@typescript-eslint': tsPlugin,
      'vue': vuePlugin,
      'prettier': prettierPlugin,
    },
    rules: {
      'camelcase': ['error', { properties: 'always' }],
      'semi': ['error', 'never'],
      'padding-line-between-statements': ['error', { blankLine: 'always', prev: '*', next: 'return' }],
      'arrow-body-style': ['error', 'always'],
      'no-comment-inline': 'off',
      'no-inline-comments': 'off',
      'spaced-comment': 'off',
      'line-comment-position': 'off',
      'no-warning-comments': 'off',
      'multiline-comment-style': 'off',
      'no-multi-spaces': 'error',
      'vue/multi-word-component-names': 'off',
      'vue/max-attributes-per-line': 'off',
      'vue/html-indent': ['error', 2],
      'vue/script-indent': 'off',
      'vue/singleline-html-element-content-newline': 'off',
      'vue/no-v-html': 'off',
      'vue/no-unused-components': 'error',
      'vue/block-lang': ['error', { script: { lang: 'ts' } }],
      'vue/component-api-style': ['error', ['script-setup']],
      'no-console': 'off',
      'no-debugger': 'error',
      'no-trailing-spaces': 'error',
      '@typescript-eslint/no-explicit-any': 'off',
      '@typescript-eslint/no-unused-vars': 'off',
      '@typescript-eslint/no-empty-function': 'off',
      '@typescript-eslint/ban-ts-comment': 'off',
    },
  },
  {
    files: ['assets/**/*.vue'],
    rules: {
      'vue/html-closing-bracket-spacing': 'off',
      'prettier/prettier': [
        'error',
        {
          semi: false,
          singleQuote: true,
          tabWidth: 2,
          trailingComma: 'all',
          printWidth: 120,
          endOfLine: 'auto',
          bracketSpacing: true,
          vueIndentScriptAndStyle: false,
        },
      ],
    },
  },
  {
    files: ['assets/**/*.ts'],
    rules: {
      'prettier/prettier': [
        'error',
        {
          semi: false,
          singleQuote: true,
          tabWidth: 2,
          trailingComma: 'all',
          printWidth: 120,
          endOfLine: 'auto',
          bracketSpacing: true,
          vueIndentScriptAndStyle: false,
        },
      ],
    },
  },
];

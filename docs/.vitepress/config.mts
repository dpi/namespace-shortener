import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Namespace Shortener',
  description: 'A coding standard rule that imports namespace roots and rewrites usages to go through them.',
  cleanUrls: true,
  // Set by the docs workflow, since GitHub Pages may serve the site from a subpath.
  base: process.env.DOCS_BASE || '/',
  themeConfig: {
    nav: [
      { text: 'Guide', link: '/guide/getting-started' },
      { text: 'Tools', link: '/tools/' },
      { text: 'Reference', link: '/reference/terminology' },
      { text: 'Contributing', link: '/contributing/packages' },
    ],
    sidebar: [
      {
        text: 'Guide',
        items: [
          { text: 'Getting started', link: '/guide/getting-started' },
          { text: 'Configuring import roots', link: '/guide/configuration' },
          { text: 'What gets rewritten', link: '/guide/what-gets-rewritten' },
          { text: 'Errors', link: '/guide/errors' },
          { text: 'Working with other rules', link: '/guide/other-rules' },
        ],
      },
      {
        text: 'Tools',
        items: [
          { text: 'Supported tools', link: '/tools/' },
          { text: 'PHP-CS-Fixer', link: '/tools/php-cs-fixer' },
          { text: 'PHP_CodeSniffer', link: '/tools/php-codesniffer' },
        ],
      },
      {
        text: 'Reference',
        items: [{ text: 'Terminology', link: '/reference/terminology' }],
      },
      {
        text: 'Contributing',
        items: [
          { text: 'Packages', link: '/contributing/packages' },
          { text: 'Code style', link: '/contributing/code-style' },
          { text: 'Testing', link: '/contributing/testing' },
        ],
      },
    ],
    search: { provider: 'local' },
  },
})

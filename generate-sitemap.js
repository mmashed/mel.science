#!/usr/bin/env node
/**
 * Сканирует .html-файлы в корне сайта и генерирует sitemap.xml.
 * Запускать при добавлении новых страниц: node generate-sitemap.js
 */

const fs = require('fs');
const path = require('path');

const DOMAIN = 'https://science-kids.ru';
const DIR = __dirname;

// Страницы с нестандартными настройками (changefreq, priority)
const PAGE_OVERRIDES = {
  'index.html': { changefreq: 'weekly', priority: '1.0' },
};

// Файлы и директории, которые не должны попасть в sitemap
const EXCLUDED = new Set(['404.html']);
const EXCLUDED_DIRS = new Set(['reference']);

function getHtmlFiles(dir) {
  return fs.readdirSync(dir, { withFileTypes: true })
    .filter(entry => {
      if (entry.isDirectory()) return false;
      if (!entry.name.endsWith('.html')) return false;
      if (EXCLUDED.has(entry.name)) return false;
      return true;
    })
    .map(entry => entry.name);
}

function fileToUrl(filename) {
  if (filename === 'index.html') return '/';
  return '/' + filename.replace(/\.html$/, '');
}

function today() {
  return new Date().toISOString().slice(0, 10);
}

const files = getHtmlFiles(DIR).sort((a, b) => {
  if (a === 'index.html') return -1;
  if (b === 'index.html') return 1;
  return a.localeCompare(b);
});
const date = today();

const urls = files.map(file => {
  const url = fileToUrl(file);
  const override = PAGE_OVERRIDES[file] || {};
  const changefreq = override.changefreq || 'monthly';
  const priority = override.priority || '0.7';
  return `  <url>
    <loc>${DOMAIN}${url}</loc>
    <lastmod>${date}</lastmod>
    <changefreq>${changefreq}</changefreq>
    <priority>${priority}</priority>
  </url>`;
});

const xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${urls.join('\n')}
</urlset>
`;

const outPath = path.join(DIR, 'sitemap.xml');
fs.writeFileSync(outPath, xml, 'utf8');
console.log(`sitemap.xml обновлён (${files.length} страниц):`, files.map(fileToUrl));

// Синхронизируем домен в robots.txt
const robotsPath = path.join(DIR, 'robots.txt');
if (fs.existsSync(robotsPath)) {
  let robots = fs.readFileSync(robotsPath, 'utf8');
  robots = robots.replace(/^Sitemap: .+$/m, `Sitemap: ${DOMAIN}/sitemap.xml`);
  fs.writeFileSync(robotsPath, robots, 'utf8');
}

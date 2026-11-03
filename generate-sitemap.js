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
  'chemistry.html': { changefreq: 'weekly', priority: '0.9' },
  'physics.html': { changefreq: 'weekly', priority: '0.9' },
  'medicine.html': { changefreq: 'weekly', priority: '0.9' },
  'chemistry/bundle-pro-8.html': { changefreq: 'weekly', priority: '0.8' },
  'chemistry/bundle-fire-8.html': { changefreq: 'weekly', priority: '0.8' },
  'chemistry/bundle-pro-10.html': { changefreq: 'weekly', priority: '0.8' },
  'chemistry/bundle-combo-12.html': { changefreq: 'weekly', priority: '0.8' },
  'chemistry/bundle-super-14.html': { changefreq: 'weekly', priority: '0.8' },
  'medicine/pharmacy.html': { changefreq: 'weekly', priority: '0.8' },
  'public-offer.html': { changefreq: 'yearly', priority: '0.3' },
  'privacy.html': { changefreq: 'yearly', priority: '0.3' },
  'payment.html': { changefreq: 'yearly', priority: '0.3' },
  'delivery.html': { changefreq: 'yearly', priority: '0.3' },
};

// Файлы и директории, которые не должны попасть в sitemap
const EXCLUDED = new Set(['404.html', 'payment-success.html', 'old-catalog.html']);
// «интеграция оплаты» — скачанная документация банка, лежит только локально
const EXCLUDED_DIRS = new Set(['reference', 'интеграция оплаты']);

function getHtmlFiles(dir, base = '') {
  let results = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const relPath = base ? `${base}/${entry.name}` : entry.name;
    if (entry.isDirectory()) {
      if (EXCLUDED_DIRS.has(entry.name)) continue;
      results = results.concat(getHtmlFiles(path.join(dir, entry.name), relPath));
      continue;
    }
    if (!entry.name.endsWith('.html')) continue;
    if (EXCLUDED.has(relPath)) continue;
    results.push(relPath);
  }
  return results;
}

function fileToUrl(filename) {
  if (filename === 'index.html') return '/';
  return '/' + filename.replace(/\.html$/, '');
}

function today() {
  return new Date().toISOString().slice(0, 10);
}

/**
 * Даты изменения уже перечисленных страниц берём из текущего sitemap.xml:
 * иначе каждый запуск сдвигал бы lastmod у всех страниц сразу, хотя
 * менялась одна. Новые страницы получают сегодняшнюю дату.
 */
function readExistingLastmod() {
  const file = path.join(DIR, 'sitemap.xml');
  if (!fs.existsSync(file)) return {};
  const xml = fs.readFileSync(file, 'utf8');
  const map = {};
  const re = /<loc>([^<]+)<\/loc>\s*<lastmod>([^<]+)<\/lastmod>/g;
  let m;
  while ((m = re.exec(xml)) !== null) {
    map[m[1].replace(DOMAIN, '')] = m[2];
  }
  return map;
}

const files = getHtmlFiles(DIR).sort((a, b) => {
  if (a === 'index.html') return -1;
  if (b === 'index.html') return 1;
  return a.localeCompare(b);
});
const date = today();
const knownLastmod = readExistingLastmod();

const urls = files.map(file => {
  const url = fileToUrl(file);
  const override = PAGE_OVERRIDES[file] || {};
  const changefreq = override.changefreq || 'monthly';
  const priority = override.priority || '0.7';
  return `  <url>
    <loc>${DOMAIN}${url}</loc>
    <lastmod>${knownLastmod[url] || date}</lastmod>
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

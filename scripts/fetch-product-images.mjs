#!/usr/bin/env node
import fs from 'node:fs/promises';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..');
const migration = await fs.readFile(path.join(root, 'database/migrations/2026_09_03_productos_precios_stock.sql'), 'utf8');
const outputDir = path.join(root, 'storage/product-images');
const manifestPath = path.join(outputDir, 'sources.json');
const swapSql = await fs.readFile(path.join(root, 'database/migrations/2026_09_04_productos_usd_categorias_codigos.sql'), 'utf8');
const swapIds = new Set([...swapSql.matchAll(/odoo-row-(\d+)/g)].map(match => Number(match[1])));

function sqlFields(text) {
  const fields = [];
  let value = '';
  let quoted = false;
  let depth = 0;
  for (let index = 0; index < text.length; index += 1) {
    const char = text[index];
    if (quoted && char === "'" && text[index + 1] === "'") { value += "'"; index += 1; }
    else if (char === "'") quoted = !quoted;
    else if (!quoted && char === '(') { depth += 1; value += char; }
    else if (!quoted && char === ')') { depth -= 1; value += char; }
    else if (!quoted && depth === 0 && char === ',') { fields.push(value.trim()); value = ''; }
    else value += char;
  }
  fields.push(value.trim());
  return fields;
}

const normalize = value => value.toLowerCase().replace(/[^a-z0-9]+/g, '');
const decodeEntities = value => value.replaceAll('&quot;', '"').replaceAll('&amp;', '&').replaceAll('&#39;', "'");
function extension(contentType, bytes) {
  if (contentType.includes('webp') || bytes.subarray(0, 4).toString() === 'RIFF') return 'webp';
  if (contentType.includes('png') || bytes.subarray(1, 4).toString() === 'PNG') return 'png';
  if (contentType.includes('jpeg') || (bytes[0] === 0xff && bytes[1] === 0xd8)) return 'jpg';
  return null;
}

const products = [];
for (const line of migration.split('\n')) {
  const match = line.match(/^INSERT INTO products\([^)]*\) VALUES\((.*)\) ON DUPLICATE/);
  if (!match) continue;
  const fields = sqlFields(match[1]);
  const id = Number(fields[0]);
  let sku = fields[3] === 'NULL' ? '' : fields[3];
  let name = fields[4];
  if (swapIds.has(id)) [sku, name] = [name, sku];
  if (sku && fields[9] === 'NULL' && fields[2] !== 'servicio') products.push({id, sku, name});
}

await fs.mkdir(outputDir, {recursive: true});
let manifest = {};
try { manifest = JSON.parse(await fs.readFile(manifestPath, 'utf8')); } catch {}

for (const [position, product] of products.entries()) {
  if (manifest[product.id]?.status === 'matched') continue;
  const query = `"${product.sku}" ${product.name}`;
  console.log(`[${position + 1}/${products.length}] ${product.sku} — ${product.name}`);
  try {
    const response = await fetch(`https://www.bing.com/images/search?q=${encodeURIComponent(query)}&first=1`, {headers: {'user-agent': 'Mozilla/5.0'}});
    const html = await response.text();
    const candidates = [...html.matchAll(/ class="iusc"[^>]* m="([^"]+)"/g)].map(match => {
      try { return JSON.parse(decodeEntities(match[1])); } catch { return null; }
    }).filter(Boolean);
    const exact = normalize(product.sku);
    const candidate = candidates.find(item => normalize(`${item.t || ''} ${item.desc || ''} ${item.purl || ''}`).includes(exact));
    if (!candidate) { manifest[product.id] = {...product, status: 'not_found', query}; continue; }
    const imageResponse = await fetch(candidate.murl, {headers: {'user-agent': 'Mozilla/5.0'}, redirect: 'follow'});
    if (!imageResponse.ok) throw new Error(`imagen HTTP ${imageResponse.status}`);
    const bytes = Buffer.from(await imageResponse.arrayBuffer());
    const ext = extension(imageResponse.headers.get('content-type') || '', bytes);
    if (!ext || bytes.length < 8000 || bytes.length > 4_194_304) throw new Error('archivo no válido o tamaño inadecuado');
    for (const oldExt of ['jpg', 'png', 'webp']) if (oldExt !== ext) await fs.rm(path.join(outputDir, `${product.id}.${oldExt}`), {force: true});
    await fs.writeFile(path.join(outputDir, `${product.id}.${ext}`), bytes);
    manifest[product.id] = {...product, status: 'matched', query, source_page: candidate.purl, image_url: candidate.murl, title: candidate.t, file: `${product.id}.${ext}`};
  } catch (error) { manifest[product.id] = {...product, status: 'error', query, error: error.message}; }
  await fs.writeFile(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`);
}

await fs.writeFile(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`);
console.log(`Finalizado: ${Object.values(manifest).filter(item => item.status === 'matched').length} imágenes con coincidencia exacta.`);

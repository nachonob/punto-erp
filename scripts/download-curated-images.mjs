#!/usr/bin/env node
import fs from 'node:fs/promises';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..');
const dir = path.join(root, 'storage/product-images');
const curated = JSON.parse(await fs.readFile(path.join(dir, 'sources-curated.json'), 'utf8'));

function extension(contentType, bytes) {
  if (contentType.includes('webp') || bytes.subarray(0, 4).toString() === 'RIFF') return 'webp';
  if (contentType.includes('png') || bytes.subarray(1, 4).toString() === 'PNG') return 'png';
  if (contentType.includes('jpeg') || bytes.subarray(0, 2).equals(Buffer.from([0xff, 0xd8]))) return 'jpg';
  return null;
}

async function download([id, item]) {
  try {
    const response = await fetch(item.image_url, {headers: {'user-agent': 'Mozilla/5.0'}, redirect: 'follow'});
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const bytes = Buffer.from(await response.arrayBuffer());
    const ext = extension(response.headers.get('content-type') || '', bytes);
    if (!ext || bytes.length < 8000 || bytes.length > 4_194_304) throw new Error(`archivo inválido (${bytes.length} bytes)`);
    for (const oldExt of ['jpg', 'png', 'webp']) if (oldExt !== ext) await fs.rm(path.join(dir, `${id}.${oldExt}`), {force: true});
    await fs.writeFile(path.join(dir, `${id}.${ext}`), bytes);
    return [id, {...item, status: 'matched', file: `${id}.${ext}`, bytes: bytes.length}];
  } catch (error) {
    return [id, {...item, status: 'error', error: error.message}];
  }
}

const entries = Object.entries(curated);
const results = [];
for (let index = 0; index < entries.length; index += 8) {
  results.push(...await Promise.all(entries.slice(index, index + 8).map(download)));
  console.log(`${Math.min(index + 8, entries.length)}/${entries.length}`);
}
await fs.writeFile(path.join(dir, 'sources.json'), `${JSON.stringify(Object.fromEntries(results), null, 2)}\n`);
const ok = results.filter(([, item]) => item.status === 'matched').length;
console.log(`Descargadas: ${ok}; fallidas: ${results.length - ok}`);

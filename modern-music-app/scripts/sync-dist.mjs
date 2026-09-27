#!/usr/bin/env node
/**
 * sync-dist.mjs — Post-build sync of the Aether Vite output into MAMP's served document root.
 *
 * Some setups already build directly inside the MAMP htdocs tree (this repo IS the docroot),
 * in which case there is nothing to copy. Others build in a separate working copy and need the
 * compiled dist/ mirrored into /Applications/MAMP/htdocs (or a custom MAMP root) so the browser
 * gets the new build without a manual drag-and-drop or `cp` command.
 *
 * Configure the target with the MAMP_HTDOCS_TARGET env var, e.g.:
 *   MAMP_HTDOCS_TARGET=/Applications/MAMP/htdocs npm run build:sync
 *
 * If unset, we fall back to the conventional MAMP default path. If that path doesn't exist
 * either, or already IS this project (no-op), we skip the copy without failing the build.
 */
import { existsSync, realpathSync, cpSync, mkdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(__dirname, '..'); // modern-music-app/
const distDir = join(projectRoot, 'dist');

const DEFAULT_MAMP_ROOT = '/Applications/MAMP/htdocs';
const target = process.env.MAMP_HTDOCS_TARGET || DEFAULT_MAMP_ROOT;

function warn(msg) {
  console.warn(`\n[sync-dist] ${msg}\n`);
}

if (!existsSync(distDir)) {
  warn(`dist/ not found at ${distDir} — run "vite build" first. Skipping sync.`);
  process.exit(0);
}

if (!existsSync(target)) {
  warn(
    `MAMP document root "${target}" was not found on this machine.\n` +
    `[ACTION REQUIRED: MAMP Document Root] -> Set MAMP_HTDOCS_TARGET to the real MAMP ` +
    `htdocs path on the home server (check MAMP > Preferences > Web Server), e.g.:\n` +
    `  MAMP_HTDOCS_TARGET=/Applications/MAMP/htdocs npm run build:sync\n` +
    `Skipping sync — the build output is still available at ${distDir}.`
  );
  process.exit(0);
}

// Figure out where THIS project already lives relative to the target. If the target root
// already contains (or IS) this project, dist/ is already served in place — no copy needed.
let alreadyInPlace = false;
try {
  const realProjectRoot = realpathSync(projectRoot);
  const realTarget = realpathSync(target);
  alreadyInPlace = realProjectRoot === realTarget || realProjectRoot.startsWith(realTarget + '/');
} catch {
  alreadyInPlace = false;
}

if (alreadyInPlace) {
  console.log(`[sync-dist] Project already lives under the MAMP document root (${target}). dist/ is served in place — nothing to sync.`);
  process.exit(0);
}

const destDir = join(target, 'modern-music-app', 'dist');
mkdirSync(destDir, { recursive: true });
cpSync(distDir, destDir, { recursive: true, force: true });
console.log(`[sync-dist] Synced ${distDir} -> ${destDir}`);

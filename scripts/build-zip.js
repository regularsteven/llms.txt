const { execSync } = require('child_process');
const { version } = require('../package.json');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const distDir = path.join(root, 'dist');

if (!fs.existsSync(distDir)) {
    fs.mkdirSync(distDir);
}

const zipName = `wp-ai-visibility-manager-${version}.zip`;
const zipPath = path.join(distDir, zipName);

if (fs.existsSync(zipPath)) {
    fs.unlinkSync(zipPath);
}

execSync(`zip -r "${zipPath}" wp-ai-visibility-manager/`, {
    cwd: root,
    stdio: 'inherit',
});

const stats = fs.statSync(zipPath);
console.log(`\nBuilt: dist/${zipName} (${(stats.size / 1024).toFixed(1)} KB)`);

const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const { version } = require('../package.json');

// Sync to plugin PHP header
const phpFile = path.join(root, 'wp-ai-visibility-manager', 'wp-ai-visibility-manager.php');
if (fs.existsSync(phpFile)) {
    let php = fs.readFileSync(phpFile, 'utf8');
    php = php.replace(/^ \* Version:\s+.+$/m, ` * Version:           ${version}`);
    php = php.replace(/^define\(\s*'AIVM_VERSION',\s*'[^']+'\s*\);$/m, `define('AIVM_VERSION', '${version}');`);
    fs.writeFileSync(phpFile, php, 'utf8');
    console.log(`Updated plugin PHP header to ${version}`);
}

// Sync to readme.txt
const readmeFile = path.join(root, 'wp-ai-visibility-manager', 'readme.txt');
if (fs.existsSync(readmeFile)) {
    let readme = fs.readFileSync(readmeFile, 'utf8');
    readme = readme.replace(/^Stable tag:\s+.+$/m, `Stable tag: ${version}`);
    fs.writeFileSync(readmeFile, readme, 'utf8');
    console.log(`Updated readme.txt stable tag to ${version}`);
}

console.log(`Version synced to ${version}`);

const fs = require('fs');
const path = require('path');

function walk(dir, callback) {
    const list = fs.readdirSync(dir);
    list.forEach(file => {
        file = path.join(dir, file);
        const stat = fs.statSync(file);
        if (stat && stat.isDirectory()) {
            walk(file, callback);
        } else {
            if (file.endsWith('.tsx')) callback(file);
        }
    });
}

function processFile(filePath) {
    let content = fs.readFileSync(filePath, 'utf8');
    let original = content;

    // Stronger Glassmorphism for normal dark/light containers
    content = content.replace(/bg-white\/85 dark:bg-tudu-content-dark\/85 backdrop-blur-md border border-white\/20 dark:border-white\/10/g, 'bg-white/30 dark:bg-tudu-content-dark/40 backdrop-blur-2xl border border-white/50 dark:border-white/10 shadow-[0_8px_32px_0_rgba(31,38,135,0.07)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.4)]');

    // Stronger Glassmorphism for column containers
    content = content.replace(/bg-white\/85 dark:bg-tudu-column-dark\/85 backdrop-blur-md border border-white\/20 dark:border-white\/10/g, 'bg-white/40 dark:bg-tudu-column-dark/40 backdrop-blur-2xl border border-white/50 dark:border-white/10 shadow-[0_8px_32px_0_rgba(31,38,135,0.07)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.4)]');

    // Stronger Glassmorphism for Top Navigation
    content = content.replace(/bg-gradient-to-r from-tudu-bg-light\/85 to-tudu-content-light\/85 dark:from-tudu-bg-dark\/85 dark:to-tudu-content-dark\/85 backdrop-blur-md border-white\/20 dark:border-white\/10/g, 'bg-white/40 dark:bg-tudu-bg-dark/50 backdrop-blur-2xl border-white/50 dark:border-white/10 shadow-[0_4px_30px_rgba(0,0,0,0.1)]');

    // In case there are some that were not caught or changed slightly
    if (content !== original) {
        fs.writeFileSync(filePath, content, 'utf8');
        console.log('Updated v2:', filePath);
    }
}

walk('c:/xampp/htdocs/tudu_haute_couture_tech/frontend/src/components', processFile);
walk('c:/xampp/htdocs/tudu_haute_couture_tech/frontend/src/layouts', processFile);
console.log('Done v2');

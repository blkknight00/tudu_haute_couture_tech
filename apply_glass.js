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

    if (!content.includes('backdrop-blur')) {
        content = content.replace(/bg-white dark:bg-tudu-content-dark/g, 'bg-white/85 dark:bg-tudu-content-dark/85 backdrop-blur-md border border-white/20 dark:border-white/10');
        content = content.replace(/bg-white dark:bg-tudu-column-dark/g, 'bg-white/85 dark:bg-tudu-column-dark/85 backdrop-blur-md border border-white/20 dark:border-white/10');
        content = content.replace(/bg-tudu-column-light dark:bg-tudu-column-dark/g, 'bg-tudu-column-light/80 dark:bg-tudu-column-dark/80 backdrop-blur-md border border-white/20 dark:border-white/10');
        content = content.replace(/bg-gradient-to-r from-tudu-bg-light to-tudu-content-light dark:from-tudu-bg-dark dark:to-tudu-content-dark/g, 'bg-gradient-to-r from-tudu-bg-light/85 to-tudu-content-light/85 dark:from-tudu-bg-dark/85 dark:to-tudu-content-dark/85 backdrop-blur-md border-white/20 dark:border-white/10');
    }

    if (content !== original) {
        fs.writeFileSync(filePath, content, 'utf8');
        console.log('Updated:', filePath);
    }
}

walk('c:/xampp/htdocs/tudu_haute_couture_tech/frontend/src/components', processFile);
walk('c:/xampp/htdocs/tudu_haute_couture_tech/frontend/src/layouts', processFile);
walk('c:/xampp/htdocs/tudu_haute_couture_tech/frontend/src/pages', processFile);
console.log('Done');

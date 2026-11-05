const {defineConfig} = require('@playwright/test');
module.exports = defineConfig({
    testDir: './tests/browser',
    use: {baseURL:'http://127.0.0.1:8764', browserName:'chromium'},
    webServer: {command:'php -S 127.0.0.1:8764 -t . tests/pdf-guide-demo.php',url:'http://127.0.0.1:8764/tests/editor-fixture.php',reuseExistingServer:!process.env.CI},
    reporter:'list'
});

const path = require('path');
const fs = require('fs');

const chromePathDarwin = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const resolvedChromePath = process.env.PLAYWRIGHT_CHROME_PATH
  || (fs.existsSync(chromePathDarwin) ? chromePathDarwin : undefined);

module.exports = {
  testDir: __dirname,
  testMatch: ['*.spec.js'],
  timeout: 30_000,
  expect: {
    timeout: 5_000,
  },
  reporter: 'list',
  use: {
    baseURL: 'http://127.0.0.1:9088',
    headless: true,
    viewport: { width: 1440, height: 1100 },
    launchOptions: resolvedChromePath
      ? { executablePath: resolvedChromePath, args: ['--no-sandbox', '--disable-dev-shm-usage'] }
      : { args: ['--no-sandbox', '--disable-dev-shm-usage'] },
  },
  webServer: {
    command: 'php -S 127.0.0.1:9088 -t ../..',
    port: 9088,
    reuseExistingServer: true,
    cwd: __dirname,
    timeout: 30_000,
  },
  outputDir: path.join(__dirname, 'test-results'),
};

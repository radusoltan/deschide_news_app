import { chromium } from '@playwright/test';

async function verifyLogoAlignment() {
  console.log('Starting logo alignment verification...');

  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 }
  });
  const page = await context.newPage();

  try {
    // Navigate to homepage
    console.log('Navigating to http://localhost:3005...');
    await page.goto('http://localhost:3005', { waitUntil: 'networkidle' });

    // Wait for logo to be visible
    await page.waitForSelector('svg[viewBox="0 0 1000 854.25"]', { timeout: 10000 });

    // Take screenshot of header
    console.log('Taking header screenshot...');
    const header = await page.locator('header').first();
    await header.screenshot({ path: '/tmp/logo-header-alignment.png' });
    console.log('Header screenshot saved to /tmp/logo-header-alignment.png');

    // Scroll to footer
    console.log('Scrolling to footer...');
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(1000);

    // Take screenshot of footer
    console.log('Taking footer screenshot...');
    const footer = await page.locator('footer').first();
    await footer.screenshot({ path: '/tmp/logo-footer-alignment.png' });
    console.log('Footer screenshot saved to /tmp/logo-footer-alignment.png');

    // Take full page screenshot for reference
    await page.screenshot({ path: '/tmp/logo-full-page.png', fullPage: true });
    console.log('Full page screenshot saved to /tmp/logo-full-page.png');

    console.log('\n✅ Verification complete!');
    console.log('Screenshots saved:');
    console.log('  - /tmp/logo-header-alignment.png');
    console.log('  - /tmp/logo-footer-alignment.png');
    console.log('  - /tmp/logo-full-page.png');

  } catch (error) {
    console.error('❌ Error during verification:', error.message);
    throw error;
  } finally {
    await browser.close();
  }
}

verifyLogoAlignment().catch(console.error);

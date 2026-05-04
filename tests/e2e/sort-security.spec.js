const { test, expect } = require('@playwright/test');

test('Sorting parameters are neutralized in User Admin', async ({ page }) => {
  // Login
  await page.goto('/auth_login.php');
  await page.fill('#login_username', 'admin');
  await page.fill('#login_password', 'admin');
  await page.click('#login_button');

  // Navigate to User Admin with malicious sort
  await page.goto('/user_admin.php?sort_column=username%60%20OR%201%3D1&sort_direction=ASC%20--');
  
  // Verify page still loads and doesn't show injection results
  await expect(page.locator('.tableHeader')).toBeVisible();
  const bodyText = await page.innerText('body');
  expect(bodyText).not.toContain('OR 1=1');
});

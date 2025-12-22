const { test, expect } = require('@playwright/test');

test.describe('Family Mode Roles', () => {
  test('Creator role should display the creator view', async ({ page }) => {
    await page.goto('http://localhost:8080/family.html?room_id=1&role=creator&testing=true');
    await expect(page.locator('#creator-view')).toBeVisible();
    await page.screenshot({ path: 'tests/screenshots/creator_view.png' });
  });

  test('Songlist role should display the song list', async ({ page }) => {
    await page.goto('http://localhost:8080/family.html?room_id=1&role=songlist&testing=true');
    await expect(page.locator('#songlist-view')).toBeVisible();
    await page.screenshot({ path: 'tests/screenshots/songlist_view.png' });
  });

  test('Number Pad role should display the number pad', async ({ page }) => {
    await page.goto('http://localhost:8080/family.html?room_id=1&role=number_pad&testing=true');
    await expect(page.locator('#number-pad-view')).toBeVisible();
    await page.screenshot({ path: 'tests/screenshots/number_pad_view.png' });
  });
});

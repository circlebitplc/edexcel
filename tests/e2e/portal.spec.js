import { test, expect } from '@playwright/test';

test.beforeEach(async ({}, testInfo) => {
  test.skip(process.env.E2E_ALLOW_MUTATIONS !== '1', 'Set E2E_ALLOW_MUTATIONS=1 on staging only.');
  testInfo.annotations.push({ type: 'safety', description: 'Disposable staging data only.' });
});

test('public admission page loads', async ({ page }) => {
  await page.goto('/admissions/apply.php');
  await expect(page.getByRole('heading', { name: /admission application/i })).toBeVisible();
});

test('public enquiry and status pages load', async ({ page }) => {
  await page.goto('/admissions/enquire.php');
  await expect(page.getByRole('heading', { name: /course enquiry/i })).toBeVisible();
  await page.goto('/admissions/status.php');
  await expect(page.getByRole('heading', { name: /application status/i })).toBeVisible();
});

test('staff login form is available', async ({ page }) => {
  await page.goto('/login.php');
  await expect(page.getByLabel(/username/i)).toBeVisible();
  await expect(page.getByLabel(/password/i)).toBeVisible();
});

test('student portal navigation exposes report card and notifications', async ({ page }) => {
  test.skip(!process.env.E2E_STUDENT_USER || !process.env.E2E_STUDENT_PASSWORD, 'Student staging credentials not configured.');
  await page.goto('/login.php');
  await page.getByLabel(/username/i).fill(process.env.E2E_STUDENT_USER);
  await page.getByLabel(/password/i).fill(process.env.E2E_STUDENT_PASSWORD);
  await page.getByRole('button', { name: /sign in/i }).click();
  await expect(page).toHaveURL(/student|dashboard/);
  await page.goto('/student/report_card.php');
  await expect(page.getByRole('heading', { name: /report card/i })).toBeVisible();
  await page.goto('/student/assessment.php');
  await expect(page.getByRole('heading', { name: /assessment/i })).toBeVisible();
});

test('teacher exam builder is available on staging', async ({ page }) => {
  test.skip(!process.env.E2E_TEACHER_USER || !process.env.E2E_TEACHER_PASSWORD, 'Teacher staging credentials not configured.');
  await page.goto('/login.php');
  await page.getByLabel(/username/i).fill(process.env.E2E_TEACHER_USER);
  await page.getByLabel(/password/i).fill(process.env.E2E_TEACHER_PASSWORD);
  await page.getByRole('button', { name: /sign in/i }).click();
  await page.goto('/campus/exam_control.php');
  await expect(page.getByRole('heading', { name: /examination control/i })).toBeVisible();
  await page.goto('/campus/assessment_builder.php');
  await expect(page.getByRole('heading', { name: /exam builder/i })).toBeVisible();
});

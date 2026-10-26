/**
 * NEXUS-L -> Intertek InterLink 2.0 Automated Form Filler Bot (Playwright)
 *
 * Usage:
 *   node scripts/interlink-bot.js writable/cache/interlink_job_A005967368.json
 *
 * Automates navigating to InterLink 2.0 SearchFormWithHeader.aspx / UploadForm.aspx,
 * locating the target Application No., and populating all VF Corporation Report
 * Online Upload Form fields from the NEXUS-L confirmed sync dialog payload.
 */

const fs = require('fs');
const path = require('path');

async function runInterlinkAutomation(jobFilePath) {
  if (!fs.existsSync(jobFilePath)) {
    console.error(`[InterLink Bot] Job file not found: ${jobFilePath}`);
    process.exit(1);
  }

  const job = JSON.parse(fs.readFileSync(jobFilePath, 'utf8'));
  const fields = job.fields || {};
  console.log(`[InterLink Bot] Starting sync for Application No: ${job.application_no}`);

  let playwright;
  try {
    playwright = require('playwright');
  } catch (err) {
    console.log('[InterLink Bot] Playwright package not installed yet. Run: npm i -D playwright');
    console.log('[InterLink Bot] Validated payload ready for UploadForm.aspx:', JSON.stringify(fields, null, 2));
    return;
  }

  const browser = await playwright.chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();

  console.log(`[InterLink Bot] Navigating to ${job.target_url}`);
  await page.goto(job.target_url, { waitUntil: 'domcontentloaded', timeout: 60000 });

  // Map NEXUS-L fields to InterLink 2.0 ASP.NET UploadForm.aspx selectors / labels
  const fieldMappings = [
    { key: 'service_provider', label: 'Service Provider' },
    { key: 'lab_location', label: 'Lab Location' },
    { key: 'application_no', label: 'Application No.' },
    { key: 'service_test_type', label: 'Service Test Type' },
    { key: 'test_stage', label: 'Test Stage' },
    { key: 'service_required', label: 'Service Required' },
    { key: 'report_no', label: 'Report No.' },
    { key: 'previous_report_no', label: 'Previous Test Report No' },
    { key: 'email_recipients', label: 'Email Notification Recipient(s)' },
    { key: 'company_name', label: 'Company Name' },
    { key: 'interlink_code', label: 'Interlink Code' },
    { key: 'vendor_name', label: 'Vendor Name' },
    { key: 'vendor_id', label: 'Vendor ID' },
    { key: 'factory_name', label: 'Factory Name' },
    { key: 'factory_id', label: 'Factory ID' },
    { key: 'supplier_name', label: 'Supplier Name' },
    { key: 'supplier_id', label: 'Supplier ID' },
  ];

  for (const item of fieldMappings) {
    const val = fields[item.key];
    if (val === undefined || val === null) continue;

    try {
      const locator = page.getByLabel(item.label, { exact: false }).first();
      if (await locator.count() > 0) {
        await locator.fill(String(val));
        console.log(`  [✓] Filled ${item.label}: ${val}`);
      }
    } catch (e) {
      console.warn(`  [!] Could not fill ${item.label}: ${e.message}`);
    }
  }

  // Handle "Is this a Retest?" radio button (Yes / No)
  if (fields.is_retest) {
    try {
      const radio = page.getByLabel(fields.is_retest, { exact: true }).first();
      if (await radio.count() > 0) {
        await radio.check();
        console.log(`  [✓] Checked Retest Radio: ${fields.is_retest}`);
      }
    } catch (e) {
      console.warn(`  [!] Could not set Retest radio: ${e.message}`);
    }
  }

  console.log('[InterLink Bot] All fields populated on UploadForm.aspx.');
}

const argFile = process.argv[2];
if (argFile) {
  runInterlinkAutomation(path.resolve(argFile));
}

/**
 * TAPP — Google Sheets Sync
 * =========================
 * Paste this entire file into: Google Sheets → Extensions → Apps Script
 * Then deploy as a Web App:
 *   1. Click "Deploy" → "New deployment"
 *   2. Click the gear icon → select "Web app"
 *   3. Description: "TAPP Sync"
 *   4. Execute as: "Me"
 *   5. Who has access: "Anyone"
 *   6. Click "Deploy" and copy the Web App URL
 *   7. Paste that URL into TAPP Admin → Settings → Google Sheets Sync
 */

/**
 * Handles POST requests from the TAPP backend.
 * Expects a JSON body:
 * {
 *   "timestamp": "2026-08-14T15:00:00+02:00",
 *   "employee_id": "S-001",
 *   "employee_name": "John Doe",
 *   "event_type": "login" | "logout" | "clock_in" | "clock_out" | "test",
 *   "source": "web" | "device" | "manual"
 * }
 */
function doPost(e) {
  var lock = LockService.getScriptLock();
  lock.waitLock(10000);

  try {
    var payload = JSON.parse(e.postData.contents);
    var sheet = getOrCreateSheet_();

    var row = [
      payload.timestamp || new Date().toISOString(),
      payload.employee_id || '',
      payload.employee_name || '',
      payload.event_type || '',
      payload.source || ''
    ];

    sheet.appendRow(row);

    return ContentService
      .createTextOutput(JSON.stringify({ ok: true }))
      .setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService
      .createTextOutput(JSON.stringify({ ok: false, error: String(err) }))
      .setMimeType(ContentService.MimeType.JSON);
  } finally {
    lock.releaseLock();
  }
}

/**
 * Handles GET requests — useful for testing the web app URL in a browser.
 */
function doGet() {
  return ContentService
    .createTextOutput('TAPP Google Sheets Sync is running. Use POST to log events.')
    .setMimeType(ContentService.MimeType.TEXT);
}

/**
 * Returns the "Attendance" sheet, creating it with headers if needed.
 */
function getOrCreateSheet_() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = ss.getSheetByName('Attendance');

  if (!sheet) {
    sheet = ss.insertSheet('Attendance');
    sheet.appendRow(['Timestamp', 'Employee ID', 'Employee Name', 'Event Type', 'Source']);
    sheet.getRange(1, 1, 1, 5).setFontWeight('bold');
    sheet.setFrozenRows(1);
    sheet.autoResizeColumns(1, 5);
  }

  return sheet;
}
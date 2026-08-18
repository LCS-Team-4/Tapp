# Google Sheets Integration Setup Guide

This guide walks you through connecting TAPP to a Google Sheet so that every **login**, **logout**, **clock-in**, and **clock-out** event is automatically appended as a row.

## How it works

TAPP's backend sends a POST request to a **Google Apps Script Web App URL**. The Apps Script appends a row to your spreadsheet. This is **100% free** — no Google Cloud project, no API keys, no paid plans.

## Step 1: Create a Google Sheet

1. Go to [sheets.new](https://sheets.new) (or open Google Sheets and create a new spreadsheet)
2. Name it something like **"TAPP Attendance"**
3. You don't need to create any columns — the script will do that automatically

## Step 2: Add the Apps Script

1. In your Google Sheet, click **Extensions → Apps Script**
2. Delete any existing code in the editor
3. Copy the entire contents of [`google-sheets/Code.gs`](../google-sheets/Code.gs) from this project
4. Paste it into the Apps Script editor
5. Click the **Save** icon (or press `Ctrl+S`)

## Step 3: Deploy as a Web App

1. In the Apps Script editor, click **Deploy → New deployment**
2. Click the **gear icon** (⚙️) next to "Select type"
3. Choose **Web app**
4. Fill in:
   - **Description**: `TAPP Sync`
   - **Execute as**: `Me` (this is important — it runs under your Google account)
   - **Who has access**: `Anyone`
5. Click **Deploy**
6. Google will ask you to **authorize** the script — click **Authorize access** and allow the permissions
7. Copy the **Web App URL** — it looks like:
   ```
   https://script.google.com/macros/s/AKfycb.../exec
   ```

## Step 4: Connect TAPP to the Google Sheet

1. Log in to the **TAPP Admin Portal**
2. Go to **Settings**
3. Find the **Google Sheets Sync** card
4. Paste the Web App URL into the **Google Apps Script Web App URL** field
5. Toggle **Enable Google Sheets Sync** to ON
6. Click **Save Sync Settings**
7. Click **Test Connection** — this sends a test event to your spreadsheet

## Step 5: Verify it works

1. Open your Google Sheet
2. You should see a new **"Attendance"** tab with a header row:
   ```
   Timestamp | Employee ID | Employee Name | Event Type | Source
   ```
3. The test event should appear as a row with `TEST` as the employee ID
4. Now log in / log out / clock in / clock out in TAPP — each event appends a new row

## Troubleshooting

| Problem | Fix |
|---------|-----|
| **Test Connection fails** | Make sure the URL starts with `https://script.google.com/macros/s/` and ends with `/exec`. Re-deploy the Apps Script if you changed the code. |
| **Rows not appearing** | Check that sync is enabled in Settings and the URL is saved. Check the TAPP backend log at `backend/storage/logs/app.log` for `GoogleSheetsService` errors. |
| **"Access denied" from Google** | Re-deploy the Web App and make sure "Who has access" is set to **Anyone**. |
| **Old URL stopped working** | If you edit the Apps Script code, you must **Deploy → Manage deployments → Edit → New version** to get a new URL. |

## What each event type means

| Event Type | When it fires |
|------------|---------------|
| `login` | User signs in to the TAPP portal |
| `logout` | User signs out of the TAPP portal |
| `clock_in` | User clocks in (web portal or RFID device) |
| `clock_out` | User clocks out (web portal or RFID device) |
| `test` | Test event from the "Test Connection" button |
import { chromium } from 'playwright';
import { db } from './store.js';

const row = db.prepare('SELECT COUNT(*) AS total FROM customer_sync').get();
console.log(`SQLITE_OK rows=${row.total}`);
const eventRow = db.prepare('SELECT COUNT(*) AS total FROM sync_events').get();
console.log(`HISTORY_OK events=${eventRow.total}`);
db.close();

const browser = await chromium.launch({ headless: true });
console.log('CHROMIUM_OK');
await browser.close();

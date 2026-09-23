import { chromium } from 'playwright';
import { db } from './store.js';

const row = db.prepare('SELECT COUNT(*) AS total FROM customer_sync').get();
console.log(`SQLITE_OK rows=${row.total}`);
db.close();

const browser = await chromium.launch({ headless: true });
console.log('CHROMIUM_OK');
await browser.close();


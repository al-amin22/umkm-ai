import { writeFileSync, readFileSync, existsSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';
import axios from 'axios';
import { logger } from './logger.js';

const __dirname    = dirname(fileURLToPath(import.meta.url));
const QUEUE_FILE   = join(__dirname, 'logs', 'pending_queue.json');
const MAX_RETRIES  = 5;
const RETRY_MS     = 30_000;

let queue   = [];
let running = false;

function persist() {
  try {
    writeFileSync(QUEUE_FILE, JSON.stringify(queue, null, 2));
  } catch (e) {
    logger.error('Queue', 'Gagal simpan queue ke disk', { err: e.message });
  }
}

function loadFromDisk() {
  if (!existsSync(QUEUE_FILE)) return;
  try {
    const data = JSON.parse(readFileSync(QUEUE_FILE, 'utf8'));
    queue = Array.isArray(data) ? data : [];
    if (queue.length > 0) {
      logger.info('Queue', `Loaded ${queue.length} pending message(s) from disk`);
    }
  } catch {
    queue = [];
  }
}

export function addToQueue(payload) {
  queue.push({ payload, retries: 0, addedAt: Date.now() });
  persist();
  logger.warn('Queue', 'Pesan ditambah ke antrian lokal', { wa: payload.wa_number });
}

async function flushQueue() {
  if (running || queue.length === 0) return;
  running = true;

  const url    = `${process.env.LARAVEL_URL}/api/wa/incoming`;
  const secret = process.env.WA_SERVICE_SECRET;
  const keep   = [];

  for (const item of queue) {
    try {
      await axios.post(url, { ...item.payload, secret }, { timeout: 10_000 });
      logger.info('Queue', 'Retry berhasil', { wa: item.payload.wa_number, retries: item.retries });
    } catch (err) {
      item.retries += 1;
      if (item.retries >= MAX_RETRIES) {
        logger.error('Queue', `Pesan dibuang setelah ${MAX_RETRIES} retry`, {
          wa:    item.payload.wa_number,
          error: err.message,
        });
      } else {
        keep.push(item);
      }
    }
  }

  queue = keep;
  persist();
  running = false;
}

export function startQueueRetry() {
  loadFromDisk();
  setInterval(flushQueue, RETRY_MS);
  logger.info('Queue', `Retry scheduler aktif (interval ${RETRY_MS / 1000}s)`);
}

export function queueSize() {
  return queue.length;
}

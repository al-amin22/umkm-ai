import 'dotenv/config';
import express from 'express';
import axios   from 'axios';
import { logger }           from './logger.js';
import { loadSession, ensureSessionDir } from './session-manager.js';
import { startReceiver }    from './receiver.js';
import { registerSenderRoutes } from './sender.js';
import { startQueueRetry, queueSize } from './queue.js';

const PORT         = parseInt(process.env.PORT ?? '3000', 10);
const LARAVEL_URL  = process.env.LARAVEL_URL;
const SECRET       = process.env.WA_SERVICE_SECRET;

// ── State ──────────────────────────────────────────────────────────
let sock         = null;
let isConnected  = false;
let retryCount   = 0;
const MAX_RETRY  = 10;

function getSock() { return isConnected ? sock : null; }

// ── Express App ───────────────────────────────────────────────────
const app = express();
app.use(express.json({ limit: '10mb' }));

app.get('/health', (_req, res) => {
  res.json({
    status:      isConnected ? 'connected' : 'disconnected',
    queue_size:  queueSize(),
    retry_count: retryCount,
    uptime:      Math.floor(process.uptime()),
    timestamp:   new Date().toISOString(),
  });
});

registerSenderRoutes(app, getSock);

// ── WhatsApp Connection ───────────────────────────────────────────
async function connect() {
  const {
    default: makeWASocket,
    DisconnectReason,
    fetchLatestBaileysVersion,
  } = await import('@whiskeysockets/baileys');

  ensureSessionDir();
  const { state, saveCreds } = await loadSession();
  const { version }          = await fetchLatestBaileysVersion();

  logger.info('WA', `Menggunakan Baileys versi ${version.join('.')}`);

  sock = makeWASocket({
    version,
    auth:           state,
    printQRInTerminal: true,
    logger: {
      level: 'silent',
      child: () => ({ level: 'silent', info: () => {}, warn: () => {}, error: () => {}, debug: () => {}, trace: () => {}, fatal: () => {} }),
      info:  () => {},
      warn:  () => {},
      error: () => {},
      debug: () => {},
      trace: () => {},
      fatal: () => {},
    },
    browser:        ['UMKM AI Platform', 'Chrome', '120.0.0'],
    connectTimeoutMs: 30_000,
    defaultQueryTimeoutMs: 20_000,
    keepAliveIntervalMs: 10_000,
    retryRequestDelayMs: 2_000,
  });

  // Simpan kredensial setiap kali berubah
  sock.ev.on('creds.update', saveCreds);

  // Handle status koneksi
  sock.ev.on('connection.update', async ({ connection, lastDisconnect, qr }) => {
    if (qr) {
      logger.info('WA', 'QR Code muncul di terminal — scan dengan WhatsApp');
    }

    if (connection === 'open') {
      isConnected = true;
      retryCount  = 0;
      logger.info('WA', 'Terhubung ke WhatsApp');
      await notifyLaravelConnected();
    }

    if (connection === 'close') {
      isConnected = false;
      const code  = lastDisconnect?.error?.output?.statusCode;
      const reason = DisconnectReason[code] ?? `kode ${code}`;

      logger.warn('WA', `Koneksi terputus (${reason})`);

      // 401 = logged out — jangan reconnect
      if (code === DisconnectReason.loggedOut || code === 401) {
        logger.error('WA', 'LOGOUT terdeteksi — hapus sesi dan restart manual', {
          instruksi: `Hapus folder sessions/default lalu jalankan ulang`,
        });
        await notifyLaravelLogout();
        return;
      }

      scheduleReconnect();
    }
  });

  // Mulai receiver pesan
  startReceiver(sock);

  logger.info('WA', 'Socket dibuat, menunggu koneksi...');
}

// ── Reconnect dengan exponential backoff ─────────────────────────
function scheduleReconnect() {
  if (retryCount >= MAX_RETRY) {
    logger.error('WA', `Gagal reconnect setelah ${MAX_RETRY} percobaan`);
    return;
  }

  // Exponential backoff: 5s, 10s, 20s, 40s, ... max 5 menit
  const baseDelay = 5_000;
  const delay     = Math.min(baseDelay * Math.pow(2, retryCount), 300_000);
  retryCount++;

  logger.info('WA', `Reconnect ke-${retryCount} dalam ${delay / 1000}s...`);
  setTimeout(connect, delay);
}

// ── Notifikasi ke Laravel ────────────────────────────────────────
async function notifyLaravelConnected() {
  try {
    await axios.post(
      `${LARAVEL_URL}/api/wa/status`,
      { status: 'connected', secret: SECRET },
      { timeout: 5_000 }
    );
  } catch {
    // Laravel mungkin belum siap — tidak perlu crash
  }
}

async function notifyLaravelLogout() {
  try {
    await axios.post(
      `${LARAVEL_URL}/api/wa/status`,
      { status: 'logged_out', secret: SECRET },
      { timeout: 5_000 }
    );
  } catch {
    // Tetap log meski Laravel tidak bisa diakses
  }
}

// ── Health check berkala ke Laravel ──────────────────────────────
function startHealthBeacon() {
  const INTERVAL = 5 * 60 * 1000; // 5 menit

  setInterval(async () => {
    try {
      await axios.post(
        `${LARAVEL_URL}/api/wa/heartbeat`,
        {
          status:     isConnected ? 'connected' : 'disconnected',
          queue_size: queueSize(),
          uptime:     Math.floor(process.uptime()),
          secret:     SECRET,
        },
        { timeout: 5_000 }
      );
      logger.debug('Health', 'Heartbeat terkirim ke Laravel');
    } catch {
      logger.warn('Health', 'Heartbeat gagal — Laravel tidak bisa diakses');
    }
  }, INTERVAL);

  logger.info('Health', `Health beacon ke Laravel setiap ${INTERVAL / 60000} menit`);
}

// ── Bootstrap ────────────────────────────────────────────────────
app.listen(PORT, () => {
  logger.info('Server', `wa-service berjalan di port ${PORT}`);
});

startQueueRetry();
startHealthBeacon();
connect().catch((err) => {
  logger.error('WA', 'Fatal error saat inisialisasi', { err: err.message });
  process.exit(1);
});

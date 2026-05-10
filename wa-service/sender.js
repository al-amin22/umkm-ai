import { logger } from './logger.js';

const SECRET           = process.env.WA_SERVICE_SECRET;
const RATE_LIMIT_MAX   = 10;   // max pesan per menit per nomor
const RATE_LIMIT_WINDOW = 60_000; // 1 menit dalam ms

// Map<waNumber, [timestamp, ...]>
const rateLimitMap = new Map();

function checkRateLimit(waNumber) {
  const now    = Date.now();
  const window = now - RATE_LIMIT_WINDOW;

  if (!rateLimitMap.has(waNumber)) {
    rateLimitMap.set(waNumber, []);
  }

  const timestamps = rateLimitMap.get(waNumber).filter((t) => t > window);
  rateLimitMap.set(waNumber, timestamps);

  if (timestamps.length >= RATE_LIMIT_MAX) {
    return false;
  }

  timestamps.push(now);
  return true;
}

function normalizeNumber(number) {
  // Pastikan format: 628xxx@s.whatsapp.net
  const clean = number.replace(/[^0-9]/g, '');
  const withCountry = clean.startsWith('0') ? '62' + clean.slice(1) : clean;
  return `${withCountry}@s.whatsapp.net`;
}

async function downloadFromUrl(url) {
  const { default: axios } = await import('axios');
  const response = await axios.get(url, { responseType: 'arraybuffer', timeout: 15_000 });
  return Buffer.from(response.data);
}

export function registerSenderRoutes(app, getSock) {
  app.post('/send', async (req, res) => {
    const { secret, to, pesan, media_url, caption } = req.body;

    // Validasi secret
    if (!secret || secret !== SECRET) {
      logger.warn('Sender', 'Unauthorized /send attempt');
      return res.status(401).json({ success: false, error: 'Unauthorized' });
    }

    // Validasi field wajib
    if (!to || (!pesan && !media_url)) {
      return res.status(400).json({ success: false, error: 'Field "to" dan "pesan" atau "media_url" wajib diisi' });
    }

    const sock = getSock();
    if (!sock) {
      return res.status(503).json({ success: false, error: 'WhatsApp belum terhubung' });
    }

    // Rate limiting
    if (!checkRateLimit(to)) {
      logger.warn('Sender', 'Rate limit tercapai', { to });
      return res.status(429).json({
        success: false,
        error:   `Rate limit: maksimal ${RATE_LIMIT_MAX} pesan per menit per nomor`,
      });
    }

    const jid = normalizeNumber(to);

    try {
      if (media_url) {
        // Kirim gambar dengan caption
        const imageBuffer = await downloadFromUrl(media_url);
        await sock.sendMessage(jid, {
          image:   imageBuffer,
          caption: caption ?? pesan ?? '',
        });
        logger.info('Sender', 'Gambar terkirim', { to: jid });
      } else {
        // Kirim pesan teks
        await sock.sendMessage(jid, { text: pesan });
        logger.info('Sender', 'Teks terkirim', { to: jid, preview: pesan.slice(0, 50) });
      }

      return res.json({ success: true, to: jid });
    } catch (err) {
      logger.error('Sender', 'Gagal kirim pesan', { to: jid, error: err.message });
      return res.status(500).json({
        success: false,
        error:   err.message,
        to:      jid,
      });
    }
  });

  logger.info('Sender', 'Route POST /send terdaftar');
}

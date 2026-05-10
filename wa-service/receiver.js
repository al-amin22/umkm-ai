import axios from 'axios';
import { logger } from './logger.js';
import { addToQueue } from './queue.js';

const LARAVEL_URL = process.env.LARAVEL_URL;
const SECRET      = process.env.WA_SERVICE_SECRET;

async function downloadMedia(sock, msg) {
  try {
    const { downloadMediaMessage } = await import('@whiskeysockets/baileys');
    const buffer = await downloadMediaMessage(
      msg,
      'buffer',
      {},
      { logger: { level: 'silent', child: () => ({ level: 'silent' }) } }
    );
    return buffer ? buffer.toString('base64') : null;
  } catch (err) {
    logger.warn('Receiver', 'Gagal download media', { err: err.message });
    return null;
  }
}

function extractText(message) {
  return (
    message.conversation                           ||
    message.extendedTextMessage?.text              ||
    message.imageMessage?.caption                  ||
    message.videoMessage?.caption                  ||
    null
  );
}

function getMessageType(message) {
  if (message.imageMessage)  return 'gambar';
  if (message.audioMessage)  return 'voice';
  if (message.conversation || message.extendedTextMessage) return 'teks';
  return null;
}

async function forwardToLaravel(payload) {
  try {
    await axios.post(
      `${LARAVEL_URL}/api/wa/incoming`,
      { ...payload, secret: SECRET },
      { timeout: 10_000 }
    );
    logger.info('Receiver', 'Diteruskan ke Laravel', { wa: payload.wa_number, tipe: payload.tipe });
  } catch (err) {
    logger.error('Receiver', 'Laravel tidak bisa diakses, masuk antrian', {
      wa:    payload.wa_number,
      error: err.message,
    });
    addToQueue(payload);
  }
}

export function startReceiver(sock) {
  sock.ev.on('messages.upsert', async ({ messages, type }) => {
    if (type !== 'notify') return;

    for (const msg of messages) {
      // Abaikan pesan dari diri sendiri
      if (msg.key.fromMe) continue;

      // Abaikan pesan dari grup
      if (msg.key.remoteJid?.endsWith('@g.us')) continue;

      // Abaikan pesan status/broadcast
      if (msg.key.remoteJid === 'status@broadcast') continue;

      const message = msg.message;
      if (!message) continue;

      const tipe = getMessageType(message);
      if (!tipe) continue;

      const waNumber = msg.key.remoteJid.replace('@s.whatsapp.net', '');
      const pesan    = extractText(message) ?? '';
      let   mediaB64 = null;

      if (tipe === 'gambar') {
        mediaB64 = await downloadMedia(sock, msg);
      }

      const payload = {
        wa_number:    waNumber,
        pesan,
        tipe,
        media_base64: mediaB64,
        timestamp:    msg.messageTimestamp
          ? Number(msg.messageTimestamp) * 1000
          : Date.now(),
      };

      logger.info('Receiver', `Pesan masuk [${tipe}]`, { wa: waNumber, preview: pesan.slice(0, 50) });

      await forwardToLaravel(payload);
    }
  });

  logger.info('Receiver', 'Message listener aktif');
}

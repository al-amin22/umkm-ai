import { existsSync, mkdirSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';
import { logger } from './logger.js';

const __dirname   = dirname(fileURLToPath(import.meta.url));
const SESSION_DIR = process.env.SESSION_PATH
  ? join(__dirname, process.env.SESSION_PATH)
  : join(__dirname, 'sessions');

export function ensureSessionDir() {
  if (!existsSync(SESSION_DIR)) {
    mkdirSync(SESSION_DIR, { recursive: true });
    logger.info('SessionManager', `Session directory created: ${SESSION_DIR}`);
  }
}

export async function loadSession(sessionName = 'default') {
  const { useMultiFileAuthState } = await import('@whiskeysockets/baileys');

  const sessionPath = join(SESSION_DIR, sessionName);

  if (!existsSync(sessionPath)) {
    mkdirSync(sessionPath, { recursive: true });
    logger.info('SessionManager', `New session directory: ${sessionPath}`);
  } else {
    logger.info('SessionManager', `Loading existing session: ${sessionPath}`);
  }

  const { state, saveCreds } = await useMultiFileAuthState(sessionPath);
  return { state, saveCreds, sessionPath };
}

export function getSessionPath(sessionName = 'default') {
  return join(SESSION_DIR, sessionName);
}

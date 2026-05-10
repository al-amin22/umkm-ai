import { appendFileSync, mkdirSync, existsSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const LOG_DIR   = join(__dirname, 'logs');

if (!existsSync(LOG_DIR)) mkdirSync(LOG_DIR, { recursive: true });

const pad  = (n) => String(n).padStart(2, '0');
const ts   = () => {
  const d = new Date();
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ` +
         `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
};

function write(level, tag, msg, extra) {
  const line = `[${ts()}] [${level}] [${tag}] ${msg}${extra ? ' ' + JSON.stringify(extra) : ''}\n`;
  process.stdout.write(line);
  if (level === 'ERROR') {
    appendFileSync(join(LOG_DIR, 'error.log'), line);
  }
  appendFileSync(join(LOG_DIR, 'out.log'), line);
}

export const logger = {
  info:  (tag, msg, extra) => write('INFO',  tag, msg, extra),
  warn:  (tag, msg, extra) => write('WARN',  tag, msg, extra),
  error: (tag, msg, extra) => write('ERROR', tag, msg, extra),
  debug: (tag, msg, extra) => write('DEBUG', tag, msg, extra),
};

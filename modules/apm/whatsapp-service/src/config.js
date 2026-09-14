import dotenv from 'dotenv';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: path.resolve(__dirname, '../.env') });
dotenv.config({ path: path.resolve(__dirname, '../../.env'), override: false });

export const config = {
  port: Number(process.env.PORT || 8765),
  bindHost: process.env.BIND_HOST || '127.0.0.1',
  workerToken: process.env.WORKER_TOKEN || '',
  botNumber: (process.env.BOT_NUMBER || '').replace(/\D/g, ''),
  groupSyncKeyword: (process.env.WHATSAPP_GROUP_SYNC_KEYWORD || 'Africa CDC').trim(),
  authDir: path.resolve(__dirname, '..', process.env.AUTH_DIR || '../storage/whatsapp-auth'),
  mediaDir: path.resolve(__dirname, '..', process.env.MEDIA_DIR || '../storage/app/whatsapp-media'),
  db: {
    host: process.env.DB_HOST || '127.0.0.1',
    port: Number(process.env.DB_PORT || 3306),
    user: process.env.DB_USERNAME || process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_DATABASE || process.env.DB_NAME || 'apm',
  },
};

import fs from 'node:fs';
import path from 'node:path';
import pino from 'pino';
import makeWASocket, {
  DisconnectReason,
  fetchLatestBaileysVersion,
  useMultiFileAuthState,
} from '@whiskeysockets/baileys';
import { config } from './config.js';
import { upsertSession } from './db.js';
import { syncAllGroups } from './sync-groups.js';
import { captureIncomingMessages } from './message-capture.js';

const logger = pino({ level: process.env.LOG_LEVEL || 'info' });

let sock = null;
let starting = false;
let latestQr = null;
let latestQrAt = null;

function ensureAuthDir() {
  fs.mkdirSync(config.authDir, { recursive: true });
}

export function getSocket() {
  return sock;
}

export function isConnected() {
  return !!(sock?.user);
}

export function getLatestQr() {
  return latestQr ? { qr: latestQr, generated_at: latestQrAt } : null;
}

export async function startWhatsApp() {
  if (starting) return sock;
  starting = true;
  ensureAuthDir();

  const { state, saveCreds } = await useMultiFileAuthState(config.authDir);
  const { version } = await fetchLatestBaileysVersion();

  sock = makeWASocket({
    version,
    auth: state,
    logger,
    printQRInTerminal: false,
    syncFullHistory: false,
    markOnlineOnConnect: false,
  });

  sock.ev.on('creds.update', saveCreds);

  sock.ev.on('connection.update', async (update) => {
    const { connection, lastDisconnect, qr } = update;

    if (qr) {
      latestQr = qr;
      latestQrAt = new Date().toISOString();
    }

    const registered = !!sock?.authState?.creds?.registered;
    const connected = connection === 'open';

    if (connected) {
      latestQr = null;
      latestQrAt = null;
    }

    await upsertSession({
      phone: config.botNumber || sock?.user?.id?.split(':')[0]?.replace(/\D/g, '') || null,
      connected,
      registered: registered || connected,
      last_error: lastDisconnect?.error?.message || null,
    });

    if (connection === 'open') {
      logger.info('WhatsApp connected');
      try {
        await syncAllGroups(sock);
      } catch (err) {
        logger.error({ err }, 'Initial group sync failed');
      }
    }

    if (connection === 'close') {
      const code = lastDisconnect?.error?.output?.statusCode;
      const shouldReconnect = code !== DisconnectReason.loggedOut;
      logger.warn({ code, shouldReconnect }, 'WhatsApp disconnected');
      sock = null;
      starting = false;
      if (code === DisconnectReason.loggedOut) {
        latestQr = null;
        latestQrAt = null;
        if (fs.existsSync(config.authDir)) {
          fs.rmSync(config.authDir, { recursive: true, force: true });
          ensureAuthDir();
        }
      }
      if (shouldReconnect) {
        setTimeout(() => startWhatsApp().catch((err) => logger.error(err)), 3000);
      }
    }
  });

  sock.ev.on('groups.update', async () => {
    if (!sock) return;
    try {
      await syncAllGroups(sock);
    } catch (err) {
      logger.error({ err }, 'Group update sync failed');
    }
  });

  sock.ev.on('messages.upsert', async ({ messages }) => {
    if (!messages?.length) return;
    try {
      await captureIncomingMessages(sock, messages);
    } catch (err) {
      logger.error({ err }, 'Message capture failed');
    }
  });

  starting = false;
  return sock;
}

export async function requestPairingCode(phoneNumber) {
  const active = sock || (await startWhatsApp());
  if (active.authState?.creds?.registered) {
    throw new Error('WhatsApp is already linked. Clear auth in storage/whatsapp-auth to re-pair.');
  }
  const clean = String(phoneNumber).replace(/\D/g, '');
  if (clean.length < 7) {
    throw new Error('Invalid phone number — include country code, digits only.');
  }
  const code = await active.requestPairingCode(clean);
  await upsertSession({ phone: clean, pairing_code: code, connected: false, registered: false });
  return code;
}

export async function clearAuth() {
  if (sock) {
    try {
      await sock.logout();
    } catch {
      // ignore
    }
    sock = null;
  }
  if (fs.existsSync(config.authDir)) {
    fs.rmSync(config.authDir, { recursive: true, force: true });
  }
  ensureAuthDir();
  latestQr = null;
  latestQrAt = null;
  starting = false;
  await upsertSession({ connected: false, registered: false, pairing_code: null, last_error: null });
}

/** Clear session and restart socket so Baileys emits a fresh QR code. */
export async function prepareQrPairing() {
  if (isConnected()) {
    throw new Error('WhatsApp is already connected.');
  }
  await clearAuth();
  await startWhatsApp();
  return true;
}

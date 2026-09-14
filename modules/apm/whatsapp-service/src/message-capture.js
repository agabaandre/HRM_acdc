import fs from 'node:fs';
import path from 'node:path';
import { downloadMediaMessage } from '@whiskeysockets/baileys';
import { config } from './config.js';
import { groupExists, storeGroupMessage } from './db.js';
import { broadcastGroupMessage } from './chat-hub.js';

function normalizePhone(raw) {
  return String(raw || '').replace(/\D/g, '');
}

function extractMessageContent(message) {
  if (!message) {
    return { type: 'empty', body: '', downloadable: false };
  }

  if (message.conversation) {
    return { type: 'text', body: String(message.conversation), downloadable: false };
  }
  if (message.extendedTextMessage?.text) {
    return { type: 'text', body: String(message.extendedTextMessage.text), downloadable: false };
  }
  if (message.imageMessage) {
    const caption = message.imageMessage.caption ? String(message.imageMessage.caption) : '';
    return {
      type: 'image',
      body: caption || '',
      downloadable: true,
      mime: message.imageMessage.mimetype || 'image/jpeg',
    };
  }
  if (message.stickerMessage) {
    return {
      type: 'sticker',
      body: '',
      downloadable: true,
      mime: message.stickerMessage.mimetype || 'image/webp',
    };
  }
  if (message.videoMessage) {
    const caption = message.videoMessage.caption ? String(message.videoMessage.caption) : '';
    return { type: 'video', body: caption ? `[video] ${caption}` : '[video]', downloadable: false };
  }
  if (message.documentMessage) {
    const name = message.documentMessage.fileName || message.documentMessage.title || 'document';
    return { type: 'document', body: `[document] ${name}`, downloadable: false };
  }
  if (message.audioMessage) {
    return { type: 'audio', body: message.audioMessage.ptt ? '[voice note]' : '[audio]', downloadable: false };
  }
  if (message.contactMessage) {
    return { type: 'contact', body: `[contact] ${message.contactMessage.displayName || ''}`.trim(), downloadable: false };
  }
  if (message.locationMessage) {
    return { type: 'location', body: '[location]', downloadable: false };
  }
  if (message.reactionMessage) {
    return { type: 'reaction', body: `[reaction] ${message.reactionMessage.text || ''}`.trim(), downloadable: false };
  }
  if (message.protocolMessage) {
    return { type: 'protocol', body: '', downloadable: false };
  }

  const keys = Object.keys(message).filter((k) => !k.startsWith('messageContext'));
  return { type: keys[0] || 'unknown', body: keys[0] ? `[${keys[0]}]` : '', downloadable: false };
}

function senderPhoneFromJid(jid) {
  const s = String(jid || '');
  if (s.endsWith('@lid')) return '';
  return normalizePhone(s.split('@')[0].split(':')[0]);
}

function extensionForMime(mime) {
  const map = {
    'image/jpeg': 'jpg',
    'image/jpg': 'jpg',
    'image/png': 'png',
    'image/webp': 'webp',
    'image/gif': 'gif',
  };
  return map[String(mime || '').toLowerCase()] || 'bin';
}

async function saveMediaBuffer(groupJid, waId, mime, buffer) {
  if (!buffer?.length) return null;
  const safeGroup = String(groupJid).replace(/[^a-zA-Z0-9._@-]/g, '_');
  const dir = path.join(config.mediaDir, safeGroup);
  fs.mkdirSync(dir, { recursive: true });
  const filename = `${String(waId).replace(/[^a-zA-Z0-9_-]/g, '_')}.${extensionForMime(mime)}`;
  const abs = path.join(dir, filename);
  fs.writeFileSync(abs, buffer);
  // Relative path from Laravel storage/app for serving.
  return path.join('whatsapp-media', safeGroup, filename).replace(/\\/g, '/');
}

async function maybeDownloadMedia(sock, msg, content) {
  if (!content.downloadable || !sock) {
    return { media_path: null, media_mime: null, media_size: null };
  }
  try {
    const buffer = await downloadMediaMessage(
      msg,
      'buffer',
      {},
      {
        logger: sock.logger,
        reuploadRequest: sock.updateMediaMessage,
      }
    );
    const mediaPath = await saveMediaBuffer(
      msg.key.remoteJid,
      msg.key.id,
      content.mime,
      buffer
    );
    return {
      media_path: mediaPath,
      media_mime: content.mime || 'application/octet-stream',
      media_size: buffer?.length || null,
    };
  } catch {
    return { media_path: null, media_mime: content.mime || null, media_size: null };
  }
}

/**
 * Persist incoming group messages for groups already tracked in APM.
 * @returns {Promise<object[]>} newly stored rows
 */
export async function captureIncomingMessages(sock, messages = []) {
  const stored = [];
  for (const msg of messages) {
    try {
      const groupJid = String(msg?.key?.remoteJid || '');
      if (!groupJid.endsWith('@g.us')) continue;
      if (msg?.messageStubType) continue;

      const waId = String(msg?.key?.id || '');
      if (!waId) continue;

      if (!(await groupExists(groupJid))) continue;

      const content = extractMessageContent(msg.message);
      if (content.type === 'protocol' || content.type === 'empty') continue;

      const senderJid = msg.key.fromMe
        ? String(msg.key.participant || msg.key.remoteJid || '')
        : String(msg.key.participant || msg.participant || '');
      const senderPhone = senderPhoneFromJid(senderJid);
      const ts = Number(msg.messageTimestamp || 0);
      const sentAt = ts > 0 ? new Date(ts * (ts < 1e12 ? 1000 : 1)) : new Date();
      const media = await maybeDownloadMedia(sock, msg, content);

      const row = await storeGroupMessage({
        group_jid: groupJid,
        wa_message_id: waId,
        sender_jid: senderJid || null,
        sender_phone: senderPhone || null,
        sender_name: msg.pushName || null,
        from_me: !!msg.key.fromMe,
        message_type: content.type,
        body: content.body || null,
        media_path: media.media_path,
        media_mime: media.media_mime,
        media_size: media.media_size,
        sent_at: sentAt,
      });
      if (row) {
        stored.push(row);
        broadcastGroupMessage(groupJid, row);
      }
    } catch {
      // Never let capture crash the socket loop.
    }
  }
  return stored;
}

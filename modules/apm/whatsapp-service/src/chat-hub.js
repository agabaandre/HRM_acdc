import crypto from 'node:crypto';
import { WebSocketServer } from 'ws';
import { config } from './config.js';

/** @type {Map<string, Set<import('ws').WebSocket>>} */
const rooms = new Map();

function timingSafeEqual(a, b) {
  const ba = Buffer.from(String(a));
  const bb = Buffer.from(String(b));
  if (ba.length !== bb.length) return false;
  return crypto.timingSafeEqual(ba, bb);
}

/**
 * Ticket format: base64url(groupJid|exp|staffId|hexHmac)
 */
export function verifyChatTicket(ticket) {
  try {
    const raw = Buffer.from(String(ticket || ''), 'base64url').toString('utf8');
    const parts = raw.split('|');
    if (parts.length !== 4) return null;
    const [groupJid, expStr, staffId, sig] = parts;
    const exp = Number(expStr);
    if (!groupJid?.endsWith('@g.us') || !Number.isFinite(exp) || exp < Math.floor(Date.now() / 1000)) {
      return null;
    }
    const payload = `${groupJid}|${exp}|${staffId}`;
    const expected = crypto.createHmac('sha256', config.workerToken).update(payload).digest('hex');
    if (!timingSafeEqual(sig, expected)) return null;
    return { groupJid, staffId: String(staffId) };
  } catch {
    return null;
  }
}

function subscribe(groupJid, ws) {
  if (!rooms.has(groupJid)) rooms.set(groupJid, new Set());
  rooms.get(groupJid).add(ws);
  ws.__groupJid = groupJid;
}

function unsubscribe(ws) {
  const groupJid = ws.__groupJid;
  if (!groupJid) return;
  const set = rooms.get(groupJid);
  if (!set) return;
  set.delete(ws);
  if (!set.size) rooms.delete(groupJid);
}

export function broadcastGroupMessage(groupJid, row) {
  const set = rooms.get(groupJid);
  if (!set?.size) return;
  const payload = JSON.stringify({
    type: 'message',
    group_jid: groupJid,
    message: row,
  });
  for (const client of set) {
    if (client.readyState === 1) {
      client.send(payload);
    }
  }
}

export function attachChatHub(server) {
  const wss = new WebSocketServer({ server, path: '/chat' });

  wss.on('connection', (ws, req) => {
    try {
      const url = new URL(req.url || '', `http://${req.headers.host || '127.0.0.1'}`);
      const ticket = url.searchParams.get('ticket') || '';
      const auth = verifyChatTicket(ticket);
      if (!auth) {
        ws.close(4401, 'Unauthorized');
        return;
      }
      subscribe(auth.groupJid, ws);
      ws.send(JSON.stringify({ type: 'subscribed', group_jid: auth.groupJid }));
    } catch {
      ws.close(1011, 'Bad request');
      return;
    }

    ws.on('close', () => unsubscribe(ws));
    ws.on('error', () => unsubscribe(ws));
    ws.on('message', (data) => {
      // Client heartbeat / no-op pings.
      try {
        const msg = JSON.parse(String(data));
        if (msg?.type === 'ping') {
          ws.send(JSON.stringify({ type: 'pong' }));
        }
      } catch {
        // ignore
      }
    });
  });

  return wss;
}

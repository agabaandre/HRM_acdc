import crypto from 'node:crypto';
import http from 'node:http';
import express from 'express';
import QRCode from 'qrcode';
import { config } from './config.js';
import { getSessionRow } from './db.js';
import { attachChatHub } from './chat-hub.js';
import { getLatestQr, getSocket, isConnected, prepareQrPairing, requestPairingCode } from './whatsapp.js';
import { syncAllGroups, syncPrimaryGroup, removeGroupMember, addGroupMembers } from './sync-groups.js';
import { sendGroupMessage } from './send-message.js';

function timingSafeTokenMatch(given, expected) {
  if (!expected || !given) return false;
  const a = Buffer.from(String(given));
  const b = Buffer.from(String(expected));
  if (a.length !== b.length) return false;
  return crypto.timingSafeEqual(a, b);
}

export function createApp() {
  const app = express();
  app.disable('x-powered-by');
  app.use(express.json({ limit: '6mb' }));

  function requireToken(req, res, next) {
    const token = req.header('X-Worker-Token') || '';
    if (!config.workerToken || !timingSafeTokenMatch(token, config.workerToken)) {
      return res.status(401).json({ error: 'Unauthorized.' });
    }
    next();
  }

  app.get('/health', requireToken, (_req, res) => {
    res.json({ ok: true });
  });

  app.get('/internal/status', requireToken, async (_req, res) => {
    try {
      const row = await getSessionRow();
      res.json({
        connected: isConnected(),
        registered: isConnected(),
        phone: row?.phone || config.botNumber || null,
        error: row?.last_error || null,
        last_sync_at: row?.last_sync_at || null,
      });
    } catch {
      res.status(500).json({ error: 'Status unavailable.' });
    }
  });

  app.post('/internal/pair', requireToken, async (req, res) => {
    try {
      const phoneNumber = String(req.body?.phoneNumber || '').replace(/\D/g, '');
      if (phoneNumber.length < 7 || phoneNumber.length > 15) {
        return res.status(400).json({ error: 'Invalid phone number.' });
      }
      const code = await requestPairingCode(phoneNumber);
      res.json({ ok: true, code });
    } catch {
      res.status(500).json({ error: 'Pairing request failed.' });
    }
  });

  app.post('/internal/qr/start', requireToken, async (_req, res) => {
    try {
      await prepareQrPairing();
      await new Promise((resolve) => setTimeout(resolve, 2000));
      const qrState = getLatestQr();
      if (!qrState?.qr) {
        return res.json({ ok: true, connected: false, waiting: true, message: 'Generating QR…' });
      }
      const qrImage = await QRCode.toDataURL(qrState.qr, { margin: 2, width: 280 });
      res.json({ ok: true, connected: false, qr_image: qrImage, generated_at: qrState.generated_at });
    } catch {
      res.status(500).json({ error: 'Could not start QR pairing.' });
    }
  });

  app.post('/internal/qr/poll', requireToken, async (_req, res) => {
    try {
      if (isConnected()) {
        return res.json({ ok: true, connected: true, registered: true });
      }

      const qrState = getLatestQr();
      if (!qrState?.qr) {
        return res.json({ ok: true, connected: false, waiting: true, message: 'Waiting for QR…' });
      }

      const qrImage = await QRCode.toDataURL(qrState.qr, {
        margin: 2,
        width: 280,
        color: { dark: '#111827', light: '#ffffff' },
      });

      res.json({
        ok: true,
        connected: false,
        qr_image: qrImage,
        generated_at: qrState.generated_at,
      });
    } catch {
      res.status(500).json({ error: 'Could not load QR code.' });
    }
  });

  app.post('/internal/sync', requireToken, async (req, res) => {
    try {
      const sock = getSocket();
      if (!sock || !isConnected()) {
        return res.status(503).json({ error: 'WhatsApp is not connected.' });
      }
      const scope = String(req.body?.scope || 'all').toLowerCase();
      const result = scope === 'primary'
        ? await syncPrimaryGroup(sock)
        : await syncAllGroups(sock);
      res.json({ ok: true, ...result });
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Group sync failed.';
      const status = message.includes('not configured') ? 422 : 500;
      res.status(status).json({ error: message });
    }
  });

  app.post('/internal/sync/group', requireToken, async (req, res) => {
    try {
      const groupJid = String(req.body?.groupJid || '');
      if (!groupJid.endsWith('@g.us')) {
        return res.status(400).json({ error: 'Invalid group identifier.' });
      }
      const sock = getSocket();
      if (!sock || !isConnected()) {
        return res.status(503).json({ error: 'WhatsApp is not connected.' });
      }
      const { syncOneGroup } = await import('./sync-groups.js');
      const result = await syncOneGroup(sock, groupJid);
      res.json({ ok: true, jid: groupJid, name: result.name, ...result });
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Group sync failed.';
      res.status(500).json({ error: message });
    }
  });

  app.post('/internal/groups/resolve-phones', requireToken, async (req, res) => {
    try {
      const groupJid = String(req.body?.groupJid || '');
      if (!groupJid.endsWith('@g.us')) {
        return res.status(400).json({ error: 'Invalid group identifier.' });
      }
      const sock = getSocket();
      if (!sock || !isConnected()) {
        return res.status(503).json({ error: 'WhatsApp is not connected.' });
      }
      const { syncOneGroup } = await import('./sync-groups.js');
      const result = await syncOneGroup(sock, groupJid);
      res.json({
        ok: true,
        jid: groupJid,
        phones_resolved: result.phones_resolved,
        lid_map_size: result.lid_map_size,
        members_total: result.members_total,
        name: result.name,
      });
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Phone resolve failed.';
      res.status(500).json({ error: message });
    }
  });

  app.post('/internal/groups/participants/remove', requireToken, async (req, res) => {
    try {
      const groupJid = String(req.body?.groupJid || '');
      const memberJid = String(req.body?.memberJid || '');
      if (!groupJid.endsWith('@g.us') || !memberJid.includes('@')) {
        return res.status(400).json({ error: 'Invalid group or member identifier.' });
      }
      const sock = getSocket();
      if (!sock || !isConnected()) {
        return res.status(503).json({ error: 'WhatsApp is not connected.' });
      }
      const result = await removeGroupMember(sock, groupJid, memberJid);
      res.json(result);
    } catch (err) {
      const message = err?.message || 'Could not remove group member.';
      console.error('removeGroupMember failed:', message);
      res.status(500).json({ error: message });
    }
  });

  app.post('/internal/groups/participants/add', requireToken, async (req, res) => {
    try {
      const groupJid = String(req.body?.groupJid || '');
      const memberJids = Array.isArray(req.body?.memberJids) ? req.body.memberJids : [];
      if (!groupJid.endsWith('@g.us') || !memberJids.length) {
        return res.status(400).json({ error: 'Invalid group or member list.' });
      }
      const sock = getSocket();
      if (!sock || !isConnected()) {
        return res.status(503).json({ error: 'WhatsApp is not connected.' });
      }
      const result = await addGroupMembers(sock, groupJid, memberJids);
      res.json(result);
    } catch (err) {
      const message = err?.message || 'Could not add group members.';
      console.error('addGroupMembers failed:', message);
      res.status(500).json({ error: message });
    }
  });

  app.post('/internal/groups/messages/send', requireToken, async (req, res) => {
    try {
      const groupJid = String(req.body?.groupJid || '');
      const text = String(req.body?.text || '');
      const imageBase64 = req.body?.imageBase64 ? String(req.body.imageBase64) : null;
      const imageMime = String(req.body?.imageMime || 'image/jpeg');
      const caption = String(req.body?.caption || '');
      if (!groupJid.endsWith('@g.us')) {
        return res.status(400).json({ error: 'Invalid group identifier.' });
      }
      const sock = getSocket();
      if (!sock || !isConnected()) {
        return res.status(503).json({ error: 'WhatsApp is not connected.' });
      }
      const result = await sendGroupMessage(sock, groupJid, {
        text,
        imageBase64,
        imageMime,
        caption,
      });
      res.json(result);
    } catch (err) {
      const message = err?.message || 'Could not send message.';
      console.error('sendGroupMessage failed:', message);
      res.status(500).json({ error: message });
    }
  });

  return app;
}

export function listen() {
  if (!config.workerToken || config.workerToken.length < 32) {
    console.error('FATAL: WORKER_TOKEN must be set (min 32 chars). Run: php artisan whatsapp:bootstrap');
    process.exit(1);
  }

  const app = createApp();
  const host = config.bindHost || '127.0.0.1';
  const server = http.createServer(app);
  attachChatHub(server);
  server.listen(config.port, host, () => {
    console.log(`APM WhatsApp worker listening on ${host}:${config.port}`);
    import('./whatsapp.js').then(({ startWhatsApp }) => {
      startWhatsApp().catch((err) => console.error('WhatsApp start failed:', err.message));
    });
  });
}

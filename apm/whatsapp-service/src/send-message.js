/**
 * Send a text or image message into a WhatsApp group.
 */
export async function sendGroupMessage(sock, groupJid, { text = '', imageBase64 = null, imageMime = 'image/jpeg', caption = '' } = {}) {
  if (!sock) throw new Error('WhatsApp socket is not ready.');
  if (!String(groupJid || '').endsWith('@g.us')) {
    throw new Error('Invalid group identifier.');
  }

  const trimmed = String(text || '').trim();
  const captionText = String(caption || trimmed || '').trim();

  if (imageBase64) {
    const buffer = Buffer.from(String(imageBase64), 'base64');
    if (!buffer.length) {
      throw new Error('Invalid image payload.');
    }
    if (buffer.length > 5 * 1024 * 1024) {
      throw new Error('Image must be 5MB or smaller.');
    }
    const sent = await sock.sendMessage(groupJid, {
      image: buffer,
      mimetype: imageMime || 'image/jpeg',
      caption: captionText || undefined,
    });
    return { ok: true, wa_message_id: sent?.key?.id || null };
  }

  if (!trimmed) {
    throw new Error('Message text is required.');
  }

  const sent = await sock.sendMessage(groupJid, { text: trimmed });
  return { ok: true, wa_message_id: sent?.key?.id || null };
}

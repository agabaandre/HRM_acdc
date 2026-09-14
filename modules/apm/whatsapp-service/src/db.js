import mysql from 'mysql2/promise';
import { config } from './config.js';

let pool;

export function getPool() {
  if (!pool) {
    pool = mysql.createPool({
      ...config.db,
      waitForConnections: true,
      connectionLimit: 5,
    });
  }
  return pool;
}

export async function upsertSession(fields) {
  const db = getPool();
  const [rows] = await db.query('SELECT id FROM whatsapp_sessions ORDER BY id ASC LIMIT 1');
  const existing = rows[0];

  const values = {
    phone: fields.phone ?? null,
    connected: fields.connected ? 1 : 0,
    registered: fields.registered ? 1 : 0,
    pairing_code: fields.pairing_code ?? null,
    last_error: fields.last_error ?? null,
    last_connected_at: fields.connected ? new Date() : null,
    last_sync_at: fields.last_sync_at ?? null,
  };

  if (existing) {
    await db.query(
      `UPDATE whatsapp_sessions SET phone = ?, connected = ?, registered = ?, pairing_code = ?, last_error = ?,
       last_connected_at = COALESCE(?, last_connected_at), last_sync_at = COALESCE(?, last_sync_at), updated_at = NOW()
       WHERE id = ?`,
      [values.phone, values.connected, values.registered, values.pairing_code, values.last_error, values.last_connected_at, values.last_sync_at, existing.id]
    );
  } else {
    await db.query(
      `INSERT INTO whatsapp_sessions (phone, connected, registered, pairing_code, last_error, last_connected_at, last_sync_at, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())`,
      [values.phone, values.connected, values.registered, values.pairing_code, values.last_error, values.last_connected_at, values.last_sync_at]
    );
  }
}

export async function getSessionRow() {
  const db = getPool();
  const [rows] = await db.query('SELECT * FROM whatsapp_sessions ORDER BY id ASC LIMIT 1');
  return rows[0] || null;
}

export async function upsertGroup(jid, name, description = '') {
  const db = getPool();
  await db.query(
    `INSERT INTO whatsapp_groups (jid, name, description, is_bot_on, is_chat_bot_on, is_img_on, is_91_only, is_auto_sticker_on, is_rank_notif_on, total_msg_count, synced_at, created_at, updated_at)
     VALUES (?, ?, ?, 0, 0, 0, 0, 0, 0, 0, NOW(), NOW(), NOW())
     ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), synced_at = NOW(), updated_at = NOW()`,
    [jid, name, description]
  );
}

export async function replaceGroupMembers(groupJid, members) {
  const db = getPool();
  const conn = await db.getConnection();
  try {
    await conn.beginTransaction();
    await conn.query('DELETE FROM whatsapp_group_members WHERE group_jid = ?', [groupJid]);
    for (const member of members) {
      await conn.query(
        `INSERT INTO whatsapp_group_members (group_jid, member_jid, phone, lid, username, is_admin, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())`,
        [
          groupJid,
          member.jid,
          member.phone || null,
          member.lid || null,
          member.username,
          member.is_admin ? 1 : 0,
        ]
      );
    }
    await conn.commit();
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}

export async function pruneMissingGroups(seenJids) {
  const db = getPool();
  if (!seenJids.length) {
    await db.query('DELETE FROM whatsapp_groups');
    return;
  }
  const placeholders = seenJids.map(() => '?').join(',');
  await db.query(`DELETE FROM whatsapp_groups WHERE jid NOT IN (${placeholders})`, seenJids);
}

/** Remove DB groups whose name does not contain the sync keyword. */
export async function pruneGroupsWithoutKeyword(keyword) {
  const trimmed = String(keyword || '').trim();
  if (!trimmed) {
    return 0;
  }
  const db = getPool();
  // Delete every stored group that does not contain the keyword (including empty/null names).
  const [result] = await db.query(
    `DELETE FROM whatsapp_groups
     WHERE name IS NULL
        OR name = ''
        OR LOWER(name) NOT LIKE LOWER(?)`,
    [`%${trimmed}%`]
  );
  return result?.affectedRows ?? 0;
}

export async function getGroupFlags(jid) {
  const db = getPool();
  const [rows] = await db.query(
    'SELECT is_bot_on, is_chat_bot_on, is_img_on, is_91_only, is_auto_sticker_on, is_rank_notif_on FROM whatsapp_groups WHERE jid = ? LIMIT 1',
    [jid]
  );
  return rows[0] || null;
}

export async function getSystemSetting(key) {
  const db = getPool();
  const [rows] = await db.query('SELECT value FROM system_settings WHERE `key` = ? LIMIT 1', [key]);
  return rows[0]?.value ?? null;
}

export async function getGroupType(jid) {
  const db = getPool();
  const [rows] = await db.query('SELECT group_type FROM whatsapp_groups WHERE jid = ? LIMIT 1', [jid]);
  return rows[0]?.group_type || 'standard';
}

export async function getActiveStaffPhones() {
  const db = getPool();
  const [rows] = await db.query(
    `SELECT whatsapp, tel_1 FROM staff
     WHERE status IN ('Active', 'Due', 'Under Renewal')
       AND status NOT IN ('Expired', 'Separated')`
  );
  const phones = [];
  for (const row of rows) {
    const whatsapp = String(row.whatsapp || '').replace(/\D/g, '');
    const tel = String(row.tel_1 || '').replace(/\D/g, '');
    const phone = whatsapp || tel;
    if (phone) phones.push(phone);
  }
  return phones;
}

export async function groupExists(jid) {
  const db = getPool();
  const [rows] = await db.query('SELECT jid FROM whatsapp_groups WHERE jid = ? LIMIT 1', [jid]);
  return !!rows[0];
}

/**
 * Persist a captured group message. Keeps at most `keep` newest rows per group.
 * @returns {Promise<object|null>} inserted row, or null when duplicate
 */
export async function storeGroupMessage(row, keep = 500) {
  const db = getPool();
  const [result] = await db.query(
    `INSERT IGNORE INTO whatsapp_messages
      (group_jid, wa_message_id, sender_jid, sender_phone, sender_name, from_me, message_type, body, media_path, media_mime, media_size, sent_at, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())`,
    [
      row.group_jid,
      row.wa_message_id,
      row.sender_jid || null,
      row.sender_phone || null,
      row.sender_name || null,
      row.from_me ? 1 : 0,
      row.message_type || 'text',
      row.body ?? null,
      row.media_path || null,
      row.media_mime || null,
      row.media_size || null,
      row.sent_at || new Date(),
    ]
  );

  if ((result?.affectedRows || 0) === 0) {
    return null;
  }

  const insertId = result.insertId;
  await db.query(
    'UPDATE whatsapp_groups SET total_msg_count = total_msg_count + 1, updated_at = NOW() WHERE jid = ?',
    [row.group_jid]
  );

  await db.query(
    `DELETE FROM whatsapp_messages
     WHERE group_jid = ?
       AND id NOT IN (
         SELECT id FROM (
           SELECT id FROM whatsapp_messages WHERE group_jid = ? ORDER BY sent_at DESC, id DESC LIMIT ?
         ) recent
       )`,
    [row.group_jid, row.group_jid, keep]
  );

  const [rows] = await db.query('SELECT * FROM whatsapp_messages WHERE id = ? LIMIT 1', [insertId]);
  return rows[0] || null;
}

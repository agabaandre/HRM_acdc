import {
  getGroupType,
  getSystemSetting,
  getActiveStaffPhones,
  pruneMissingGroups,
  pruneGroupsWithoutKeyword,
  replaceGroupMembers,
  upsertGroup,
  upsertSession,
} from './db.js';
import { config } from './config.js';

function participantName(participant) {
  if (typeof participant === 'string') return participant.split('@')[0];
  return participant?.id?.split('@')[0] || '';
}

function normalizePhone(raw) {
  return String(raw || '').replace(/\D/g, '');
}

function phoneTail(digits) {
  const d = normalizePhone(digits);
  if (!d) return '';
  return d.length > 9 ? d.slice(-9) : d;
}

function phoneToJid(phone) {
  return `${normalizePhone(phone)}@s.whatsapp.net`;
}

function isPhoneJid(jid) {
  const s = String(jid || '');
  return s.endsWith('@s.whatsapp.net') || s.endsWith('@c.us');
}

function isLidJid(jid) {
  return String(jid || '').endsWith('@lid');
}

/**
 * Baileys group participants often use LID addressing (`123@lid`).
 * Prefer the phone JID from `jid` / `phone_number` when present.
 */
function resolveParticipantPhone(p) {
  const candidates = [p?.jid, p?.phoneNumber, p?.phone_number, p?.pn];
  for (const candidate of candidates) {
    if (!candidate) continue;
    const s = String(candidate);
    if (isPhoneJid(s)) {
      return normalizePhone(s.split('@')[0]);
    }
    if (isLidJid(s)) continue;
    const digits = normalizePhone(s);
    if (digits.length >= 7 && digits.length <= 15) {
      return digits;
    }
  }
  // Legacy: id itself is a phone JID
  const id = String(p?.id || '');
  if (isPhoneJid(id)) {
    return normalizePhone(id.split('@')[0]);
  }
  return '';
}

function mapMembers(metadata, lidToPhone = new Map()) {
  const participants = metadata?.participants || [];
  return participants.map((p) => {
    const id = String(p.id || p.lid || p.jid || p || '');
    let phone = resolveParticipantPhone(p);
    const lid = isLidJid(id) ? id : (p.lid && isLidJid(p.lid) ? String(p.lid) : '');

    if (!phone && lid) {
      const lidUser = lid.split('@')[0].split(':')[0];
      phone = lidToPhone.get(lidUser) || lidToPhone.get(lid) || '';
    }

    const phoneJid = phone ? phoneToJid(phone) : '';
    // Keep phone JID when known so APM can match staff; otherwise keep LID for WA ops.
    const jid = phoneJid || id;
    const display = p.name || p.notify || (phone ? phone : participantName(id));

    return {
      jid,
      lid: lid || null,
      phone: phone || null,
      username: display,
      is_admin: !!(p.admin || p.isAdmin),
    };
  });
}

/**
 * WhatsApp often returns only @lid for group participants (no phone_number).
 * Resolve LID → phone by:
 * 1) mapping the linked bot account (sock.user)
 * 2) looking up active staff phones via onWhatsApp (returns each PN's LID)
 */
async function buildStaffLidToPhoneMap(sock) {
  const map = new Map();
  if (!sock) {
    return map;
  }

  // Always map the authenticated device to its phone.
  try {
    const meId = String(sock.user?.id || '');
    const meLid = String(sock.user?.lid || sock.authState?.creds?.me?.lid || '');
    const mePhone = normalizePhone(meId.split(':')[0] || config.botNumber || '');
    if (mePhone && meLid) {
      const lidUser = meLid.split('@')[0].split(':')[0];
      map.set(lidUser, mePhone);
      map.set(meLid.includes('@') ? meLid : `${meLid}@lid`, mePhone);
    }
  } catch {
    // ignore
  }

  if (!sock.onWhatsApp) {
    return map;
  }

  const phones = [...new Set((await getActiveStaffPhones()).map(normalizePhone).filter((p) => p.length >= 8 && !/^0+$/.test(p)))];
  const batchSize = 40;

  for (let i = 0; i < phones.length; i += batchSize) {
    const batch = phones.slice(i, i + batchSize);
    const jids = batch.map((p) => phoneToJid(p));
    try {
      const results = await sock.onWhatsApp(...jids);
      for (const row of results || []) {
        const phone = normalizePhone(String(row.jid || '').split('@')[0]);
        const lidRaw = String(row.lid || '');
        if (!phone || !lidRaw) continue;
        const lidUser = lidRaw.split('@')[0].split(':')[0];
        map.set(lidUser, phone);
        map.set(lidRaw.includes('@') ? lidRaw : `${lidRaw}@lid`, phone);
      }
    } catch {
      // Best-effort; continue other batches.
    }
  }

  return map;
}

async function mapMembersResolved(sock, metadata, lidToPhone) {
  const map = lidToPhone || (await buildStaffLidToPhoneMap(sock));
  return { members: mapMembers(metadata, map), lidToPhone: map };
}

function participantPhoneTail(p, lidToPhone = new Map()) {
  let phone = resolveParticipantPhone(p);
  if (!phone) {
    const id = String(p?.id || p?.lid || '');
    if (isLidJid(id)) {
      const lidUser = id.split('@')[0].split(':')[0];
      phone = lidToPhone.get(lidUser) || lidToPhone.get(id) || '';
    }
  }
  if (phone) return phoneTail(phone);
  const id = String(p?.id || p?.jid || '');
  if (isLidJid(id)) return '';
  return phoneTail(id.split('@')[0]);
}

function groupMatchesKeyword(name, keyword) {
  if (!keyword) return true;
  return String(name || '').toLowerCase().includes(String(keyword).toLowerCase());
}

async function resolveSyncKeyword() {
  const fromDb = await getSystemSetting('whatsapp_group_sync_keyword');
  const keyword = (fromDb || config.groupSyncKeyword || 'Africa CDC').trim();
  return keyword;
}

async function resolveBotNumber() {
  const fromDb = await getSystemSetting('whatsapp_bot_number');
  return normalizePhone(fromDb || config.botNumber || '');
}

async function syncAllStaffRoster(sock, jid, botNumber, lidToPhone = new Map()) {
  const groupType = await getGroupType(jid);
  if (groupType !== 'all_staff') {
    return { added: 0, removed: 0 };
  }

  const staffPhones = await getActiveStaffPhones();
  const metadata = await sock.groupMetadata(jid);
  const participants = metadata?.participants || [];

  const memberTails = new Set();
  for (const p of participants) {
    const tail = participantPhoneTail(p, lidToPhone);
    if (tail) memberTails.add(tail);
  }

  const staffTails = new Map();
  for (const phone of staffPhones) {
    const tail = phoneTail(phone);
    if (tail) staffTails.set(tail, phone);
  }

  const toAdd = [];
  for (const [tail, phone] of staffTails) {
    if (!memberTails.has(tail)) {
      toAdd.push(phoneToJid(phone));
    }
  }

  const botTail = phoneTail(botNumber);
  const toRemove = [];
  for (const p of participants) {
    const tail = participantPhoneTail(p, lidToPhone);
    if (!tail) continue;
    if (botTail && tail === botTail) continue;
    if (!staffTails.has(tail)) {
      const removeJid = (p.lid && isLidJid(p.lid) ? p.lid : null)
        || (isLidJid(p.id) ? p.id : null)
        || (resolveParticipantPhone(p) ? phoneToJid(resolveParticipantPhone(p)) : null)
        || p.id
        || p.jid;
      if (removeJid) toRemove.push(removeJid);
    }
  }

  if (toAdd.length) {
    await sock.groupParticipantsUpdate(jid, toAdd, 'add');
  }
  if (toRemove.length) {
    await sock.groupParticipantsUpdate(jid, toRemove, 'remove');
  }

  return { added: toAdd.length, removed: toRemove.length };
}

export async function removeGroupMember(sock, groupJid, memberJid) {
  if (!sock) throw new Error('WhatsApp socket is not ready.');
  await assertBotCanManageGroup(sock, groupJid);
  await sock.groupParticipantsUpdate(groupJid, [memberJid], 'remove');
  const metadata = await sock.groupMetadata(groupJid);
  const { members } = await mapMembersResolved(sock, metadata);
  await upsertGroup(groupJid, metadata.subject || groupJid, metadata.desc ? String(metadata.desc) : '');
  await replaceGroupMembers(groupJid, members);
  return { ok: true };
}

function botIdentity(sock) {
  const me = sock?.user || {};
  const phone = normalizePhone(String(me.id || '').split(':')[0].split('@')[0]);
  const lid = normalizePhone(String(me.lid || sock?.authState?.creds?.me?.lid || '').split('@')[0]);
  return { phone, lid, tail: phoneTail(phone) };
}

function participantIsAdmin(p) {
  const role = p?.admin || p?.isAdmin || p?.isSuperAdmin;
  return role === 'admin' || role === 'superadmin' || role === true;
}

async function assertBotCanManageGroup(sock, groupJid) {
  const metadata = await sock.groupMetadata(groupJid);
  const bot = botIdentity(sock);
  const participants = metadata?.participants || [];
  const me = participants.find((p) => {
    const id = String(p?.id || '');
    const phone = resolveParticipantPhone(p) || normalizePhone(id.split('@')[0]);
    const tail = phoneTail(phone);
    if (bot.phone && (phone === bot.phone || id.startsWith(`${bot.phone}@`))) return true;
    if (bot.lid && id.startsWith(`${bot.lid}@`)) return true;
    if (bot.tail && tail === bot.tail) return true;
    return false;
  });

  if (!me) {
    throw new Error(
      `WhatsApp bot (${bot.phone || 'unknown'}) is not a member of this group, so it cannot add participants.`
    );
  }
  if (!participantIsAdmin(me)) {
    throw new Error(
      `WhatsApp bot (${bot.phone || 'unknown'}) is in this group but is not an admin. Promote the bot to admin in WhatsApp, then try again.`
    );
  }

  return metadata;
}

function describeParticipantAddStatus(status) {
  const code = String(status || '');
  switch (code) {
    case '200':
      return null;
    case '403':
      return 'privacy settings blocked the add (user must join via invite)';
    case '404':
      return 'number is not on WhatsApp';
    case '408':
      return 'add timed out';
    case '409':
      return 'already in the group';
    case '401':
      return 'bot is not allowed to add this participant';
    default:
      return `WhatsApp rejected add (status ${code})`;
  }
}

export async function addGroupMembers(sock, groupJid, memberJids) {
  if (!sock) throw new Error('WhatsApp socket is not ready.');
  const jids = [...new Set((memberJids || []).map((j) => String(j).trim()).filter((j) => j.includes('@')))];
  if (!jids.length) {
    return { ok: true, added: 0 };
  }

  await assertBotCanManageGroup(sock, groupJid);

  // Resolve to WhatsApp-confirmed JIDs when possible (handles country-code / LID edge cases).
  let resolved = jids;
  try {
    const phones = jids.map((j) => normalizePhone(j.split('@')[0])).filter(Boolean);
    const results = await sock.onWhatsApp(...phones);
    const found = (results || [])
      .filter((r) => r?.exists && r?.jid)
      .map((r) => String(r.jid));
    if (found.length) {
      resolved = [...new Set(found)];
    }
  } catch {
    // Fall back to the original phone JIDs.
  }

  // WhatsApp rate-limits large bulk adds; process in small batches.
  const batchSize = 5;
  const failures = [];
  let added = 0;
  for (let i = 0; i < resolved.length; i += batchSize) {
    const batch = resolved.slice(i, i + batchSize);
    const results = await sock.groupParticipantsUpdate(groupJid, batch, 'add');
    for (const row of results || []) {
      const reason = describeParticipantAddStatus(row?.status);
      if (reason) {
        failures.push(`${normalizePhone(String(row?.jid || '').split('@')[0]) || row?.jid}: ${reason}`);
      } else {
        added += 1;
      }
    }
    if (!results?.length) {
      // Older Baileys builds may not return per-participant rows.
      added += batch.length;
    }
  }

  if (added === 0 && failures.length) {
    throw new Error(`Could not add members: ${failures.slice(0, 3).join('; ')}`);
  }

  const metadata = await sock.groupMetadata(groupJid);
  const { members } = await mapMembersResolved(sock, metadata);
  await upsertGroup(groupJid, metadata.subject || groupJid, metadata.desc ? String(metadata.desc) : '');
  await replaceGroupMembers(groupJid, members);
  return {
    ok: true,
    added,
    failed: failures,
    warning: failures.length ? failures.slice(0, 5).join('; ') : null,
  };
}

export async function syncAllGroups(sock) {
  if (!sock) throw new Error('WhatsApp socket is not ready.');

  const keyword = await resolveSyncKeyword();
  const botNumber = await resolveBotNumber();
  const lidToPhone = await buildStaffLidToPhoneMap(sock);
  const groups = await sock.groupFetchAllParticipating();
  const seen = [];
  let rosterAdjusted = 0;
  let phonesResolved = 0;

  for (const [jid, metadata] of Object.entries(groups)) {
    const name = metadata.subject || jid;
    if (!groupMatchesKeyword(name, keyword)) {
      continue;
    }

    seen.push(jid);
    const description = metadata.desc ? String(metadata.desc) : '';
    const { members } = await mapMembersResolved(sock, metadata, lidToPhone);
    phonesResolved += members.filter((m) => m.phone).length;
    await upsertGroup(jid, name, description);
    await replaceGroupMembers(jid, members);

    const roster = await syncAllStaffRoster(sock, jid, botNumber, lidToPhone);
    if (roster.added > 0 || roster.removed > 0) {
      rosterAdjusted += 1;
      const refreshed = await sock.groupMetadata(jid);
      const resolved = await mapMembersResolved(sock, refreshed, lidToPhone);
      await upsertGroup(jid, refreshed.subject || name, refreshed.desc ? String(refreshed.desc) : '');
      await replaceGroupMembers(jid, resolved.members);
    }
  }

  await pruneMissingGroups(seen);
  const prunedByKeyword = await pruneGroupsWithoutKeyword(keyword);

  await upsertSession({ last_sync_at: new Date() });

  return {
    synced: seen.length,
    roster_adjusted: rosterAdjusted,
    pruned: prunedByKeyword,
    phones_resolved: phonesResolved,
    lid_map_size: lidToPhone.size,
    keyword,
    scope: 'all',
  };
}

export async function syncOneGroup(sock, jid) {
  if (!sock) throw new Error('WhatsApp socket is not ready.');
  const botNumber = await resolveBotNumber();
  const lidToPhone = await buildStaffLidToPhoneMap(sock);
  const metadata = await sock.groupMetadata(jid);
  const name = metadata.subject || jid;
  const { members } = await mapMembersResolved(sock, metadata, lidToPhone);
  await upsertGroup(jid, name, metadata.desc ? String(metadata.desc) : '');
  await replaceGroupMembers(jid, members);
  const roster = await syncAllStaffRoster(sock, jid, botNumber, lidToPhone);
  if (roster.added > 0 || roster.removed > 0) {
    const refreshed = await sock.groupMetadata(jid);
    const resolved = await mapMembersResolved(sock, refreshed, lidToPhone);
    await upsertGroup(jid, refreshed.subject || name, refreshed.desc ? String(refreshed.desc) : '');
    await replaceGroupMembers(jid, resolved.members);
  }
  return {
    jid,
    name,
    roster,
    phones_resolved: members.filter((m) => m.phone).length,
    lid_map_size: lidToPhone.size,
    members_total: members.length,
  };
}

export async function syncPrimaryGroup(sock) {
  if (!sock) throw new Error('WhatsApp socket is not ready.');

  const jid = String((await getSystemSetting('whatsapp_primary_group_jid')) || '').trim();
  if (!jid) {
    throw new Error('Primary staff group is not configured.');
  }

  const result = await syncOneGroup(sock, jid);
  await upsertSession({ last_sync_at: new Date() });

  return {
    synced: 1,
    scope: 'primary',
    primary_jid: jid,
    name: result.name,
    roster: result.roster,
    phones_resolved: result.phones_resolved,
    lid_map_size: result.lid_map_size,
  };
}

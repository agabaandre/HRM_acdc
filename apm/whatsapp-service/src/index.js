import { listen } from './routes.js';

// Baileys can reject with bare close codes (e.g. "1006") on disconnect.
// Keep the HTTP worker alive so reconnect logic can run.
process.on('unhandledRejection', (reason) => {
  const message = reason instanceof Error ? reason.message : String(reason);
  console.error('[whatsapp-service] unhandledRejection:', message);
});

process.on('uncaughtException', (err) => {
  console.error('[whatsapp-service] uncaughtException:', err?.message || err);
});

listen();

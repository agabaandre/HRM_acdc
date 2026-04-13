/**
 * APM: PWA disabled. This file is a no-op so any existing registration
 * does not provide installability or standalone window behavior.
 */
self.addEventListener('install', function () {
    self.skipWaiting();
});
self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

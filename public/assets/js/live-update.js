/**
 * live-update.js – polling-based live refresh for clubs & events.
 * Hits /live/fingerprint every 5 s; reloads when the DB changes.
 */
(function () {
    'use strict';

    var POLL_INTERVAL = 5000; // ms
    var initialFingerprint = null;
    var timer = null;

    function showToast(msg) {
        var t = document.getElementById('live-toast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'live-toast';
            t.style.cssText =
                'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;' +
                'background:#0d6efd;color:#fff;padding:.7rem 1.2rem;' +
                'border-radius:.5rem;box-shadow:0 4px 14px rgba(0,0,0,.25);' +
                'font-size:.88rem;display:flex;align-items:center;gap:.5rem;' +
                'opacity:0;transition:opacity .35s;';
            document.body.appendChild(t);
        }
        t.innerHTML = '<i class="bi bi-arrow-clockwise"></i> ' + msg;
        t.style.opacity = 1;
        clearTimeout(t._hide);
        t._hide = setTimeout(function () { t.style.opacity = 0; }, 4000);
    }

    function fetchFingerprint(callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/live/fingerprint', true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        callback(null, data.fingerprint);
                    } catch (e) {
                        callback(e, null);
                    }
                } else {
                    callback(new Error('HTTP ' + xhr.status), null);
                }
            }
        };
        xhr.send();
    }

    function poll() {
        fetchFingerprint(function (err, fingerprint) {
            if (err) return; // silent – try again next tick

            if (initialFingerprint === null) {
                // First call – just store the baseline
                initialFingerprint = fingerprint;
                return;
            }

            if (fingerprint !== initialFingerprint) {
                clearInterval(timer);
                showToast('Nouveau contenu disponible, rechargement…');
                setTimeout(function () { window.location.reload(); }, 800);
            }
        });
    }

    function init() {
        // Get initial fingerprint immediately, then start polling
        fetchFingerprint(function (err, fingerprint) {
            if (!err) {
                initialFingerprint = fingerprint;
            }
            timer = setInterval(poll, POLL_INTERVAL);
        });
    }

    var path = window.location.pathname;
    if (path.indexOf('/club') !== -1 || path.indexOf('/event') !== -1) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    }
})();

/**
 * SPLASMA Realtime Manager.
 * Foundation only: does not start polling automatically.
 * Modules opt in with register()/start().
 */
(function (window, document) {
    'use strict';
    var jobs = new Map();
    var visible = document.visibilityState !== 'hidden';

    function register(name, fn, interval) {
        if (!name || typeof fn !== 'function') return;
        unregister(name);
        jobs.set(name, { fn: fn, interval: Math.max(1000, Number(interval) || 3000), timer: null, running: false });
    }
    function tick(name) {
        var job = jobs.get(name); if (!job || !visible) return;
        Promise.resolve().then(job.fn).catch(function () {}).finally(function () {
            if (!job.running || !visible) return;
            job.timer = setTimeout(function () { tick(name); }, job.interval);
        });
    }
    function start(name) {
        var job = jobs.get(name); if (!job) return;
        stop(name); job.running = true; tick(name);
    }
    function stop(name) {
        var job = jobs.get(name); if (!job) return;
        job.running = false; if (job.timer) { clearTimeout(job.timer); job.timer = null; }
    }
    function unregister(name) { stop(name); jobs.delete(name); }
    function pauseAll() { visible = false; jobs.forEach(function (_, name) { stop(name); }); }
    function resumeAll() { visible = true; jobs.forEach(function (_, name) { start(name); }); }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') pauseAll(); else resumeAll();
    });

    window.SPLASMA_REALTIME = { register: register, start: start, stop: stop, unregister: unregister, pauseAll: pauseAll, resumeAll: resumeAll };
})(window, document);

/**
 * Sistem Manajemen Sekolah Terpadu
 * Global UI helpers
 *
 * Sidebar:
 * - menyimpan posisi scroll antarhalaman;
 * - memulihkan posisi setelah halaman selesai dimuat;
 * - tidak memaksa sidebar kembali ke atas;
 * - menu aktif hanya digeser bila benar-benar berada di luar viewport sidebar.
 */
(function () {
    'use strict';

    console.log('Sistem Manajemen Sekolah Terpadu aktif.');

    var SIDEBAR_KEY = 'manajemen_sekolah_sidebar_scroll_top';

    function getSidebar() {
        return document.querySelector('.sidebar-menu');
    }

    function saveSidebarPosition() {
        var sidebar = getSidebar();
        if (!sidebar) {
            return;
        }

        try {
            sessionStorage.setItem(SIDEBAR_KEY, String(sidebar.scrollTop));
        } catch (e) {
            // Jika sessionStorage tidak tersedia, aplikasi tetap berjalan normal.
        }
    }

    function restoreSidebarPosition() {
        var sidebar = getSidebar();
        if (!sidebar) {
            return;
        }

        var saved = null;

        try {
            saved = sessionStorage.getItem(SIDEBAR_KEY);
        } catch (e) {
            saved = null;
        }

        if (saved === null) {
            return;
        }

        var scrollTop = parseFloat(saved);
        if (!Number.isFinite(scrollTop)) {
            return;
        }

        /*
         * Pulihkan setelah layout selesai. Dua frame membantu mencegah
         * posisi kembali ke 0 ketika tinggi sidebar baru selesai dihitung.
         */
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                var maxScroll = Math.max(0, sidebar.scrollHeight - sidebar.clientHeight);
                sidebar.scrollTop = Math.min(scrollTop, maxScroll);
            });
        });
    }

    function keepActiveMenuVisible() {
        var sidebar = getSidebar();
        if (!sidebar) {
            return;
        }

        var active = sidebar.querySelector('.menu-item.active');
        if (!active) {
            return;
        }

        /*
         * Jangan menggunakan scrollIntoView() karena dapat menggeser
         * sidebar secara agresif dan menghilangkan posisi yang baru
         * saja dipulihkan.
         *
         * Hanya koreksi jika menu aktif benar-benar berada di luar
         * area yang terlihat.
         */
        var sidebarRect = sidebar.getBoundingClientRect();
        var activeRect = active.getBoundingClientRect();
        var topGap = 8;
        var bottomGap = 8;

        if (activeRect.top < sidebarRect.top + topGap) {
            sidebar.scrollTop -= (sidebarRect.top + topGap - activeRect.top);
        } else if (activeRect.bottom > sidebarRect.bottom - bottomGap) {
            sidebar.scrollTop += (activeRect.bottom - (sidebarRect.bottom - bottomGap));
        }

        saveSidebarPosition();
    }

    function initSidebarPersistence() {
        var sidebar = getSidebar();
        if (!sidebar) {
            return;
        }

        restoreSidebarPosition();

        sidebar.addEventListener('scroll', function () {
            saveSidebarPosition();
        }, { passive: true });

        /*
         * Simpan posisi tepat sebelum navigasi. Ini menjadi cadangan
         * jika browser tidak sempat menjalankan event scroll terakhir.
         */
        sidebar.querySelectorAll('a.menu-item').forEach(function (link) {
            link.addEventListener('click', function () {
                saveSidebarPosition();
            });
        });

        /*
         * Jangan langsung menggeser menu aktif. Beri kesempatan posisi
         * sidebar sebelumnya dipulihkan terlebih dahulu.
         */
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                keepActiveMenuVisible();
            });
        });
    }

    document.addEventListener('DOMContentLoaded', initSidebarPersistence);
})();

/* ================================================================
   GLOBAL SIDEBAR CONTROLLER — EXPANDED / COLLAPSED / AUTO-HIDE
   Presentation/navigation chrome only.
================================================================ */
(function () {
    'use strict';

    var MODE_KEY = 'manajemen_sekolah_sidebar_mode';
    var MODES = ['expanded', 'collapsed', 'auto'];

    function getMode() {
        var mode = document.documentElement.getAttribute('data-sidebar-mode');
        return MODES.indexOf(mode) !== -1 ? mode : 'expanded';
    }

    function setMode(mode) {
        if (MODES.indexOf(mode) === -1) {
            mode = 'expanded';
        }

        document.documentElement.setAttribute('data-sidebar-mode', mode);

        try {
            localStorage.setItem(MODE_KEY, mode);
        } catch (e) {
            // Prefer the in-page UI state if storage is unavailable.
        }

        updateToggle();
    }

    function getNextMode() {
        var current = getMode();
        var index = MODES.indexOf(current);
        return MODES[(index + 1) % MODES.length];
    }

    function updateToggle() {
        var button = document.getElementById('globalSidebarToggle');
        if (!button) {
            return;
        }

        var mode = getMode();
        var icon = button.querySelector('i');

        var meta = {
            expanded: {
                icon: 'bi-chevron-left',
                label: 'Kecilkan sidebar',
                title: 'Kecilkan sidebar'
            },
            collapsed: {
                icon: 'bi-chevron-right',
                label: 'Aktifkan auto-hide sidebar',
                title: 'Auto-hide sidebar'
            },
            auto: {
                icon: 'bi-arrow-left-right',
                label: 'Besarkan sidebar secara manual',
                title: 'Auto-hide aktif — klik untuk membuka mode normal'
            }
        };

        var current = meta[mode];
        if (icon) {
            icon.className = 'bi ' + current.icon;
        }

        button.setAttribute('aria-label', current.label);
        button.setAttribute('title', current.title);

        /* On mobile the same button acts as drawer open/close. */
        if (window.innerWidth <= 767) {
            var open = document.documentElement.classList.contains('sidebar-mobile-open');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            button.setAttribute('aria-label', open ? 'Tutup menu navigasi' : 'Buka menu navigasi');
            button.setAttribute('title', open ? 'Tutup menu navigasi' : 'Buka menu navigasi');
            if (icon) {
                icon.className = 'bi ' + (open ? 'bi-x-lg' : 'bi-list');
            }
        } else {
            button.setAttribute('aria-expanded', mode === 'expanded' ? 'true' : 'false');
        }
    }

    function closeMobileSidebar() {
        document.documentElement.classList.remove('sidebar-mobile-open');
        updateToggle();
    }

    function initGlobalSidebar() {
        var button = document.getElementById('globalSidebarToggle');
        var overlay = document.getElementById('sidebarOverlay');
        var sidebar = document.getElementById('globalSidebar');

        if (!button || !sidebar) {
            return;
        }

        /* Fill tooltip text from the existing menu label without changing
           hrefs, active-state logic, or permission logic. */
        sidebar.querySelectorAll('.menu-item').forEach(function (item) {
            var label = item.querySelector('.menu-label');
            if (label) {
                item.setAttribute('data-menu-label', label.textContent.trim());
            }
        });

        button.addEventListener('click', function () {
            if (window.innerWidth <= 767) {
                document.documentElement.classList.toggle('sidebar-mobile-open');
                updateToggle();
                return;
            }

            setMode(getNextMode());
        });

        if (overlay) {
            overlay.addEventListener('click', closeMobileSidebar);
        }

        sidebar.addEventListener('click', function (event) {
            var link = event.target.closest('a.menu-item');
            if (link && window.innerWidth <= 767) {
                closeMobileSidebar();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && window.innerWidth <= 767) {
                closeMobileSidebar();
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 767) {
                document.documentElement.classList.remove('sidebar-mobile-open');
            }
            updateToggle();
        }, { passive: true });

        updateToggle();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGlobalSidebar);
    } else {
        initGlobalSidebar();
    }
})();

/* ================================================================
   PART90 — GROUPED SIDEBAR CONTROLLER
   Keeps the navigation compact and remembers the last opened group.
   No route, permission, or business logic changes.
================================================================ */
(function () {
    'use strict';

    var GROUP_KEY = 'manajemen_sekolah_sidebar_open_group';

    function getGroups() {
        return Array.prototype.slice.call(document.querySelectorAll('.sidebar-menu details.sidebar-group'));
    }

    function hasActiveItem(group) {
        return !!group.querySelector('.menu-item.active');
    }

    function syncActiveGroupClasses() {
        getGroups().forEach(function (group) {
            group.classList.toggle('has-active-child', hasActiveItem(group));
        });
    }

    function getStoredGroup() {
        try {
            return sessionStorage.getItem(GROUP_KEY) || '';
        } catch (e) {
            return '';
        }
    }

    function storeGroup(group) {
        try {
            sessionStorage.setItem(GROUP_KEY, group ? (group.getAttribute('data-sidebar-group') || '') : '');
        } catch (e) {
            // Sidebar tetap berfungsi normal jika storage tidak tersedia.
        }
    }

    function closeOtherGroups(current) {
        getGroups().forEach(function (group) {
            if (group !== current) {
                group.open = false;
            }
        });
    }

    function initGroupedSidebar() {
        var groups = getGroups();
        if (!groups.length) return;

        var stored = getStoredGroup();
        var activeGroup = groups.find(hasActiveItem);
        var pendingAnchor = null;

        /*
         * Native <details> changes its height immediately. If another group
         * above/below the clicked group is closed at the same time, the
         * browser may clamp scrollTop and make the sidebar appear to jump.
         * Capture the clicked summary position first, then restore that
         * visual anchor after the DOM has settled.
         */
        groups.forEach(function (group) {
            var summary = group.querySelector('.sidebar-group-toggle');
            if (summary) {
                summary.addEventListener('click', function () {
                    var sidebar = getSidebar();
                    if (!sidebar) return;
                    pendingAnchor = {
                        group: group,
                        top: summary.getBoundingClientRect().top,
                        scrollTop: sidebar.scrollTop
                    };
                }, true);
            }
        });

        if (activeGroup) {
            closeOtherGroups(activeGroup);
            activeGroup.open = true;
            syncActiveGroupClasses();
            storeGroup(activeGroup);
        } else if (stored) {
            var storedGroup = groups.find(function (group) {
                return (group.getAttribute('data-sidebar-group') || '') === stored;
            });
            if (storedGroup) {
                closeOtherGroups(storedGroup);
                storedGroup.open = true;
                syncActiveGroupClasses();
            }
        } else {
            closeOtherGroups(null);
            syncActiveGroupClasses();
        }

        groups.forEach(function (group) {
            group.addEventListener('toggle', function () {
                if (!group.open) return;

                closeOtherGroups(group);
                syncActiveGroupClasses();
                storeGroup(group);

                if (pendingAnchor && pendingAnchor.group === group) {
                    window.requestAnimationFrame(function () {
                        var sidebar = getSidebar();
                        var summary = group.querySelector('.sidebar-group-toggle');
                        if (!sidebar || !summary) {
                            pendingAnchor = null;
                            return;
                        }

                        var afterTop = summary.getBoundingClientRect().top;
                        sidebar.scrollTop += (afterTop - pendingAnchor.top);
                        saveSidebarPosition();
                        pendingAnchor = null;
                    });
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', initGroupedSidebar);
})();

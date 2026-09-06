(function () {
    'use strict';
    const body = document.body;
    const sidebar = document.querySelector('[data-sidebar]');
    const sidebarOpen = document.querySelector('[data-sidebar-open]');
    const sidebarBackdrop = document.querySelector('[data-sidebar-backdrop]');
    let returnFocus = null;

    function focusable(container) {
        return [...container.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')];
    }
    // Every navigation is a full page load, so the sidebar (a plain scrolling
    // box) always redraws scrolled to its top -- even when the link you just
    // clicked, and its "active" highlight, are further down the list. Jump
    // straight to wherever the active item actually is instead of forcing a
    // re-scroll on every click. "nearest" + no smooth-scroll keeps this a
    // silent correction rather than a visible animation.
    sidebar?.querySelector('a.active')?.scrollIntoView({ block: 'nearest' });
    const desktopSidebarQuery = window.matchMedia('(min-width: 1051px)');

    function openSidebar() {
        if (!sidebar) return;
        returnFocus = document.activeElement; sidebar.classList.add('open'); sidebarBackdrop.hidden = false;
        body.classList.add('navigation-open'); sidebarOpen?.setAttribute('aria-expanded', 'true');
        focusable(sidebar)[0]?.focus();
    }
    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('open'); sidebarBackdrop.hidden = true; body.classList.remove('navigation-open');
        sidebarOpen?.setAttribute('aria-expanded', 'false'); returnFocus?.focus();
    }
    function toggleDesktopSidebar() {
        const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
        try { localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0'); } catch (error) { /* storage unavailable */ }
        sidebarOpen?.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        sidebarOpen?.setAttribute('aria-label', collapsed ? sidebarOpen.dataset.labelExpand : sidebarOpen.dataset.labelCollapse);
    }
    sidebarOpen?.addEventListener('click', () => { desktopSidebarQuery.matches ? toggleDesktopSidebar() : openSidebar(); });
    sidebarBackdrop?.addEventListener('click', closeSidebar);
    if (sidebarOpen && document.documentElement.classList.contains('sidebar-collapsed')) {
        sidebarOpen.setAttribute('aria-expanded', 'false');
        sidebarOpen.setAttribute('aria-label', sidebarOpen.dataset.labelExpand);
    }

    // A dropdown-menu positioned inside a scrolling .table-wrap gets clipped
    // by that ancestor's overflow (needed for horizontal scroll on wide
    // tables) no matter what position value the menu itself uses -- overflow
    // clipping applies to descendants regardless of position: absolute/fixed.
    // The only real fix is a portal: temporarily reparent the open menu to
    // <body> as position:fixed, placed from the trigger button's own
    // coordinates, and move it back where it lived when closed.
    const menuOrigin = new WeakMap();
    function positionMenu(button, menu) {
        if (!menuOrigin.has(menu)) menuOrigin.set(menu, { parent: menu.parentNode, next: menu.nextSibling });
        document.body.appendChild(menu);
        menu.style.position = 'fixed';
        menu.style.insetInlineStart = 'auto';
        menu.style.insetInlineEnd = 'auto';
        menu.style.right = 'auto';
        menu.style.zIndex = '150';
        const rect = button.getBoundingClientRect();
        const menuWidth = menu.offsetWidth || 208;
        const viewportWidth = document.documentElement.clientWidth;
        const rtl = document.documentElement.dir === 'rtl';
        let left = rtl ? rect.left : rect.right - menuWidth;
        left = Math.max(8, Math.min(left, viewportWidth - menuWidth - 8));
        const viewportHeight = document.documentElement.clientHeight;
        const menuHeight = menu.offsetHeight || 0;
        const top = (rect.bottom + 8 + menuHeight > viewportHeight) ? Math.max(8, rect.top - menuHeight - 8) : rect.bottom + 8;
        menu.style.top = top + 'px';
        menu.style.left = left + 'px';
    }
    function restoreMenu(menu) {
        const origin = menuOrigin.get(menu);
        if (origin && menu.parentNode === document.body) {
            if (origin.next && origin.next.parentNode === origin.parent) origin.parent.insertBefore(menu, origin.next);
            else origin.parent.appendChild(menu);
        }
        menu.style.position = ''; menu.style.top = ''; menu.style.left = ''; menu.style.right = '';
        menu.style.insetInlineStart = ''; menu.style.insetInlineEnd = ''; menu.style.zIndex = '';
    }
    function closeMenus(exception) {
        document.querySelectorAll('[data-menu-button]').forEach(button => {
            const menu = document.getElementById(button.getAttribute('aria-controls'));
            if (button === exception) return;
            button.setAttribute('aria-expanded', 'false');
            if (menu) { menu.hidden = true; restoreMenu(menu); }
        });
    }
    document.addEventListener('scroll', () => closeMenus(), true);
    window.addEventListener('resize', () => closeMenus());
    document.querySelectorAll('[data-menu-button]').forEach(button => button.addEventListener('click', event => {
        event.stopPropagation(); const menu = document.getElementById(button.getAttribute('aria-controls')); const opening = menu?.hidden;
        closeMenus(button); if (menu) { menu.hidden = !opening; if (opening) positionMenu(button, menu); } button.setAttribute('aria-expanded', opening ? 'true' : 'false');
        if (opening) focusable(menu)[0]?.focus();
    }));
    document.addEventListener('click', () => closeMenus());

    const dialog = document.getElementById('confirm-dialog');
    let pendingForm = null;
    let pendingSubmitter = null;
    const confirmedForms = new WeakSet();
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        const message = form.dataset.confirm;
        if (message && !confirmedForms.has(form)) {
            event.preventDefault();
            if (dialog && typeof dialog.showModal === 'function') {
                pendingForm = form; pendingSubmitter = event.submitter;
                dialog.querySelector('#confirm-message').textContent = message; dialog.showModal(); return;
            }
            if (window.confirm(message)) {
                confirmedForms.add(form);
                if (event.submitter) form.requestSubmit(event.submitter); else form.requestSubmit();
            }
            return;
        }
        confirmedForms.delete(form);
        window.setTimeout(() => form.querySelectorAll('button[type="submit"],input[type="submit"]').forEach(control => { control.disabled = true; control.classList.add('loading'); }), 0);
    });
    dialog?.addEventListener('close', () => {
        if (dialog.returnValue === 'confirm' && pendingForm) {
            confirmedForms.add(pendingForm);
            if (pendingSubmitter) pendingForm.requestSubmit(pendingSubmitter); else pendingForm.requestSubmit();
        }
        pendingForm = null; pendingSubmitter = null;
    });

    document.querySelectorAll('[data-dismiss-alert]').forEach(button => button.addEventListener('click', () => button.closest('.alert')?.remove()));
    document.querySelectorAll('[data-toast-stack] .alert').forEach(toast => {
        let timer = setTimeout(() => toast.remove(), 6000);
        toast.addEventListener('mouseenter', () => clearTimeout(timer));
        toast.addEventListener('mouseleave', () => { timer = setTimeout(() => toast.remove(), 6000); });
    });
    document.querySelectorAll('.table-wrap').forEach((wrapper, index) => {
        wrapper.setAttribute('role', wrapper.getAttribute('role') || 'region');
        wrapper.setAttribute('tabindex', wrapper.getAttribute('tabindex') || '0');
        if (!wrapper.getAttribute('aria-label')) {
            const heading = wrapper.closest('.card')?.querySelector('h2,h3');
            const template = body.dataset.tableLabel || ':number';
            wrapper.setAttribute('aria-label', heading?.textContent?.trim() || template.replace(':number', String(index + 1)));
        }
        wrapper.querySelectorAll('th:not([scope])').forEach(cell => cell.setAttribute('scope', 'col'));
    });

    const reservationAllocation = document.querySelector('[data-reservation-allocation]');
    function fillReservationAllocation() {
        if (!reservationAllocation) return;
        const option = reservationAllocation.options[reservationAllocation.selectedIndex];
        const pickup = document.getElementById('pickup'); const returnAt = document.getElementById('return'); const vehicle = document.getElementById('vehicle');
        if (pickup) pickup.value = option?.dataset.pickup || '';
        if (returnAt) returnAt.value = option?.dataset.return || '';
        if (vehicle) vehicle.value = option?.dataset.vehicle || '';
    }
    reservationAllocation?.addEventListener('change', fillReservationAllocation); fillReservationAllocation();

    const planningBoard = document.querySelector('[data-planning-board]');
    if (planningBoard) {
        planningBoard.querySelector('.reservation-block')?.scrollIntoView({block: 'nearest', inline: 'nearest'});
    }

    const drawer = document.querySelector('[data-drawer]'); const drawerBackdrop = document.querySelector('[data-drawer-backdrop]');
    const drawerTitleDefault = drawer?.querySelector('#drawer-title')?.textContent ?? '';
    function closeDrawer() { if (!drawer) return; drawer.hidden = true; drawer.classList.remove('open'); drawerBackdrop.hidden = true; body.classList.remove('drawer-open'); returnFocus?.focus(); const titleEl = drawer.querySelector('#drawer-title'); if (titleEl) titleEl.textContent = drawerTitleDefault; }
    document.querySelectorAll('[data-drawer-target]').forEach(button => button.addEventListener('click', () => {
        const source = document.querySelector(button.dataset.drawerTarget); if (!drawer || !source) return; returnFocus = button;
        drawer.querySelector('[data-drawer-body]').replaceChildren(source.content ? source.content.cloneNode(true) : source.cloneNode(true));
        const titleEl = drawer.querySelector('#drawer-title'); if (titleEl) titleEl.textContent = button.dataset.drawerTitle || drawerTitleDefault;
        drawer.hidden = false; drawerBackdrop.hidden = false; body.classList.add('drawer-open'); requestAnimationFrame(() => drawer.classList.add('open')); focusable(drawer)[0]?.focus();
    }));
    document.querySelector('[data-drawer-close]')?.addEventListener('click', closeDrawer); drawerBackdrop?.addEventListener('click', closeDrawer);

    function syncHashNavigation() {
        const hash = window.location.hash;
        if (!hash) return;
        const anchorLink = document.querySelector('.nav-group a[href$="' + hash + '"]');
        if (!anchorLink) return;
        document.querySelectorAll('.nav-group a.active').forEach(link => {
            link.classList.remove('active'); link.removeAttribute('aria-current');
        });
        anchorLink.classList.add('active'); anchorLink.setAttribute('aria-current', 'page');
    }
    syncHashNavigation();
    window.addEventListener('hashchange', syncHashNavigation);

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') { closeMenus(); if (sidebar?.classList.contains('open')) closeSidebar(); if (drawer?.classList.contains('open')) closeDrawer(); }
        if (event.key === 'Tab') {
            const container = drawer?.classList.contains('open') ? drawer : sidebar?.classList.contains('open') ? sidebar : null;
            if (!container) return; const items = focusable(container); if (!items.length) return;
            if (event.shiftKey && document.activeElement === items[0]) { event.preventDefault(); items.at(-1).focus(); }
            else if (!event.shiftKey && document.activeElement === items.at(-1)) { event.preventDefault(); items[0].focus(); }
        }
    });
})();

(function () {
    'use strict';
    const body = document.body;
    const sidebar = document.querySelector('[data-sidebar]');
    const sidebarOpen = document.querySelector('[data-sidebar-open]');
    const sidebarClose = document.querySelector('[data-sidebar-close]');
    const sidebarBackdrop = document.querySelector('[data-sidebar-backdrop]');
    let returnFocus = null;

    function focusable(container) {
        return [...container.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')];
    }
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
    sidebarOpen?.addEventListener('click', openSidebar); sidebarClose?.addEventListener('click', closeSidebar); sidebarBackdrop?.addEventListener('click', closeSidebar);

    function closeMenus(exception) {
        document.querySelectorAll('[data-menu-button]').forEach(button => {
            const menu = document.getElementById(button.getAttribute('aria-controls'));
            if (button === exception) return;
            button.setAttribute('aria-expanded', 'false'); if (menu) menu.hidden = true;
        });
    }
    document.querySelectorAll('[data-menu-button]').forEach(button => button.addEventListener('click', event => {
        event.stopPropagation(); const menu = document.getElementById(button.getAttribute('aria-controls')); const opening = menu?.hidden;
        closeMenus(button); if (menu) menu.hidden = !opening; button.setAttribute('aria-expanded', opening ? 'true' : 'false');
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
    function closeDrawer() { if (!drawer) return; drawer.hidden = true; drawer.classList.remove('open'); drawerBackdrop.hidden = true; body.classList.remove('drawer-open'); returnFocus?.focus(); }
    document.querySelectorAll('[data-drawer-target]').forEach(button => button.addEventListener('click', () => {
        const source = document.querySelector(button.dataset.drawerTarget); if (!drawer || !source) return; returnFocus = button;
        drawer.querySelector('[data-drawer-body]').replaceChildren(source.content ? source.content.cloneNode(true) : source.cloneNode(true));
        drawer.hidden = false; drawerBackdrop.hidden = false; body.classList.add('drawer-open'); requestAnimationFrame(() => drawer.classList.add('open')); focusable(drawer)[0]?.focus();
    }));
    document.querySelector('[data-drawer-close]')?.addEventListener('click', closeDrawer); drawerBackdrop?.addEventListener('click', closeDrawer);

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

"use strict";
const initTabs = () => {
    const tabs = Array.from(document.querySelectorAll('[data-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-tab-panel]'));
    if (tabs.length === 0 || panels.length === 0) {
        return;
    }
    const setActiveTab = (activeTab) => {
        tabs.forEach((button) => {
            const isActive = button === activeTab;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            const parent = button.closest('li');
            if (parent) {
                parent.classList.toggle('is-active', isActive);
            }
        });
    };
    tabs.forEach((tab) => {
        tab.addEventListener('click', (event) => {
            event.preventDefault();
            const target = tab.getAttribute('data-tab');
            setActiveTab(tab);
            panels.forEach((panel) => {
                const isActive = panel.getAttribute('data-tab-panel') === target;
                panel.classList.toggle('is-hidden', !isActive);
                if (isActive) {
                    document.dispatchEvent(new CustomEvent('tab:activated', { detail: { tab: target } }));
                }
            });
        });
    });
    const activeTab = tabs.find((tab) => {
        var _a;
        const parent = tab.closest('li');
        return (_a = parent === null || parent === void 0 ? void 0 : parent.classList.contains('is-active')) !== null && _a !== void 0 ? _a : false;
    });
    if (activeTab) {
        setActiveTab(activeTab);
    }
};
initTabs();

"use strict";
const initAdminTabs = () => {
    const tabs = Array.from(document.querySelectorAll('[data-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-tab-panel]'));
    if (tabs.length === 0 || panels.length === 0) {
        return;
    }
    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const target = tab.getAttribute('data-tab');
            tabs.forEach((button) => {
                button.classList.toggle('active', button === tab);
                button.setAttribute('aria-selected', button === tab ? 'true' : 'false');
            });
            panels.forEach((panel) => {
                panel.classList.toggle('active', panel.getAttribute('data-tab-panel') === target);
            });
        });
    });
};
initAdminTabs();

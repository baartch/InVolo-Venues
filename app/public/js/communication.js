"use strict";
const dismissNotifications = () => {
    const notificationBlocks = Array.from(document.querySelectorAll('[data-auto-dismiss]'));
    if (notificationBlocks.length === 0) {
        return;
    }
    window.setTimeout(() => {
        notificationBlocks.forEach((block) => {
            block.classList.add('is-hidden');
        });
    }, 5000);
};
dismissNotifications();

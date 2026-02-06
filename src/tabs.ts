const initTabs = (): void => {
  const tabs = Array.from(document.querySelectorAll<HTMLElement>('[data-tab]'));
  const panels = Array.from(document.querySelectorAll<HTMLElement>('[data-tab-panel]'));

  if (tabs.length === 0 || panels.length === 0) {
    return;
  }

  tabs.forEach((tab) => {
    tab.addEventListener('click', (event) => {
      event.preventDefault();
      const target = tab.getAttribute('data-tab');
      tabs.forEach((button) => {
        button.classList.toggle('is-active', button === tab);
        button.setAttribute('aria-selected', button === tab ? 'true' : 'false');
      });
      panels.forEach((panel) => {
        panel.classList.toggle('is-hidden', panel.getAttribute('data-tab-panel') !== target);
      });
    });
  });
};

initTabs();

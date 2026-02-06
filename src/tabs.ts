const initTabs = (): void => {
  const tabs = Array.from(document.querySelectorAll<HTMLElement>('[data-tab]'));
  const panels = Array.from(document.querySelectorAll<HTMLElement>('[data-tab-panel]'));

  if (tabs.length === 0 || panels.length === 0) {
    return;
  }

  const setActiveTab = (activeTab: HTMLElement): void => {
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
        panel.classList.toggle('is-hidden', panel.getAttribute('data-tab-panel') !== target);
      });
    });
  });

  const activeTab = tabs.find((tab) => {
    const parent = tab.closest('li');
    return parent?.classList.contains('is-active') ?? false;
  });
  if (activeTab) {
    setActiveTab(activeTab);
  }
};

initTabs();

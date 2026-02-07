const initWysiEditor = (): void => {
  const textarea = document.querySelector<HTMLTextAreaElement>('#email_body');

  if (!textarea) {
    return;
  }

  const wysi = window as typeof window & {
    Wysi?: (options: { el: string }) => void;
  };

  if (typeof wysi.Wysi !== 'function') {
    return;
  }

  wysi.Wysi({
    el: '#email_body'
  });
};

const bindWysiEditor = (): void => {
  initWysiEditor();
  document.addEventListener('tab:activated', () => {
    initWysiEditor();
  });
};

document.addEventListener('DOMContentLoaded', () => {
  bindWysiEditor();
});

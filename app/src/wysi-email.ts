const initWysiEditor = (): void => {
  const textarea = document.querySelector<HTMLTextAreaElement>("#email_body");

  if (!textarea) {
    return;
  }

  const wysi = window as typeof window & {
    Wysi?: (options: { el: string; darkMode?: boolean }) => void;
  };

  if (typeof wysi.Wysi !== "function") {
    return;
  }

  const darkModeMql =
    window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)");
  const prefersDarkMode = darkModeMql && darkModeMql.matches;
  wysi.Wysi({
    el: "#email_body",
    darkMode: prefersDarkMode,
  });
};

const bindWysiEditor = (): void => {
  initWysiEditor();
  document.addEventListener("tab:activated", () => {
    initWysiEditor();
  });
};

document.addEventListener("DOMContentLoaded", () => {
  bindWysiEditor();
});

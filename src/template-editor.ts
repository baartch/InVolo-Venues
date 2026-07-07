import { getStoredTheme } from "./appearance.js";

type HugeRteEditor = {
  save: () => void;
  getContent: () => string;
  on: (event: string, callback: () => void) => void;
};

type HugeRteStatic = {
  init: (options: Record<string, unknown>) => Promise<unknown>;
};

const resolveHugerte = (): HugeRteStatic | null => {
  const w = window as typeof window & { hugerte?: HugeRteStatic };
  return w.hugerte ?? null;
};

const isDarkMode = (): boolean => {
  const storedTheme = getStoredTheme();
  const darkModeMql =
    window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)");
  const prefersDarkMode = darkModeMql && darkModeMql.matches;
  return storedTheme === "dark" || (storedTheme !== "light" && prefersDarkMode);
};

const initTemplateEditor = (): void => {
  const textarea =
    document.querySelector<HTMLTextAreaElement>("#template_body");
  if (!textarea) {
    return;
  }

  const hugerte = resolveHugerte();
  if (!hugerte) {
    // HugeRTE deferred script may not have executed yet; retry on next tick.
    window.setTimeout(initTemplateEditor, 50);
    return;
  }

  hugerte.init({
    selector: "#template_body",
    menubar: false,
    plugins: "lists link image table code",
    toolbar:
      "undo redo | styles | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | code",
    skin: isDarkMode() ? "oxide-dark" : "oxide",
    content_css: isDarkMode() ? "dark" : "default",
    branding: false,
    promotion: false,
    height: 360,
    setup: (editor: HugeRteEditor) => {
      // Sync editor content back to the textarea before form submit.
      editor.on("Submit", () => editor.save());
    },
  });
};

document.addEventListener("DOMContentLoaded", () => {
  initTemplateEditor();
});

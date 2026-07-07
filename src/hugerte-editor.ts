import { getStoredTheme } from "./appearance.js";

type HugeRteEditor = {
  save: () => void;
  getContent: () => string;
  setContent: (content: string) => void;
  on: (event: string, callback: () => void) => void;
  container?: HTMLElement;
};

type HugeRteStatic = {
  init: (options: Record<string, unknown>) => Promise<unknown>;
  get?: (selector: string) => HugeRteEditor | null;
};

type HugerteWindow = typeof window & { hugerte?: HugeRteStatic };

const resolveHugerte = (): HugeRteStatic | null => {
  const w = window as HugerteWindow;
  return w.hugerte ?? null;
};

const isDarkMode = (): boolean => {
  const storedTheme = getStoredTheme();
  const darkModeMql =
    window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)");
  const prefersDarkMode = darkModeMql && darkModeMql.matches;
  return storedTheme === "dark" || (storedTheme !== "light" && prefersDarkMode);
};

const escapeHtml = (value: string): string =>
  value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");

const sanitizeInitialHtml = (value: string): string => {
  if (!value.includes("<") || !value.includes(">")) {
    return value;
  }

  const template = document.createElement("template");
  template.innerHTML = value;

  template.content.querySelectorAll<HTMLElement>("[align]").forEach((node) => {
    node.removeAttribute("align");
  });

  return template.innerHTML;
};

export type InitHugerteOptions = {
  selector: string;
  height?: number;
  plugins?: string;
  toolbar?: string;
  onSetup?: (editor: HugeRteEditor) => void;
};

const defaultPlugins = "lists link image table code";

const defaultToolbar =
  "undo redo | styles | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | code";

const initHugerte = (options: InitHugerteOptions): void => {
  const textarea = document.querySelector<HTMLTextAreaElement>(
    options.selector,
  );
  if (!textarea) {
    return;
  }

  // Avoid double-init when re-invoked after HTMX swaps or tab activations.
  if (textarea.dataset.hugerteBound === "true") {
    return;
  }
  textarea.dataset.hugerteBound = "true";

  const hugerte = resolveHugerte();
  if (!hugerte) {
    // HugeRTE deferred script may not have executed yet; retry on next tick.
    textarea.dataset.hugerteBound = "false";
    window.setTimeout(() => initHugerte(options), 50);
    return;
  }

  textarea.value = sanitizeInitialHtml(textarea.value);

  hugerte.init({
    selector: options.selector,
    menubar: false,
    plugins: options.plugins ?? defaultPlugins,
    toolbar: options.toolbar ?? defaultToolbar,
    skin: isDarkMode() ? "oxide-dark" : "oxide",
    content_css: isDarkMode() ? "dark" : "default",
    branding: false,
    promotion: false,
    height: options.height ?? 360,
    setup: (editor: HugeRteEditor) => {
      // Sync editor content back to the textarea before form submit.
      editor.on("Submit", () => editor.save());
      options.onSetup?.(editor);
    },
  });
};

/**
 * Returns the HugeRTE editor instance for a selector, if available.
 * Useful for reading content or attaching listeners after init.
 */
export const getHugerteEditor = (selector: string): HugeRteEditor | null => {
  const hugerte = resolveHugerte();
  if (!hugerte || typeof hugerte.get !== "function") {
    return null;
  }
  return hugerte.get(selector);
};

/**
 * Sanitizes pasted plain text into paragraph HTML and inserts it into the
 * active HugeRTE editor. Mirrors the previous Wysi paste behavior.
 */
export const initHugertePlainPaste = (selector: string): void => {
  const textarea = document.querySelector<HTMLTextAreaElement>(selector);
  if (!textarea) {
    return;
  }

  // HugeRTE renders its editable iframe/container as a sibling before the textarea.
  const wrapper = textarea.parentElement;
  if (!wrapper) {
    return;
  }

  const editor = wrapper.querySelector<HTMLElement>(".tox-tinymce") ?? null;
  if (!editor || editor.dataset.plainPasteBound === "true") {
    return;
  }

  editor.dataset.plainPasteBound = "true";
  editor.addEventListener("paste", (event: ClipboardEvent) => {
    if (!event.clipboardData || event.clipboardData.files.length > 0) {
      return;
    }

    const plainText = event.clipboardData.getData("text/plain");
    if (!plainText) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    const normalized = plainText
      .replace(/\r\n?/g, "\n")
      .replace(/\u00A0/g, " ")
      .replace(/[ \t]+\n/g, "\n")
      .replace(/\n{3,}/g, "\n\n");

    const blocks = normalized
      .split(/\n\n+/)
      .map((part) => part.trim())
      .filter((part) => part !== "");

    const html = (blocks.length ? blocks : [""])
      .map((block) => {
        if (block === "") {
          return "<p><br></p>";
        }
        const content = block
          .split("\n")
          .map((line) => escapeHtml(line.trim()))
          .join("<br>");
        return `<p>${content || "<br>"}</p>`;
      })
      .join("");

    document.execCommand("insertHTML", false, html);
    editor.dispatchEvent(new Event("input", { bubbles: true }));
  });
};

export { initHugerte };

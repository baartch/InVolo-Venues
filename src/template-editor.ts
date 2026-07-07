import { initHugerte } from "./hugerte-editor.js";

const initTemplateEditor = (): void => {
  initHugerte({ selector: "#template_body", height: 360 });
};

document.addEventListener("DOMContentLoaded", () => {
  initTemplateEditor();
});

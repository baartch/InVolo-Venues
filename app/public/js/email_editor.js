"use strict";
const initSquireEditor = () => {
    const textarea = document.querySelector('#email_body');
    const editorNode = document.querySelector('[data-squire-editor]');
    if (!textarea || !editorNode) {
        return;
    }
    if (!('Squire' in window)) {
        return;
    }
    const editor = new window.Squire(editorNode);
    editor.setHTML(textarea.value || '');
    const form = textarea.closest('form');
    if (!form) {
        return;
    }
    form.addEventListener('submit', () => {
        textarea.value = editor.getHTML();
    });
};
const bindEditorInit = () => {
    initSquireEditor();
    document.addEventListener('tab:activated', () => {
        initSquireEditor();
    });
};
document.addEventListener('DOMContentLoaded', () => {
    bindEditorInit();
});

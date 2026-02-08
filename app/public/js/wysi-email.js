"use strict";
const initWysiEditor = () => {
    const textarea = document.querySelector("#email_body");
    if (!textarea) {
        return;
    }
    const wysi = window;
    if (typeof wysi.Wysi !== "function") {
        return;
    }
    const darkModeMql = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)");
    const prefersDarkMode = darkModeMql && darkModeMql.matches;
    wysi.Wysi({
        el: "#email_body",
        darkMode: prefersDarkMode,
    });
};
const bindWysiEditor = () => {
    initWysiEditor();
    document.addEventListener("tab:activated", () => {
        initWysiEditor();
    });
};
document.addEventListener("DOMContentLoaded", () => {
    bindWysiEditor();
});

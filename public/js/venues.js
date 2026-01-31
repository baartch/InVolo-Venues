"use strict";
const qs = (selector, scope = document) => scope.querySelector(selector);
const qsAll = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));
const createDebounce = (callback, delay) => {
    let timerId = null;
    const clearTimer = () => {
        if (timerId !== null) {
            window.clearTimeout(timerId);
            timerId = null;
        }
    };
    const trigger = () => {
        clearTimer();
        timerId = window.setTimeout(callback, delay);
    };
    const flush = () => {
        clearTimer();
        callback();
    };
    return { trigger, flush, clear: clearTimer };
};
const initImportModal = () => {
    const importToggle = qs('[data-import-toggle]');
    const importModal = qs('[data-import-modal]');
    const importClose = qs('[data-import-close]');
    if (!importModal) {
        return;
    }
    const setOpen = (isOpen) => {
        importModal.classList.toggle('open', isOpen);
    };
    importToggle === null || importToggle === void 0 ? void 0 : importToggle.addEventListener('click', (event) => {
        event.stopPropagation();
        setOpen(true);
    });
    importClose === null || importClose === void 0 ? void 0 : importClose.addEventListener('click', (event) => {
        event.stopPropagation();
        setOpen(false);
    });
    importModal.addEventListener('click', (event) => {
        if (event.target === importModal) {
            setOpen(false);
        }
    });
    if (importModal.dataset.initialOpen === 'true') {
        setOpen(true);
    }
};
const initFilterForm = () => {
    const filterForm = qs('[data-filter-form]');
    const filterInput = filterForm ? qs('input[name="filter"]', filterForm) : null;
    const filterClear = qs('[data-filter-clear]');
    if (!filterForm || !filterInput) {
        return;
    }
    const focusKey = 'venueFilterFocus';
    const pageInput = qs('input[name="page"]', filterForm);
    const setClearVisible = (visible) => {
        if (!filterClear) {
            return;
        }
        filterClear.classList.toggle('is-hidden', !visible);
        filterClear.setAttribute('aria-hidden', visible ? 'false' : 'true');
    };
    const submitFilter = () => {
        sessionStorage.setItem(focusKey, '1');
        if (pageInput) {
            pageInput.value = '1';
        }
        filterForm.submit();
    };
    const debounce = createDebounce(submitFilter, 500);
    if (sessionStorage.getItem(focusKey) === '1') {
        filterInput.focus();
        const valueLength = filterInput.value.length;
        filterInput.setSelectionRange(valueLength, valueLength);
        sessionStorage.removeItem(focusKey);
    }
    filterInput.addEventListener('input', () => {
        setClearVisible(filterInput.value.trim() !== '');
        debounce.trigger();
    });
    filterInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            debounce.flush();
        }
    });
    if (filterClear) {
        const clearFilter = () => {
            debounce.clear();
            filterInput.value = '';
            setClearVisible(false);
            submitFilter();
        };
        filterClear.addEventListener('click', clearFilter);
        filterClear.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                clearFilter();
            }
        });
    }
    setClearVisible(filterInput.value.trim() !== '');
};
const initSorting = () => {
    const venuesTable = qs('.table');
    if (!venuesTable) {
        return;
    }
    const headers = qsAll('thead th[data-sort]', venuesTable);
    let sortIndex = null;
    let sortDirection = 'asc';
    const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
    const getCellValue = (row, index) => {
        var _a, _b;
        const cell = row.children[index];
        if (!cell) {
            return '';
        }
        const link = cell.querySelector('a');
        return ((_b = (_a = link === null || link === void 0 ? void 0 : link.textContent) !== null && _a !== void 0 ? _a : cell.textContent) !== null && _b !== void 0 ? _b : '').trim();
    };
    const compareValues = (a, b, type) => {
        if (type === 'number') {
            const numberA = Number.parseFloat(a.replace(/[^0-9.-]/g, ''));
            const numberB = Number.parseFloat(b.replace(/[^0-9.-]/g, ''));
            if (Number.isNaN(numberA) && Number.isNaN(numberB)) {
                return 0;
            }
            if (Number.isNaN(numberA)) {
                return 1;
            }
            if (Number.isNaN(numberB)) {
                return -1;
            }
            return numberA - numberB;
        }
        return collator.compare(a, b);
    };
    const updateHeaderState = () => {
        headers.forEach((header) => {
            var _a;
            const label = header.dataset.sortLabel || ((_a = header.textContent) === null || _a === void 0 ? void 0 : _a.trim()) || '';
            header.dataset.sortLabel = label;
            header.classList.remove('is-sorted', 'is-sorted-asc', 'is-sorted-desc');
            if (header.cellIndex === sortIndex) {
                const arrow = sortDirection === 'asc' ? ' ▲' : ' ▼';
                header.textContent = `${label}${arrow}`;
                header.classList.add('is-sorted', sortDirection === 'asc' ? 'is-sorted-asc' : 'is-sorted-desc');
            }
            else {
                header.textContent = label;
            }
        });
    };
    const sortRows = (cellIndex, direction, header) => {
        const body = venuesTable.querySelector('tbody');
        if (!body) {
            return;
        }
        const type = header.dataset.sortType || 'text';
        const rows = qsAll('tr', body);
        rows.sort((rowA, rowB) => {
            const valueA = getCellValue(rowA, cellIndex);
            const valueB = getCellValue(rowB, cellIndex);
            const comparison = compareValues(valueA, valueB, type);
            return direction === 'asc' ? comparison : -comparison;
        });
        rows.forEach((row) => body.appendChild(row));
    };
    headers.forEach((header) => {
        header.setAttribute('role', 'button');
        header.setAttribute('tabindex', '0');
        header.addEventListener('click', () => {
            const cellIndex = header.cellIndex;
            const isSameColumn = sortIndex === cellIndex;
            sortIndex = cellIndex;
            sortDirection = isSameColumn && sortDirection === 'asc' ? 'desc' : 'asc';
            sortRows(cellIndex, sortDirection, header);
            updateHeaderState();
        });
        header.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                header.click();
            }
        });
    });
};
const initVenuesPage = () => {
    initImportModal();
    initFilterForm();
    initSorting();
};
initVenuesPage();

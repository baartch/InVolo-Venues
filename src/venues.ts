const qs = <T extends Element>(selector: string, scope: ParentNode = document): T | null =>
  scope.querySelector(selector) as T | null;

const qsAll = <T extends Element>(selector: string, scope: ParentNode = document): T[] =>
  Array.from(scope.querySelectorAll(selector)) as T[];

const createDebounce = (callback: () => void, delay: number) => {
  let timerId: number | null = null;

  const clearTimer = (): void => {
    if (timerId !== null) {
      window.clearTimeout(timerId);
      timerId = null;
    }
  };

  const trigger = (): void => {
    clearTimer();
    timerId = window.setTimeout(callback, delay);
  };

  const flush = (): void => {
    clearTimer();
    callback();
  };

  return { trigger, flush, clear: clearTimer };
};

const initImportModal = (): void => {
  const importToggle = qs<HTMLElement>('[data-import-toggle]');
  const importModal = qs<HTMLElement>('[data-import-modal]');
  const importCloseButtons = qsAll<HTMLElement>('[data-import-close]');

  if (!importModal) {
    return;
  }

  const setOpen = (isOpen: boolean): void => {
    importModal.classList.toggle('is-active', isOpen);
  };

  importToggle?.addEventListener('click', (event) => {
    event.stopPropagation();
    setOpen(true);
  });

  importCloseButtons.forEach((button) => {
    button.addEventListener('click', (event) => {
      event.stopPropagation();
      setOpen(false);
    });
  });

  if (importModal.dataset.initialOpen === 'true') {
    setOpen(true);
  }
};

const initFilterForm = (): void => {
  const filterForm = qs<HTMLFormElement>('[data-filter-form]');
  const filterInput = filterForm ? qs<HTMLInputElement>('input[name="filter"]', filterForm) : null;
  const filterClear = qs<HTMLElement>('[data-filter-clear]');

  if (!filterForm || !filterInput) {
    return;
  }

  const focusKey = 'venueFilterFocus';
  const pageInput = qs<HTMLInputElement>('input[name="page"]', filterForm);
  const setClearVisible = (visible: boolean): void => {
    if (!filterClear) {
      return;
    }
    filterClear.toggleAttribute('disabled', !visible);
    filterClear.classList.toggle('is-static', !visible);
    filterClear.classList.toggle('is-light', !visible);
    filterClear.setAttribute('aria-hidden', visible ? 'false' : 'true');
  };

  const submitFilter = (): void => {
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
    const clearFilter = (): void => {
      if (filterClear.hasAttribute('disabled')) {
        return;
      }
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

const initSorting = (): void => {
  const venuesTable = qs<HTMLTableElement>('[data-venues-table]');
  if (!venuesTable) {
    return;
  }

  const headers = qsAll<HTMLTableCellElement>('thead th[data-sort]', venuesTable);
  let sortIndex: number | null = null;
  let sortDirection: 'asc' | 'desc' = 'asc';

  const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });

  const getCellValue = (row: HTMLTableRowElement, index: number): string => {
    const cell = row.children[index] as HTMLElement | undefined;
    if (!cell) {
      return '';
    }
    const link = cell.querySelector('a');
    return (link?.textContent ?? cell.textContent ?? '').trim();
  };

  const compareValues = (a: string, b: string, type: string): number => {
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

  const updateHeaderState = (): void => {
    headers.forEach((header) => {
      const label = header.dataset.sortLabel || header.textContent?.trim() || '';
      header.dataset.sortLabel = label;
      header.classList.remove('is-sorted', 'is-sorted-asc', 'is-sorted-desc');
      if (header.cellIndex === sortIndex) {
        const arrow = sortDirection === 'asc' ? ' ▲' : ' ▼';
        header.textContent = `${label}${arrow}`;
        header.classList.add('is-sorted', sortDirection === 'asc' ? 'is-sorted-asc' : 'is-sorted-desc');
      } else {
        header.textContent = label;
      }
    });
  };

  const sortRows = (cellIndex: number, direction: 'asc' | 'desc', header: HTMLTableCellElement): void => {
    const body = venuesTable.querySelector('tbody');
    if (!body) {
      return;
    }
    const type = header.dataset.sortType || 'text';
    const rows = qsAll<HTMLTableRowElement>('tr', body);

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

const initVenueDetails = (): void => {
  const table = qs<HTMLTableElement>('.table');
  if (!table) {
    return;
  }

  const toggleButtons = qsAll<HTMLButtonElement>('[data-venue-info-toggle]', table);
  toggleButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const row = button.closest('tr');
      if (!row) {
        return;
      }
      const detailsRow = row.nextElementSibling as HTMLTableRowElement | null;
      if (!detailsRow || !detailsRow.hasAttribute('data-venue-details')) {
        return;
      }
      const isHidden = detailsRow.classList.contains('is-hidden');
      detailsRow.classList.toggle('is-hidden', !isHidden);
      button.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
    });
  });
};

const initVenuesPage = (): void => {
  initImportModal();
  initFilterForm();
  initSorting();
  initVenueDetails();
};

initVenuesPage();

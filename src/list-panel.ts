export {};

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

const initListSearch = (): void => {
  const searchForm = qs<HTMLFormElement>('[data-filter-form]');
  const searchInput = searchForm ? qs<HTMLInputElement>('input[type="text"]', searchForm) : null;
  if (!searchForm || !searchInput) {
    return;
  }

  const focusKey = 'listFilterFocus';

  const submitSearch = (): void => {
    sessionStorage.setItem(focusKey, '1');
    searchForm.submit();
  };

  const debounce = createDebounce(submitSearch, 500);

  if (sessionStorage.getItem(focusKey) === '1') {
    searchInput.focus();
    const valueLength = searchInput.value.length;
    searchInput.setSelectionRange(valueLength, valueLength);
    sessionStorage.removeItem(focusKey);
  }

  searchInput.addEventListener('input', () => {
    debounce.trigger();
  });

  searchInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      debounce.flush();
    }
  });

  document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
      event.preventDefault();
      searchInput.focus();
      searchInput.select();
    }
  });
};

const focusRowLink = (row: HTMLElement): void => {
  const link = row.getAttribute('data-row-link');
  if (!link) {
    return;
  }

  const detailTarget = row.getAttribute('data-row-target');
  const swap = row.getAttribute('data-row-swap') || 'innerHTML';
  const pushUrl = row.getAttribute('data-row-push-url');
  const htmx = (window as Window & {
    htmx?: { ajax: (method: string, url: string, config?: Record<string, unknown>) => void }
  }).htmx;

  if (detailTarget && htmx) {
    const config: Record<string, unknown> = {
      target: detailTarget,
      swap,
    };

    config.pushUrl = pushUrl !== null ? (pushUrl !== '' ? pushUrl : link) : link;

    htmx.ajax('GET', link, config);
    return;
  }

  window.location.href = link;
};

const initTableSortPersistence = (): void => {
  const table = qs<HTMLTableElement>('table[data-sort-table-type]');
  if (!table) {
    return;
  }

  const tableType = table.dataset.sortTableType?.trim() ?? '';
  const sortParamKey = table.dataset.sortParamKey?.trim() || 'sort';
  const sortParamDirection = table.dataset.sortParamDirection?.trim() || 'dir';
  const sortPageParam = table.dataset.sortPageParam?.trim() || 'page';
  if (tableType === '') {
    return;
  }

  const storageKey = `tableSort:${tableType}`;
  const url = new URL(window.location.href);
  const currentSort = url.searchParams.get(sortParamKey);
  const currentDirection = url.searchParams.get(sortParamDirection);

  if (!currentSort || !currentDirection) {
    const savedRaw = localStorage.getItem(storageKey);
    if (savedRaw) {
      try {
        const saved = JSON.parse(savedRaw) as { key?: string; direction?: string };
        const key = (saved.key ?? '').trim();
        const direction = (saved.direction ?? '').toLowerCase();
        if (key !== '' && (direction === 'asc' || direction === 'desc')) {
          url.searchParams.set(sortParamKey, key);
          url.searchParams.set(sortParamDirection, direction);
          url.searchParams.set(sortPageParam, '1');
          window.location.replace(url.toString());
          return;
        }
      } catch {
        localStorage.removeItem(storageKey);
      }
    }
  }

  qsAll<HTMLAnchorElement>('a[data-table-sort]', table).forEach((link) => {
    link.addEventListener('click', () => {
      const key = link.dataset.sortKey?.trim() ?? '';
      const direction = (link.dataset.sortDirection ?? '').toLowerCase();
      if (key === '' || (direction !== 'asc' && direction !== 'desc')) {
        return;
      }
      localStorage.setItem(storageKey, JSON.stringify({ key, direction }));
    });
  });
};

const initListSelection = (): void => {
  const emptyStates = new Map<HTMLElement, { target: HTMLElement; html: string }>();

  document.querySelectorAll<HTMLElement>('[data-list-selectable]').forEach((list) => {
    const row = list.querySelector<HTMLElement>('[data-row-target]');
    if (!row) {
      return;
    }
    const targetSelector = row.getAttribute('data-row-target');
    if (!targetSelector) {
      return;
    }
    const target = document.querySelector<HTMLElement>(targetSelector);
    if (!target) {
      return;
    }
    emptyStates.set(list, { target, html: target.innerHTML });
  });

  document.addEventListener('click', (event) => {
    const target = event.target as HTMLElement | null;
    if (!target) {
      return;
    }

    const row = target.closest<HTMLElement>('[data-list-item]');
    if (!row) {
      return;
    }

    const list = row.closest<HTMLElement>('[data-list-selectable]');
    if (!list) {
      return;
    }

    const ignoreElement = target.closest('[data-list-ignore]');
    if (ignoreElement) {
      return;
    }

    const interactiveElement = target.closest('a, button, input, select, textarea, label');
    const activeClass = list.dataset.listActiveClass || 'is-active';

    if (!interactiveElement && row.classList.contains(activeClass)) {
      event.preventDefault();
      row.classList.remove(activeClass);
      const emptyState = emptyStates.get(list);
      if (emptyState) {
        emptyState.target.innerHTML = emptyState.html;
      }
      return;
    }

    if (!interactiveElement && row.hasAttribute('data-row-link')) {
      event.preventDefault();
      focusRowLink(row);
    }

    const items = list.querySelectorAll<HTMLElement>('[data-list-item]');
    items.forEach((element) => {
      element.classList.remove(activeClass);
    });

    row.classList.add(activeClass);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }

    const target = event.target as HTMLElement | null;
    if (!target) {
      return;
    }

    if (!target.matches('[data-list-item]')) {
      return;
    }

    if (!target.hasAttribute('data-row-link')) {
      return;
    }

    event.preventDefault();
    focusRowLink(target);
  });
};

initListSearch();
initTableSortPersistence();
initListSelection();

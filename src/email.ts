import { initHugerte, initHugertePlainPaste } from "./hugerte-editor.js";

type TemplateData = {
  name: string;
  subject: string;
  body: string;
};

const readTemplates = (): TemplateData[] => {
  const script = document.querySelector<HTMLScriptElement>(
    "[data-email-templates]",
  );
  if (!script || !script.textContent) {
    return [];
  }
  try {
    const parsed = JSON.parse(script.textContent) as TemplateData[];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
};

const initHugerteEditor = (): void => {
  initHugerte({
    selector: "#email_body",
    height: 420,
    templates: readTemplates(),
  });
};

const initHugertePasteSanitizer = (): void => {
  initHugertePlainPaste("#email_body");
};

const isValidEmail = (email: string): boolean => {
  if (email === "") {
    return true;
  }
  return /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i.test(email);
};

const normalizeEmailList = (value: string): string => {
  const trimmed = value.trim();
  if (trimmed === "") {
    return "";
  }

  const parts = trimmed
    .split(/[;,\n]+/)
    .map((item) => item.trim())
    .filter((part) => part !== "");

  const seen = new Set<string>();
  const unique: string[] = [];
  parts.forEach((part) => {
    const key = part.toLowerCase();
    if (seen.has(key)) {
      return;
    }
    seen.add(key);
    unique.push(part);
  });

  return unique.join(", ");
};

const getLastEmailToken = (value: string): string => {
  const parts = value.split(/[;,\n]/);
  return (parts[parts.length - 1] ?? "").trim();
};

const replaceLastEmailToken = (value: string, email: string): string => {
  const parts = value.split(/[;,\n]/).map((item) => item.trim());
  const prefix = parts.slice(0, -1).filter((part) => part !== "");
  return [...prefix, email].join(", ");
};

const validateEmailInput = (input: HTMLInputElement): boolean => {
  const raw = input.value.trim();
  const parts =
    raw === ""
      ? []
      : raw
          .split(/[;,\n]+/)
          .map((item) => item.trim())
          .filter((part) => part !== "");
  const invalid = parts.some((part) => !isValidEmail(part));
  input.classList.toggle("is-danger", invalid);

  const field = input.closest(".field");
  const help = field?.querySelector<HTMLElement>("[data-email-help]") ?? null;
  const icon = field?.querySelector<HTMLElement>("[data-email-icon]") ?? null;
  if (help) {
    help.classList.toggle("is-hidden", !invalid);
  }
  if (icon) {
    icon.classList.toggle("is-hidden", !invalid);
  }

  return !invalid;
};

type RecipientItem = {
  id: number;
  type: string;
  name: string;
  label: string;
  email: string;
  source?: string;
};

type LinkItem = {
  id: number;
  type: string;
  name: string;
};

let selectedLinkItems: LinkItem[] = [];

type LocalLinkEditorSavedEvent = CustomEvent<{
  collectorSelector?: string;
  sourceType?: string;
  sourceId?: number;
  links?: Array<{ type: string; id: number; label: string }>;
  conversationId?: number | null;
  conversationLabel?: string;
  detachConversation?: boolean;
}>;

const renderLinkItems = (): void => {
  const container = document.querySelector<HTMLElement>("[data-email-links]");
  const list = document.querySelector<HTMLElement>("[data-email-links-list]");
  const inputs = document.querySelector<HTMLElement>(
    "[data-email-link-inputs]",
  );
  if (!container || !list || !inputs) {
    return;
  }

  const existing = Array.from(
    inputs.querySelectorAll<HTMLInputElement>("input[name='link_items[]']"),
  )
    .map((input) => {
      const [type, idRaw] = (input.value || "").split(":", 2);
      const id = Number(idRaw ?? 0);
      const label = input.dataset.linkLabel ?? "";
      if (!type || Number.isNaN(id) || id <= 0) {
        return null;
      }
      return {
        type,
        id,
        label,
      };
    })
    .filter(
      (item): item is { type: string; id: number; label: string } =>
        item !== null,
    );

  if (existing.length > 0) {
    selectedLinkItems = existing
      .filter(
        (item, index, array) =>
          array.findIndex(
            (other) => other.type === item.type && other.id === item.id,
          ) === index,
      )
      .map((item) => {
        const match = selectedLinkItems.find(
          (existingItem) =>
            existingItem.type === item.type && existingItem.id === item.id,
        );
        return {
          id: item.id,
          type: item.type,
          name: item.label || match?.name || `${item.type} #${item.id}`,
        };
      });
  }

  list.innerHTML = "";
  inputs.innerHTML = "";

  if (selectedLinkItems.length === 0) {
    const empty = document.createElement("span");
    empty.className = "has-text-grey is-size-7";
    empty.textContent = "No links yet";
    list.appendChild(empty);
    return;
  }

  const teamId = Number(list.dataset.teamId ?? 0);
  const contactUrlBase = list.dataset.contactUrlBase ?? "";
  const venueUrlBase = list.dataset.venueUrlBase ?? "";
  const emailUrlBase = list.dataset.emailUrlBase ?? "";
  const taskUrlBase = list.dataset.taskUrlBase ?? "";
  const conversationUrlBase = list.dataset.conversationUrlBase ?? "";

  const resolveLinkUrl = (type: string, id: number): string => {
    const params = new URLSearchParams();

    if (type === "contact" && contactUrlBase) {
      params.set("tab", "contacts");
      if (teamId > 0) {
        params.set("team_id", String(teamId));
      }
      params.set("contact_id", String(id));
      return `${contactUrlBase}?${params.toString()}`;
    }

    if (type === "venue" && venueUrlBase) {
      params.set("venue_id", String(id));
      return `${venueUrlBase}?${params.toString()}`;
    }

    if (type === "email" && emailUrlBase) {
      params.set("tab", "email");
      params.set("message_id", String(id));
      return `${emailUrlBase}?${params.toString()}`;
    }

    if (type === "task" && taskUrlBase) {
      params.set("tab", "tasks");
      params.set("task_id", String(id));
      return `${taskUrlBase}?${params.toString()}`;
    }

    if (type === "conversation" && conversationUrlBase) {
      params.set("tab", "conversations");
      params.set("conversation_id", String(id));
      return `${conversationUrlBase}?${params.toString()}`;
    }

    return "";
  };

  selectedLinkItems.forEach((item) => {
    const chip = document.createElement("a");
    chip.className = "detail-link-pill";

    const href = resolveLinkUrl(item.type, item.id);
    if (href !== "") {
      chip.href = href;
    } else {
      chip.href = "#";
      chip.addEventListener("click", (event) => {
        event.preventDefault();
      });
    }

    const icon = document.createElement("span");
    icon.className = "icon is-small";
    icon.innerHTML = `<i class="fa-solid ${
      item.type === "contact"
        ? "fa-user"
        : item.type === "venue"
          ? "fa-location-dot"
          : item.type === "email"
            ? "fa-envelope"
            : item.type === "task"
              ? "fa-list-check"
              : "fa-link"
    }"></i>`;

    const text = document.createElement("span");
    text.textContent = item.name;

    chip.appendChild(icon);
    chip.appendChild(text);
    list.appendChild(chip);

    const hidden = document.createElement("input");
    hidden.type = "hidden";
    hidden.name = "link_items[]";
    hidden.value = `${item.type}:${item.id}`;
    hidden.dataset.linkLabel = item.name;
    inputs.appendChild(hidden);
  });
};

const addLinkItem = (item: LinkItem): void => {
  if (!item.type || item.id <= 0 || item.name.trim() === "") {
    return;
  }
  if (
    selectedLinkItems.some(
      (existing) => existing.type === item.type && existing.id === item.id,
    )
  ) {
    return;
  }
  selectedLinkItems = [...selectedLinkItems, item];
  renderLinkItems();
};

const initLinkList = (): void => {
  renderLinkItems();
};

const initQuoteToggle = (): void => {
  const detailBlocks = document.querySelectorAll<HTMLElement>(
    "[data-email-detail]",
  );
  if (!detailBlocks.length) {
    return;
  }

  detailBlocks.forEach((detailBlock) => {
    const body = detailBlock.querySelector<HTMLElement>(
      "[data-email-detail-body]",
    );
    const toggle = detailBlock.querySelector<HTMLButtonElement>(
      "[data-email-quote-toggle]",
    );
    if (!body || !toggle) {
      return;
    }

    const updateLabel = (isCollapsed: boolean): void => {
      toggle.innerHTML = isCollapsed
        ? '<span class="icon is-small"><i class="fa-solid fa-quote-left"></i></span>'
        : '<span class="icon is-small"><i class="fa-solid fa-quote-right"></i></span>';
      toggle.dataset.emailQuoteState = isCollapsed ? "collapsed" : "expanded";
    };

    const hasQuotes = body.querySelector('blockquote[type="cite"]');
    const toggleWrapper = toggle.closest<HTMLElement>(
      ".email-detail-quote-toggle",
    );
    if (!hasQuotes) {
      toggleWrapper?.classList.add("is-hidden");
      return;
    }

    toggleWrapper?.classList.remove("is-hidden");
    body.classList.add("is-quotes-collapsed");
    updateLabel(true);

    if (toggle.dataset.quoteToggleBound === "true") {
      return;
    }
    toggle.dataset.quoteToggleBound = "true";

    toggle.addEventListener("click", () => {
      const collapsed = body.classList.toggle("is-quotes-collapsed");
      updateLabel(collapsed);
    });
  });
};

const initEmailValidation = (): void => {
  const inputs = Array.from(
    document.querySelectorAll<HTMLInputElement>("[data-email-input]"),
  );
  if (!inputs.length) {
    return;
  }

  inputs.forEach((input) => {
    if (input.dataset.emailValidationBound === "true") {
      return;
    }
    input.dataset.emailValidationBound = "true";

    const handleChange = (): void => {
      validateEmailInput(input);
    };

    input.addEventListener("input", handleChange);
    input.addEventListener("blur", handleChange);
    handleChange();
  });
};

const mailboxStorageKey = "email:selectedMailboxId";

const initEmailDragAndDrop = (): void => {
  const draggables = Array.from(
    document.querySelectorAll<HTMLElement>("[data-email-draggable]"),
  );
  const dropzones = Array.from(
    document.querySelectorAll<HTMLElement>(
      "[data-email-folder-dropzone][data-folder-key]",
    ),
  );

  if (!draggables.length || !dropzones.length) {
    return;
  }

  draggables.forEach((draggable) => {
    if (draggable.dataset.emailDragBound === "true") {
      return;
    }
    draggable.dataset.emailDragBound = "true";

    draggable.addEventListener("dragstart", (event: DragEvent) => {
      const emailId = draggable.dataset.emailId ?? "";
      const mailboxId = draggable.dataset.mailboxId ?? "";
      const currentFolder = draggable.dataset.currentFolder ?? "";
      const csrfToken = draggable.dataset.csrfToken ?? "";
      if (
        !event.dataTransfer ||
        !emailId ||
        !mailboxId ||
        !currentFolder ||
        !csrfToken
      ) {
        event.preventDefault();
        return;
      }

      event.dataTransfer.effectAllowed = "move";
      event.dataTransfer.setData(
        "application/json",
        JSON.stringify({ emailId, mailboxId, currentFolder, csrfToken }),
      );
      draggable.classList.add("is-selected");
    });

    draggable.addEventListener("dragend", () => {
      draggable.classList.remove("is-selected");
      dropzones.forEach((zone) => zone.classList.remove("is-active"));
    });
  });

  dropzones.forEach((dropzone) => {
    if (dropzone.dataset.emailDropBound === "true") {
      return;
    }
    dropzone.dataset.emailDropBound = "true";

    dropzone.addEventListener("dragover", (event: DragEvent) => {
      event.preventDefault();
      dropzone.classList.add("is-active");
      if (event.dataTransfer) {
        event.dataTransfer.dropEffect = "move";
      }
    });

    dropzone.addEventListener("dragleave", () => {
      dropzone.classList.remove("is-active");
    });

    dropzone.addEventListener("drop", async (event: DragEvent) => {
      event.preventDefault();
      dropzone.classList.remove("is-active");

      const raw = event.dataTransfer?.getData("application/json") ?? "";
      if (!raw) {
        return;
      }

      let payload: {
        emailId?: string;
        mailboxId?: string;
        currentFolder?: string;
        csrfToken?: string;
      } = {};

      try {
        payload = JSON.parse(raw) as typeof payload;
      } catch {
        return;
      }

      const emailId = Number(payload.emailId ?? 0);
      const mailboxId = Number(payload.mailboxId ?? 0);
      const currentFolder = (payload.currentFolder ?? "").trim();
      const csrfToken = (payload.csrfToken ?? "").trim();
      const targetFolder = (dropzone.dataset.folderKey ?? "").trim();

      if (
        emailId <= 0 ||
        mailboxId <= 0 ||
        currentFolder === "" ||
        csrfToken === "" ||
        targetFolder === "" ||
        currentFolder === targetFolder
      ) {
        return;
      }

      try {
        const formData = new FormData();
        formData.set("email_id", String(emailId));
        formData.set("mailbox_id", String(mailboxId));
        formData.set("target_folder", targetFolder);
        formData.set("csrf_token", csrfToken);

        const response = await fetch("app/controllers/email/move.php", {
          method: "POST",
          body: formData,
          credentials: "same-origin",
        });

        if (!response.ok) {
          return;
        }

        const data = (await response.json()) as { ok?: boolean };
        if (!data.ok) {
          return;
        }

        window.location.reload();
      } catch {
        // ignore
      }
    });
  });
};

const initMailboxSwitch = (): void => {
  const currentUrl = new URL(window.location.href);
  const currentTab = currentUrl.searchParams.get("tab") ?? "";
  if (currentTab !== "" && currentTab !== "email") {
    return;
  }

  const avatars = Array.from(
    document.querySelectorAll<HTMLElement>(
      "[data-mailbox-avatar][data-mailbox-id]",
    ),
  );

  if (!avatars.length) {
    return;
  }

  avatars.forEach((avatar) => {
    if (avatar.dataset.mailboxStorageBound === "true") {
      return;
    }
    avatar.dataset.mailboxStorageBound = "true";
    avatar.addEventListener("click", () => {
      const mailboxId = avatar.dataset.mailboxId ?? "";
      if (mailboxId !== "") {
        window.localStorage.setItem(mailboxStorageKey, mailboxId);
      }
    });
  });

  const storedMailboxId = window.localStorage.getItem(mailboxStorageKey);
  const selectedFromUrl = currentUrl.searchParams.get("mailbox_id");
  const hasMailboxParam = currentUrl.searchParams.has("mailbox_id");
  const hasExplicitTarget =
    currentUrl.searchParams.has("message_id") ||
    currentUrl.searchParams.has("compose") ||
    currentUrl.searchParams.has("reply") ||
    currentUrl.searchParams.has("forward") ||
    currentUrl.searchParams.has("conversation_id");

  if (hasMailboxParam) {
    if (selectedFromUrl && !storedMailboxId) {
      window.localStorage.setItem(mailboxStorageKey, selectedFromUrl);
      return;
    }

    if (
      selectedFromUrl &&
      storedMailboxId &&
      selectedFromUrl !== storedMailboxId
    ) {
      if (hasExplicitTarget) {
        window.localStorage.setItem(mailboxStorageKey, selectedFromUrl);
        return;
      }

      const targetAvatar = avatars.find(
        (avatar) => avatar.dataset.mailboxId === storedMailboxId,
      );
      const href = targetAvatar?.getAttribute("href") ?? "";
      if (href !== "") {
        window.location.assign(href);
      }
      return;
    }

    if (selectedFromUrl) {
      window.localStorage.setItem(mailboxStorageKey, selectedFromUrl);
    }
    return;
  }

  if (!storedMailboxId) {
    const activeAvatar = avatars.find((avatar) =>
      avatar.classList.contains("is-active"),
    );
    const activeMailboxId = activeAvatar?.dataset.mailboxId ?? "";
    if (activeMailboxId !== "") {
      window.localStorage.setItem(mailboxStorageKey, activeMailboxId);
    }
    return;
  }

  if (hasExplicitTarget) {
    return;
  }

  const targetAvatar = avatars.find(
    (avatar) => avatar.dataset.mailboxId === storedMailboxId,
  );

  if (!targetAvatar) {
    return;
  }

  const href = targetAvatar.getAttribute("href") ?? "";
  if (href === "") {
    return;
  }

  window.location.assign(href);
};

const initRecipientToggle = (): void => {
  const toggleButton = document.querySelector<HTMLButtonElement>(
    "[data-email-recipient-toggle-button]",
  );
  const extraFields = document.querySelector<HTMLElement>(
    "[data-email-recipient-extra]",
  );

  if (!toggleButton || !extraFields) {
    return;
  }

  if (toggleButton.dataset.recipientToggleBound === "true") {
    return;
  }
  toggleButton.dataset.recipientToggleBound = "true";

  const updateState = (isExpanded: boolean): void => {
    extraFields.classList.toggle("is-hidden", !isExpanded);
    toggleButton.setAttribute("aria-expanded", isExpanded ? "true" : "false");
    const icon = toggleButton.querySelector("i");
    if (icon) {
      icon.classList.toggle("fa-chevron-down", !isExpanded);
      icon.classList.toggle("fa-chevron-up", isExpanded);
    }
  };

  const hasPrefill = extraFields.querySelector<HTMLInputElement>(
    "input[value]:not([value=''])",
  );
  updateState(Boolean(hasPrefill));

  toggleButton.addEventListener("click", (event) => {
    event.preventDefault();
    updateState(extraFields.classList.contains("is-hidden"));
  });
};

const resolvePlainText = (value: string): string =>
  value
    .replace(/<[^>]*>/g, " ")
    .replace(/\u00A0/g, " ")
    .replace(/&nbsp;/gi, " ")
    .replace(/\s+/g, " ")
    .trim();

const resolveSendConfirmMessage = (
  subjectEmpty: boolean,
  bodyEmpty: boolean,
): string => {
  if (subjectEmpty && bodyEmpty) {
    return "Subject and body are empty. Send anyway?";
  }
  if (subjectEmpty) {
    return "Subject is empty. Send anyway?";
  }
  return "Body is empty. Send anyway?";
};

const resolveSubmitAction = (
  event: SubmitEvent,
  form: HTMLFormElement,
): string => {
  const submitter = event.submitter as
    | HTMLButtonElement
    | HTMLInputElement
    | null;

  if (submitter && submitter.name === "action") {
    return submitter.value;
  }

  const actionField = form.querySelector<HTMLInputElement>(
    'input[name="action"]',
  );
  return actionField?.value ?? "";
};

const confirmEmptySend = (form: HTMLFormElement): boolean => {
  const subjectField = form.querySelector<HTMLInputElement>("#email_subject");
  const bodyField = form.querySelector<HTMLTextAreaElement>("#email_body");
  const subject = subjectField?.value?.trim() ?? "";
  const bodyValue = bodyField?.value ?? "";
  const bodyText = resolvePlainText(bodyValue);

  const subjectEmpty = subject === "";
  const bodyEmpty = bodyText === "";

  if (!subjectEmpty && !bodyEmpty) {
    return true;
  }

  return window.confirm(resolveSendConfirmMessage(subjectEmpty, bodyEmpty));
};

const initSendConfirmation = (): void => {
  const form = document.querySelector<HTMLFormElement>(
    "[data-email-compose-form]",
  );
  if (!form) {
    return;
  }

  if (form.dataset.sendConfirmBound === "true") {
    return;
  }
  form.dataset.sendConfirmBound = "true";

  form.addEventListener("submit", (event: SubmitEvent) => {
    const action = resolveSubmitAction(event, form);
    if (!action || (action !== "send_email" && action !== "schedule_send")) {
      return;
    }

    if (!confirmEmptySend(form)) {
      event.preventDefault();
      event.stopPropagation();
    }
  });
};

const initSendMenu = (): void => {
  const dropdown = document.querySelector<HTMLElement>(
    "[data-email-send-menu]",
  );
  const trigger = dropdown?.querySelector<HTMLElement>(
    ".dropdown-trigger button",
  );

  if (!dropdown || !trigger) {
    return;
  }

  if (dropdown.dataset.emailSendMenuBound === "true") {
    return;
  }
  dropdown.dataset.emailSendMenuBound = "true";

  const closeMenu = (): void => {
    dropdown.classList.remove("is-active");
  };

  trigger.addEventListener("click", (event) => {
    event.preventDefault();
    event.stopPropagation();
    dropdown.classList.toggle("is-active");
  });

  dropdown.querySelectorAll<HTMLElement>(".dropdown-item").forEach((item) => {
    item.addEventListener("click", () => {
      closeMenu();
    });
  });

  document.addEventListener("click", (event) => {
    if (!dropdown.contains(event.target as Node)) {
      closeMenu();
    }
  });
};

const initScheduleModal = (): void => {
  if (document.body.dataset.scheduleModalBound === "true") {
    return;
  }
  document.body.dataset.scheduleModalBound = "true";

  const resolveModalState = () => {
    const modal = document.querySelector<HTMLElement>(
      "[data-email-schedule-modal]",
    );
    const form = document.querySelector<HTMLFormElement>(
      "[data-email-compose-form]",
    );
    if (!modal || !form) {
      return null;
    }

    const dateField = form.querySelector<HTMLInputElement>(
      '[name="schedule_date"]',
    );
    const timeField = form.querySelector<HTMLInputElement>(
      '[name="schedule_time"]',
    );
    const datePicker = modal.querySelector<HTMLInputElement>(
      "[data-email-schedule-date]",
    );
    const timePicker = modal.querySelector<HTMLInputElement>(
      "[data-email-schedule-time]",
    );

    if (!dateField || !timeField || !datePicker || !timePicker) {
      return null;
    }

    return {
      modal,
      form,
      dateField,
      timeField,
      datePicker,
      timePicker,
    };
  };

  const openModal = (): void => {
    const state = resolveModalState();
    if (!state) {
      return;
    }
    state.datePicker.value = state.dateField.value;
    state.timePicker.value = state.timeField.value;
    state.datePicker.classList.remove("is-danger");
    state.timePicker.classList.remove("is-danger");
    state.modal.classList.add("is-active");
  };

  const closeModal = (): void => {
    const state = resolveModalState();
    if (!state) {
      return;
    }
    state.modal.classList.remove("is-active");
  };

  document.addEventListener("click", (event) => {
    const target = (event.target as HTMLElement | null)?.closest(
      "[data-email-schedule-trigger]",
    );
    if (!target) {
      return;
    }
    event.preventDefault();
    openModal();
  });

  document.addEventListener("click", (event) => {
    const target = (event.target as HTMLElement | null)?.closest(
      "[data-email-schedule-close]",
    );
    if (!target) {
      return;
    }
    event.preventDefault();
    closeModal();
  });

  document.addEventListener("click", (event) => {
    const target = (event.target as HTMLElement | null)?.closest(
      "[data-email-schedule-submit]",
    );
    if (!target) {
      return;
    }
    event.preventDefault();

    const state = resolveModalState();
    if (!state) {
      return;
    }

    state.dateField.value = state.datePicker.value;
    state.timeField.value = state.timePicker.value;

    if (!state.dateField.value || !state.timeField.value) {
      state.datePicker.classList.toggle("is-danger", !state.datePicker.value);
      state.timePicker.classList.toggle("is-danger", !state.timePicker.value);
      return;
    }

    const actionField = document.createElement("input");
    actionField.type = "hidden";
    actionField.name = "action";
    actionField.value = "schedule_send";
    state.form.appendChild(actionField);

    if (!confirmEmptySend(state.form)) {
      actionField.remove();
      return;
    }

    if (typeof state.form.requestSubmit === "function") {
      state.form.requestSubmit();
    } else {
      state.form.submit();
    }
    actionField.remove();
  });
};

const initComposeEnterGuard = (): void => {
  const forms = Array.from(
    document.querySelectorAll<HTMLFormElement>("[data-email-compose-form]"),
  );

  forms.forEach((form) => {
    if (form.dataset.enterGuardBound === "true") {
      return;
    }
    form.dataset.enterGuardBound = "true";

    form.addEventListener("keydown", (event: KeyboardEvent) => {
      if (event.key !== "Enter") {
        return;
      }

      const target = event.target as HTMLElement | null;
      if (!target) {
        return;
      }

      if (
        target instanceof HTMLTextAreaElement ||
        target.isContentEditable ||
        target.closest(".tox-tinymce")
      ) {
        return;
      }

      if (target instanceof HTMLButtonElement) {
        return;
      }

      if (
        target instanceof HTMLInputElement &&
        ["submit", "button", "file", "checkbox", "radio"].includes(target.type)
      ) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
    });
  });
};

const initRecipientLookup = (): void => {
  const lookups = Array.from(
    document.querySelectorAll<HTMLElement>("[data-email-lookup]"),
  );

  lookups.forEach((lookup) => {
    if (lookup.dataset.lookupBound === "true") {
      return;
    }
    lookup.dataset.lookupBound = "true";

    const input = lookup.querySelector<HTMLInputElement>("[data-email-input]");
    const menu = lookup.querySelector<HTMLDivElement>(".dropdown-menu");
    const content = lookup.querySelector<HTMLDivElement>(".dropdown-content");
    const lookupUrl = lookup.dataset.lookupUrl ?? "";

    if (!input || !menu || !content || lookupUrl === "") {
      return;
    }

    let activeRequest = 0;
    let debounceId: number | null = null;
    let currentItems: RecipientItem[] = [];
    let selectedIndex = -1;

    const clearResults = (): void => {
      content.innerHTML = "";
      menu.classList.add("is-hidden");
      lookup.classList.remove("is-active");
      currentItems = [];
      selectedIndex = -1;
    };

    const selectItem = (index: number): void => {
      const items = Array.from(
        content.querySelectorAll<HTMLElement>(".dropdown-item"),
      );
      items.forEach((item) => item.classList.remove("is-active"));

      if (index >= 0 && index < items.length) {
        selectedIndex = index;
        items[index].classList.add("is-active");
        items[index].scrollIntoView({ block: "nearest" });
        return;
      }

      selectedIndex = -1;
    };

    const showResults = (items: RecipientItem[]): void => {
      currentItems = items;
      selectedIndex = -1;

      if (!items.length) {
        content.innerHTML = '<div class="dropdown-item">No results found</div>';
      } else {
        content.innerHTML = items
          .map((item, index) => {
            const email = item.email
              ? ` <span class="has-text-grey">${item.email}</span>`
              : "";
            const source = item.source
              ? ` <span class="tag email-recipient-badge ml-2">${item.source}</span>`
              : "";
            return `<a class="dropdown-item" data-index="${index}" data-id="${item.id}" data-type="${item.type}" data-name="${item.name}" data-label="${item.label}" data-email="${item.email ?? ""}">${item.label}${email}${source}</a>`;
          })
          .join("");
      }
      menu.classList.remove("is-hidden");
      lookup.classList.add("is-active");
    };

    const performSearch = async (query: string): Promise<void> => {
      const requestId = ++activeRequest;
      if (query.length < 2) {
        clearResults();
        return;
      }

      try {
        const response = await fetch(
          `${lookupUrl}?q=${encodeURIComponent(query)}`,
        );
        if (!response.ok) {
          clearResults();
          return;
        }
        const data = (await response.json()) as { items: RecipientItem[] };
        if (requestId !== activeRequest) {
          return;
        }
        showResults(data.items);
      } catch {
        clearResults();
      }
    };

    const appendSelection = (item: RecipientItem): void => {
      const email = item.email || "";
      if (email === "") {
        return;
      }
      const updated = replaceLastEmailToken(input.value, email);
      input.value = normalizeEmailList(updated);
      clearResults();
      validateEmailInput(input);
    };

    input.addEventListener("input", () => {
      validateEmailInput(input);

      if (debounceId) {
        window.clearTimeout(debounceId);
      }
      const term = getLastEmailToken(input.value);
      debounceId = window.setTimeout(() => {
        void performSearch(term);
      }, 250);
    });

    input.addEventListener("keydown", (event) => {
      if (event.key !== "Enter" && event.key !== "Tab") {
        return;
      }

      if (currentItems.length > 0) {
        return;
      }

      const normalized = normalizeEmailList(input.value);
      if (normalized !== input.value) {
        input.value = normalized;
      }
      validateEmailInput(input);
    });

    input.addEventListener("blur", () => {
      const normalized = normalizeEmailList(input.value);
      if (normalized !== input.value) {
        input.value = normalized;
      }
      validateEmailInput(input);

      window.setTimeout(() => {
        clearResults();
      }, 150);
    });

    const handleResultSelection = (event: Event): void => {
      const target = (event.target as HTMLElement).closest(
        ".dropdown-item",
      ) as HTMLElement | null;
      if (!target || !target.dataset.index) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();

      const index = Number(target.dataset.index);
      const item = Number.isNaN(index) ? undefined : currentItems[index];
      if (!item) {
        return;
      }

      appendSelection(item);
      if (item.id && item.type && item.name) {
        addLinkItem({
          id: item.id,
          type: item.type,
          name: item.name,
        });
      }
    };

    content.addEventListener("mousedown", handleResultSelection);
    content.addEventListener("click", handleResultSelection);

    input.addEventListener("keydown", (event) => {
      if (!currentItems.length) {
        return;
      }

      switch (event.key) {
        case "ArrowDown":
          event.preventDefault();
          selectItem(
            selectedIndex < currentItems.length - 1 ? selectedIndex + 1 : 0,
          );
          break;
        case "ArrowUp":
          event.preventDefault();
          selectItem(
            selectedIndex > 0 ? selectedIndex - 1 : currentItems.length - 1,
          );
          break;
        case "Enter": {
          event.preventDefault();
          const index = selectedIndex >= 0 ? selectedIndex : 0;
          const item = currentItems[index];
          if (!item) {
            return;
          }
          appendSelection(item);
          if (item.id && item.type && item.name) {
            addLinkItem({
              id: item.id,
              type: item.type,
              name: item.name,
            });
          }
          break;
        }
        case "Escape":
          clearResults();
          break;
      }
    });

    document.addEventListener("click", (event) => {
      const target = event.target as Node;
      if (!lookup.contains(target)) {
        clearResults();
      }
    });
  });
};

const initComposeLinkRefresh = (): void => {
  if (document.body.dataset.composeLinkRefreshBound === "true") {
    return;
  }
  document.body.dataset.composeLinkRefreshBound = "true";

  document.addEventListener("link-editor:local-saved", (event) => {
    const detail = (event as LocalLinkEditorSavedEvent).detail;
    const links = detail?.links ?? [];

    if (links.length > 0) {
      selectedLinkItems = links
        .filter((link) => link.type && Number(link.id) > 0)
        .map((link) => ({
          id: Number(link.id),
          type: link.type,
          name: link.label || `${link.type} #${link.id}`,
        }));
    }

    const composeConversationInput = document.querySelector<HTMLInputElement>(
      "[data-email-conversation-id]",
    );
    const composeConversationPill = document.querySelector<HTMLElement>(
      "[data-email-conversation-pill]",
    );

    if (composeConversationInput) {
      const shouldDetach = Boolean(detail?.detachConversation);
      if (shouldDetach) {
        composeConversationInput.value = "";
      } else if (
        typeof detail?.conversationId === "number" &&
        detail.conversationId > 0
      ) {
        composeConversationInput.value = String(detail.conversationId);
      }
    }

    if (composeConversationPill) {
      const shouldDetach = Boolean(detail?.detachConversation);
      if (shouldDetach) {
        composeConversationPill.innerHTML = "";
      } else if (
        typeof detail?.conversationId === "number" &&
        detail.conversationId > 0
      ) {
        const label =
          detail?.conversationLabel?.trim() ||
          `Conversation #${detail.conversationId}`;
        const safe = document.createElement("span");
        safe.textContent = label;
        composeConversationPill.innerHTML = `<span class="detail-link-pill"><span class="icon is-small"><i class="fa-solid fa-comments"></i></span><span>${safe.innerHTML}</span></span>`;
      }
    }

    renderLinkItems();
  });
};

const bindHugerteEditor = (): void => {
  initHugerteEditor();
  initHugertePasteSanitizer();
  initQuoteToggle();
  initEmailValidation();
  initRecipientLookup();
  initComposeEnterGuard();
  initComposeLinkRefresh();
  initLinkList();
  initMailboxSwitch();
  initEmailDragAndDrop();
  initRecipientToggle();
  initSendMenu();
  initSendConfirmation();
  initScheduleModal();
  document.addEventListener("tab:activated", () => {
    initHugerteEditor();
    initHugertePasteSanitizer();
    initQuoteToggle();
    initEmailValidation();
    initRecipientLookup();
    initComposeEnterGuard();
    initComposeLinkRefresh();
    initLinkList();
    initMailboxSwitch();
    initRecipientToggle();
    initSendMenu();
    initSendConfirmation();
    initScheduleModal();
  });
};

document.addEventListener("DOMContentLoaded", () => {
  bindHugerteEditor();
});

document.addEventListener("htmx:afterSwap", (event) => {
  const customEvent = event as CustomEvent<{
    target?: HTMLElement;
    elt?: HTMLElement;
  }>;

  const target =
    customEvent.detail?.target ??
    customEvent.detail?.elt ??
    (event.target as HTMLElement | null) ??
    null;

  if (!target) {
    return;
  }

  const hasEmailDetail =
    target.matches("[data-email-detail]") ||
    Boolean(target.querySelector("[data-email-detail]"));

  const hasComposeFormInDom = Boolean(
    document.querySelector("[data-email-compose-form]"),
  );

  if (hasEmailDetail) {
    initQuoteToggle();
  }

  if (hasComposeFormInDom) {
    initHugerteEditor();
    initHugertePasteSanitizer();
    initEmailValidation();
    initRecipientLookup();
    initComposeEnterGuard();
    initComposeLinkRefresh();
    initLinkList();
    initMailboxSwitch();
    initEmailDragAndDrop();
    initRecipientToggle();
    initSendMenu();
    initSendConfirmation();
    initScheduleModal();
  }
});

const initMailboxPasswordToggle = (): void => {
  const checkbox = document.querySelector<HTMLInputElement>('[data-mailbox-same-credentials]');
  const imapPassword = document.querySelector<HTMLInputElement>('[data-imap-password]');
  const smtpPassword = document.querySelector<HTMLInputElement>('[data-smtp-password]');
  const imapUsername = document.querySelector<HTMLInputElement>('[data-imap-username]');
  const smtpUsername = document.querySelector<HTMLInputElement>('[data-smtp-username]');
  const smtpCredentialFields = Array.from(
    document.querySelectorAll<HTMLElement>('[data-smtp-credentials]')
  );

  if (!checkbox || !imapPassword || !smtpPassword || !imapUsername || !smtpUsername) {
    return;
  }

  const syncCredentials = () => {
    if (checkbox.checked) {
      smtpPassword.value = imapPassword.value;
      smtpUsername.value = imapUsername.value;
    }
  };

  const toggleState = () => {
    const useSame = checkbox.checked;
    smtpPassword.readOnly = useSame;
    smtpUsername.readOnly = useSame;
    smtpPassword.classList.toggle('is-readonly', useSame);
    smtpUsername.classList.toggle('is-readonly', useSame);
    smtpCredentialFields.forEach((field) => {
      field.classList.toggle('is-hidden', useSame);
    });
    if (useSame) {
      syncCredentials();
    }
  };

  imapPassword.addEventListener('input', syncCredentials);
  imapUsername.addEventListener('input', syncCredentials);
  checkbox.addEventListener('change', toggleState);
  toggleState();
};

initMailboxPasswordToggle();

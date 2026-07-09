(() => {
    const DEFAULTS = {
        title: 'Confirmar accion',
        text: 'Deseas continuar?',
        type: 'question',
        confirmButton: 'Si, continuar',
        cancelButton: 'Cancelar',
    };

    const iconFor = (type) => {
        switch (String(type || '').toLowerCase()) {
            case 'danger':
            case 'error':
            case 'delete':
                return 'warning';
            case 'success':
                return 'success';
            case 'warning':
                return 'warning';
            case 'info':
                return 'info';
            default:
                return 'question';
        }
    };

    const confirmButtonColorFor = (type) => {
        switch (String(type || '').toLowerCase()) {
            case 'danger':
            case 'error':
            case 'delete':
                return '#dc2626';
            case 'success':
                return '#047857';
            case 'warning':
                return '#d97706';
            default:
                return '#2563eb';
        }
    };

    const datasetOptions = (element) => {
        const data = element?.dataset || {};
        return {
            title: data.confirmTitle || DEFAULTS.title,
            text: data.confirmText || data.confirm || DEFAULTS.text,
            html: data.confirmHtml || '',
            type: data.confirmType || DEFAULTS.type,
            confirmButton: data.confirmButton || DEFAULTS.confirmButton,
            cancelButton: data.confirmCancel || DEFAULTS.cancelButton,
            phrase: data.confirmPhrase || '',
            phraseLabel: data.confirmPhraseLabel || '',
            phrasePlaceholder: data.confirmPhrasePlaceholder || '',
        };
    };

    const fallbackConfirm = (options) => {
        if (!options.phrase) {
            return Promise.resolve(window.confirm(`${options.title}\n\n${options.text}`));
        }

        const label = options.phraseLabel
            || `Escribe ${options.phrase} para confirmar.`;
        const value = window.prompt(`${options.title}\n\n${options.text}\n\n${label}`, '');
        return Promise.resolve(String(value || '').trim() === options.phrase);
    };

    const ask = async (rawOptions = {}) => {
        const options = { ...DEFAULTS, ...rawOptions };

        if (typeof window.Swal === 'undefined') {
            return fallbackConfirm(options);
        }

        const swalOptions = {
            icon: iconFor(options.type),
            title: options.title,
            showCancelButton: true,
            confirmButtonText: options.confirmButton,
            cancelButtonText: options.cancelButton,
            confirmButtonColor: confirmButtonColorFor(options.type),
            reverseButtons: true,
            focusCancel: true,
            allowOutsideClick: () => !window.Swal.isLoading(),
        };

        if (options.html) {
            swalOptions.html = options.html;
        } else {
            swalOptions.text = options.text;
        }

        if (options.phrase) {
            swalOptions.input = 'text';
            swalOptions.inputLabel = options.phraseLabel
                || `Escribe ${options.phrase} para confirmar`;
            swalOptions.inputPlaceholder = options.phrasePlaceholder || options.phrase;
            swalOptions.preConfirm = (value) => {
                if (String(value || '').trim() !== options.phrase) {
                    window.Swal.showValidationMessage('El texto de confirmacion no coincide.');
                    return false;
                }
                return true;
            };
        }

        const result = await window.Swal.fire(swalOptions);
        return Boolean(result.isConfirmed);
    };

    const submitForm = (form, submitter) => {
        form.dataset.dsConfirmSubmitting = '1';

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(submitter || undefined);
            return;
        }

        if (submitter && submitter.name) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = submitter.name;
            hidden.value = submitter.value || '';
            form.appendChild(hidden);
        }

        form.submit();
    };

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (form.dataset.dsConfirmSubmitting === '1') {
            delete form.dataset.dsConfirmSubmitting;
            return;
        }

        const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
        const source = submitter?.matches('[data-confirm]') ? submitter : form;
        if (!source.matches('[data-confirm]')) {
            return;
        }

        event.preventDefault();

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        if (await ask(datasetOptions(source))) {
            submitForm(form, submitter);
        }
    }, true);

    document.addEventListener('click', async (event) => {
        const link = event.target instanceof Element
            ? event.target.closest('a[data-confirm]')
            : null;
        if (!(link instanceof HTMLAnchorElement) || link.dataset.dsConfirmNavigating === '1') {
            return;
        }

        const href = link.getAttribute('href');
        if (!href || href === '#') {
            return;
        }

        event.preventDefault();

        if (await ask(datasetOptions(link))) {
            link.dataset.dsConfirmNavigating = '1';
            if (link.target && link.target !== '_self') {
                window.open(link.href, link.target, 'noopener');
                delete link.dataset.dsConfirmNavigating;
                return;
            }
            window.location.href = link.href;
        }
    }, true);

    window.DigiSecureConfirm = {
        ask,
        confirm: ask,
        fromDataset: datasetOptions,
    };
})();

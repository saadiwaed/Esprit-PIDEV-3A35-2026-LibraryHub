(function () {
    const requestAi = async (endpoint, payload) => {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload || {}),
        });

        const data = await response.json().catch(() => null);
        if (!response.ok || !data || data.ok !== true) {
            const message = data && typeof data.error === 'string'
                ? data.error
                : 'Erreur lors de l appel IA.';
            throw new Error(message);
        }

        return data;
    };

    const setStatus = (target, kind, message) => {
        if (!target) {
            return;
        }

        target.className = 'small mt-2';
        if (kind === 'success') {
            target.classList.add('text-success');
        } else if (kind === 'error') {
            target.classList.add('text-danger');
        } else {
            target.classList.add('text-muted');
        }

        target.textContent = message || '';
    };

    const getSuccessStatusMessage = (response, defaultMessage) => {
        const meta = response && response.meta ? response.meta : null;
        if (meta && meta.fallbackUsed === true) {
            return '';
        }

        if (meta && typeof meta.message === 'string' && meta.message.trim() !== '') {
            return meta.message;
        }

        return defaultMessage;
    };

    const getEditorInstance = (field) => {
        if (!field || !field.id) {
            return null;
        }

        const globalKey = `editor_${field.id}`;
        if (typeof window !== 'undefined' && window[globalKey]) {
            return window[globalKey];
        }

        // Toast UI bundle declares editor instances as global `const editor_<id>`,
        // which are not attached to window. Resolve it dynamically when possible.
        try {
            const fromEval = eval(globalKey);
            if (fromEval) {
                return fromEval;
            }
        } catch (error) {
            // Ignore lookup errors and continue with DOM-based heuristics.
        }

        const scope = field.closest('.mb-3, .col-12, .card-body, form') || document;
        const editorRoot = scope.querySelector('.toastui-editor-defaultUI');
        if (!editorRoot) {
            return null;
        }

        return editorRoot.toastUIEditor || editorRoot.__toastuiEditor || editorRoot._toastuiEditor || null;
    };

    const renderFallbackVisualContent = (field, value) => {
        if (!field) {
            return;
        }

        const scope = field.closest('.mb-3, .col-12, .card-body, form') || document;
        const wwEditable = scope.querySelector('.toastui-editor-ww-container .ProseMirror.toastui-editor-contents');
        if (!wwEditable) {
            return;
        }

        const chunks = String(value || '')
            .replace(/\r/g, '')
            .split('\n\n')
            .map((part) => part.trim())
            .filter((part) => part.length > 0);

        if (chunks.length === 0) {
            wwEditable.innerHTML = '<p><br></p>';
            return;
        }

        const toHtml = (input) => String(input)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/\n/g, '<br>');

        wwEditable.innerHTML = chunks.map((part) => `<p>${toHtml(part)}</p>`).join('');
        wwEditable.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const getFieldValue = (field) => {
        if (!field) {
            return '';
        }

        const instance = getEditorInstance(field);
        if (instance && typeof instance.getMarkdown === 'function') {
            const markdown = instance.getMarkdown();
            if (typeof markdown === 'string' && markdown.trim() !== '') {
                return markdown;
            }
        }

        return field.value || field.innerHTML || '';
    };

    const trySetEditorContent = (field, value) => {
        const instance = getEditorInstance(field);
        if (!instance) {
            return false;
        }

        if (typeof instance.setMarkdown === 'function') {
            instance.setMarkdown(value || '');
            return true;
        }

        if (typeof instance.setHTML === 'function') {
            instance.setHTML(value || '');
            return true;
        }

        return false;
    };

    const setFieldValue = (field, value) => {
        if (!field) {
            return;
        }

        const normalized = typeof value === 'string' ? value : '';
        const editorUpdated = trySetEditorContent(field, normalized);
        field.value = normalized;
        field.innerHTML = normalized;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));

        if (!editorUpdated) {
            renderFallbackVisualContent(field, normalized);
        }
    };

    const readById = (id) => {
        if (!id) {
            return null;
        }
        return document.getElementById(id);
    };

    const normalizeCommentsContext = (limit) => {
        const comments = [];
        const cards = document.querySelectorAll('.comment-card .comment-content');
        for (let i = 0; i < cards.length && i < limit; i += 1) {
            const raw = (cards[i].innerText || '').trim();
            if (raw) {
                comments.push(raw);
            }
        }
        return comments;
    };

    const escapeHtml = (value) => {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const renderList = (title, items) => {
        if (!Array.isArray(items) || items.length === 0) {
            return '';
        }

        const li = items.map((item) => `<li>${escapeHtml(item)}</li>`).join('');
        return `<div class="mb-2"><div class="fw-semibold mb-1">${escapeHtml(title)}</div><ul class="mb-0 ps-3">${li}</ul></div>`;
    };

    const initCommunityAssistant = () => {
        document.querySelectorAll('[data-forum-ai-community]').forEach((root) => {
            const endpoint = root.getAttribute('data-endpoint');
            const actionButton = root.querySelector('[data-ai-action="community-generate"]');
            const status = root.querySelector('[data-ai-status]');

            if (!endpoint || !actionButton) {
                return;
            }

            const nameField = readById(root.getAttribute('data-name-id'));
            const purposeField = readById(root.getAttribute('data-purpose-id'));
            const descriptionField = readById(root.getAttribute('data-description-id'));
            const rulesField = readById(root.getAttribute('data-rules-id'));
            const welcomeField = readById(root.getAttribute('data-welcome-id'));

            actionButton.addEventListener('click', async () => {
                actionButton.disabled = true;
                setStatus(status, 'info', 'Generation IA en cours...');

                try {
                    const response = await requestAi(endpoint, {
                        name: nameField ? nameField.value : '',
                        purpose: purposeField ? purposeField.value : '',
                        description: descriptionField ? descriptionField.value : '',
                        rules: rulesField ? rulesField.value : '',
                        welcomeMessage: welcomeField ? welcomeField.value : '',
                    });

                    if (response.data) {
                        setFieldValue(purposeField, response.data.purpose);
                        setFieldValue(descriptionField, response.data.description);
                        setFieldValue(rulesField, response.data.rules);
                        setFieldValue(welcomeField, response.data.welcomeMessage);
                    }

                    const message = getSuccessStatusMessage(response, 'Suggestion IA appliquee.');
                    setStatus(status, 'success', message);
                } catch (error) {
                    setStatus(status, 'error', error.message || 'Impossible de generer la suggestion.');
                } finally {
                    actionButton.disabled = false;
                }
            });
        });
    };

    const initPostAssistant = () => {
        document.querySelectorAll('[data-forum-ai-post]').forEach((root) => {
            const endpoint = root.getAttribute('data-endpoint');
            const actionButton = root.querySelector('[data-ai-action="post-generate"]');
            const status = root.querySelector('[data-ai-status]');

            if (!endpoint || !actionButton) {
                return;
            }

            const titleField = readById(root.getAttribute('data-title-id'));
            const contentField = readById(root.getAttribute('data-content-id'));
            const communityField = readById(root.getAttribute('data-community-id'));

            actionButton.addEventListener('click', async () => {
                actionButton.disabled = true;
                setStatus(status, 'info', 'Generation IA en cours...');

                try {
                    const communityName = communityField && communityField.tagName === 'SELECT'
                        ? (communityField.options[communityField.selectedIndex] || {}).text || ''
                        : (communityField ? communityField.value : '');

                    const response = await requestAi(endpoint, {
                        title: titleField ? titleField.value : '',
                        content: getFieldValue(contentField),
                        communityName: communityName,
                    });

                    if (response.data) {
                        setFieldValue(titleField, response.data.title);
                        setFieldValue(contentField, response.data.content);
                    }

                    const message = getSuccessStatusMessage(response, 'Suggestion IA appliquee.');
                    setStatus(status, 'success', message);
                } catch (error) {
                    setStatus(status, 'error', error.message || 'Impossible de generer la suggestion.');
                } finally {
                    actionButton.disabled = false;
                }
            });
        });
    };

    const initCommentAssistant = () => {
        document.querySelectorAll('[data-forum-ai-comment]').forEach((root) => {
            const endpoint = root.getAttribute('data-endpoint');
            const contentField = readById(root.getAttribute('data-content-id'));
            const actionButton = root.querySelector('[data-ai-action="comment-generate"]');
            const status = root.querySelector('[data-ai-status]');

            if (!endpoint || !actionButton || !contentField) {
                return;
            }

            actionButton.addEventListener('click', async () => {
                actionButton.disabled = true;
                setStatus(status, 'info', 'Generation IA en cours...');

                try {
                    const response = await requestAi(endpoint, {
                        draft: getFieldValue(contentField),
                        commentsContext: normalizeCommentsContext(10),
                    });

                    if (response.data && typeof response.data.suggestion === 'string') {
                        setFieldValue(contentField, response.data.suggestion);
                    }

                    const message = getSuccessStatusMessage(response, 'Suggestion de commentaire prete.');
                    setStatus(status, 'success', message);
                } catch (error) {
                    setStatus(status, 'error', error.message || 'Impossible de generer la suggestion.');
                } finally {
                    actionButton.disabled = false;
                }
            });
        });
    };

    const initSummaryAssistant = () => {
        document.querySelectorAll('[data-forum-ai-summary]').forEach((root) => {
            const endpoint = root.getAttribute('data-endpoint');
            const actionButton = root.querySelector('[data-ai-action="summary-generate"]');
            const output = root.querySelector('[data-ai-summary-output]');
            const status = root.querySelector('[data-ai-status]');

            if (!endpoint || !actionButton || !output) {
                return;
            }

            actionButton.addEventListener('click', async () => {
                actionButton.disabled = true;
                setStatus(status, 'info', 'Generation de la synthese...');

                try {
                    const response = await requestAi(endpoint, {});
                    const data = response.data || {};

                    output.innerHTML = [
                        data.summary
                            ? `<p class="mb-2">${escapeHtml(data.summary)}</p>`
                            : '<p class="mb-2 text-muted">Synthese indisponible.</p>',
                        renderList('Points cles', data.keyPoints),
                        renderList('Points de desaccord', data.disagreements),
                        renderList('Questions ouvertes', data.openQuestions),
                    ].join('');

                    const message = getSuccessStatusMessage(response, 'Synthese IA generee.');
                    setStatus(status, 'success', message);
                } catch (error) {
                    setStatus(status, 'error', error.message || 'Impossible de generer la synthese.');
                } finally {
                    actionButton.disabled = false;
                }
            });
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        initCommunityAssistant();
        initPostAssistant();
        initCommentAssistant();
        initSummaryAssistant();
    });
})();

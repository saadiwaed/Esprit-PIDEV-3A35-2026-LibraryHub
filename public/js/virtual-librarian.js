document.addEventListener('DOMContentLoaded', () => {
    const launcher = document.getElementById('vh-chat-launcher');
    const panel = document.getElementById('vh-chat-panel');
    const form = document.getElementById('vh-chat-form');
    const input = document.getElementById('vh-chat-input');
    const messages = document.getElementById('vh-chat-messages');

    if (!launcher || !panel || !form || !input || !messages) {
        return;
    }

    const endpoint = panel.dataset.endpoint || '/assistant/ask';

    // S'assurer qu'au chargement le panneau est caché
    if (!panel.style.display) {
        panel.style.display = 'none';
    }

    launcher.addEventListener('click', () => {
        if (panel.style.display === 'none' || panel.style.display === '') {
            panel.style.display = 'block';
        } else {
            panel.style.display = 'none';
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const question = (input.value || '').trim();
        if (!question) {
            return;
        }

        addMessage('user', question);
        input.value = '';

        addMessage('bot', 'Je réfléchis à votre question...');

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ question }),
            });

            const data = await response.json();
            const lastBotBubble = messages.querySelector('.vh-msg-bot:last-child');
            if (lastBotBubble) {
                lastBotBubble.remove();
            }

            if (!response.ok || !data.ok) {
                addMessage('bot', data.answer || "Je n'ai pas pu traiter cette demande pour le moment.");
                return;
            }

            addMessage('bot', data.answer);
        } catch (e) {
            const lastBotBubble = messages.querySelector('.vh-msg-bot:last-child');
            if (lastBotBubble) {
                lastBotBubble.remove();
            }
            addMessage('bot', "Une erreur technique est survenue. Réessayez dans quelques instants.");
        }
    });

    function addMessage(author, text) {
        const wrapper = document.createElement('div');
        wrapper.className = author === 'user' ? 'vh-msg vh-msg-user' : 'vh-msg vh-msg-bot';
        wrapper.textContent = text;
        messages.appendChild(wrapper);
        messages.scrollTop = messages.scrollHeight;
    }
});


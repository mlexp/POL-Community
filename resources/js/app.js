function initializeRichTextEditors() {
    document.querySelectorAll('[data-rich-text]').forEach((container) => {
        if (container.dataset.initialized) return;
        container.dataset.initialized = 'true';
        const editor = container.querySelector('[contenteditable]');
        const input = container.querySelector('input[type="hidden"]');
        const sync = () => { input.value = editor.innerHTML; };
        editor.addEventListener('input', sync);
        container.querySelectorAll('[data-command]').forEach((button) => {
            button.addEventListener('click', () => {
                const command = button.dataset.command;
                const value = command === 'createLink' ? window.prompt('リンク先（https://）') : (command === 'formatBlock' ? 'H2' : null);
                if (command === 'createLink' && (!value || !value.startsWith('https://'))) return;
                document.execCommand(command, false, value);
                editor.focus();
                sync();
            });
        });
        container.closest('form')?.addEventListener('submit', sync);
    });
}

document.addEventListener('DOMContentLoaded', initializeRichTextEditors);
document.addEventListener('livewire:navigated', initializeRichTextEditors);

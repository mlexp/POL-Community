function initializeRichTextEditors() {
    document.querySelectorAll('[data-rich-text]').forEach((container) => {
        if (container.dataset.initialized) return;
        container.dataset.initialized = 'true';
        const editor = container.querySelector('[contenteditable]');
        const input = container.querySelector('input[type="hidden"]');
        let savedRange = null;
        let paletteInteraction = false;
        const rememberSelection = () => {
            const selection = window.getSelection();
            if (!selection?.rangeCount) return;
            const range = selection.getRangeAt(0);
            if (editor.contains(range.commonAncestorContainer)) savedRange = range.cloneRange();
        };
        const restoreSelection = () => {
            if (!savedRange) return;
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(savedRange);
        };
        const createSelectionBookmark = () => {
            const selection = window.getSelection();
            if (!selection?.rangeCount || selection.isCollapsed) return null;
            const range = selection.getRangeAt(0);
            if (!editor.contains(range.commonAncestorContainer)) return null;
            const start = document.createElement('span');
            const end = document.createElement('span');
            start.dataset.selectionMarker = 'start';
            end.dataset.selectionMarker = 'end';
            const endRange = range.cloneRange();
            endRange.collapse(false);
            endRange.insertNode(end);
            const startRange = range.cloneRange();
            startRange.collapse(true);
            startRange.insertNode(start);

            return { start, end };
        };
        const restoreSelectionBookmark = (bookmark) => {
            if (!bookmark?.start.isConnected || !bookmark.end.isConnected) return false;
            const range = document.createRange();
            range.setStartAfter(bookmark.start);
            range.setEndBefore(bookmark.end);
            bookmark.start.remove();
            bookmark.end.remove();
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            savedRange = range.cloneRange();

            return true;
        };
        const sync = () => { input.value = editor.innerHTML; };
        editor.addEventListener('input', () => { sync(); rememberSelection(); });
        editor.addEventListener('keyup', rememberSelection);
        editor.addEventListener('mouseup', rememberSelection);
        container.querySelectorAll('[data-rich-text-palette]').forEach((palette) => {
            const summary = palette.querySelector('summary');
            summary.addEventListener('mousedown', (event) => {
                rememberSelection();
                event.preventDefault();
            });
            summary.addEventListener('click', () => {
                container.querySelectorAll('[data-rich-text-palette][open]').forEach((other) => {
                    if (other !== palette) other.removeAttribute('open');
                });
            });
            palette.addEventListener('focusout', () => {
                window.setTimeout(() => {
                    if (!paletteInteraction && !palette.contains(document.activeElement)) palette.removeAttribute('open');
                });
            });
        });
        container.querySelectorAll('[data-command]').forEach((button) => {
            button.addEventListener('mousedown', (event) => {
                event.preventDefault();
                if (button.closest('[data-rich-text-palette]')) {
                    paletteInteraction = true;
                    window.setTimeout(() => { paletteInteraction = false; }, 1000);
                }
                editor.focus({ preventScroll: true });
                restoreSelection();
            });
            button.addEventListener('click', () => {
                editor.focus({ preventScroll: true });
                restoreSelection();
                const command = button.dataset.command;
                let linkBookmark = null;
                let value = command === 'formatBlock' ? 'H2' : (button.dataset.value || null);
                if (command === 'createLink') {
                    linkBookmark = createSelectionBookmark();
                    value = window.prompt('リンク先（https://）');
                    if (!value || !value.startsWith('https://')) {
                        restoreSelectionBookmark(linkBookmark);
                        return;
                    }
                    editor.focus({ preventScroll: true });
                    if (!restoreSelectionBookmark(linkBookmark)) restoreSelection();
                }
                document.execCommand(command, false, value);
                if (command === 'removeFormat') {
                    const selection = window.getSelection();
                    const range = selection?.rangeCount ? selection.getRangeAt(0) : null;
                    if (range) {
                        editor.querySelectorAll('[data-text-color], [data-background-color]').forEach((element) => {
                            if (!range.intersectsNode(element)) return;
                            element.removeAttribute('data-text-color');
                            element.removeAttribute('data-background-color');
                            if (!element.attributes.length) element.replaceWith(...element.childNodes);
                        });
                    }
                }
                if (command === 'fontSize') {
                    const sizeBookmark = createSelectionBookmark();
                    const semanticTag = value === '5' ? 'big' : 'small';
                    editor.querySelectorAll(`font[size="${value}"]`).forEach((font) => {
                        const replacement = document.createElement(semanticTag);
                        replacement.replaceChildren(...font.childNodes);
                        font.replaceWith(replacement);
                    });
                    if (!restoreSelectionBookmark(sizeBookmark)) {
                        restoreSelection();
                        rememberSelection();
                    }
                }
                if (command === 'foreColor') {
                    const color = value.slice(1).toUpperCase();
                    editor.querySelectorAll('font[color]').forEach((font) => {
                        const replacement = document.createElement('span');
                        replacement.dataset.textColor = color;
                        replacement.replaceChildren(...font.childNodes);
                        font.replaceWith(replacement);
                    });
                    editor.querySelectorAll('[style]').forEach((element) => {
                        if (!element.style.color) return;
                        if (element.tagName === 'SPAN') {
                            element.dataset.textColor = color;
                        } else {
                            const replacement = document.createElement('span');
                            replacement.dataset.textColor = color;
                            replacement.replaceChildren(...element.childNodes);
                            element.append(replacement);
                        }
                        element.style.removeProperty('color');
                        if (!element.getAttribute('style')) element.removeAttribute('style');
                    });
                }
                if (command === 'hiliteColor') {
                    const color = value.slice(1).toUpperCase();
                    editor.querySelectorAll('[style]').forEach((element) => {
                        if (!element.style.backgroundColor) return;
                        if (element.tagName === 'SPAN') {
                            element.dataset.backgroundColor = color;
                        } else {
                            const replacement = document.createElement('span');
                            replacement.dataset.backgroundColor = color;
                            replacement.replaceChildren(...element.childNodes);
                            element.append(replacement);
                        }
                        element.style.removeProperty('background-color');
                        if (!element.getAttribute('style')) element.removeAttribute('style');
                    });
                }
                if (['justifyLeft', 'justifyCenter', 'justifyRight'].includes(command)) {
                    const alignment = command.replace('justify', '').toLowerCase();
                    editor.querySelectorAll('[style]').forEach((element) => {
                        if (!element.style.textAlign) return;
                        element.setAttribute('align', alignment);
                        element.style.removeProperty('text-align');
                        if (!element.getAttribute('style')) element.removeAttribute('style');
                    });
                }
                const palette = button.closest('[data-rich-text-palette]');
                palette?.removeAttribute('open');
                paletteInteraction = false;
                editor.focus({ preventScroll: true });
                sync();
                if (palette) {
                    window.getSelection()?.collapseToEnd();
                    savedRange = null;
                }
            });
        });
        container.closest('form')?.addEventListener('submit', sync);
    });
}

document.addEventListener('DOMContentLoaded', initializeRichTextEditors);
document.addEventListener('livewire:navigated', initializeRichTextEditors);
document.addEventListener('pointerdown', (event) => {
    document.querySelectorAll('[data-rich-text-palette][open]').forEach((palette) => {
        if (!palette.contains(event.target)) palette.removeAttribute('open');
    });
});

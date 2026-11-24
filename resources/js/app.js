import './catalogue-selection';
import './playlist-album-selection';
import './confirmations';

document.querySelectorAll('[data-playlist-name-editor]').forEach((editor) => {
    const display = editor.querySelector('[data-playlist-name-display]');
    const form = editor.querySelector('[data-playlist-name-form]');
    const editButton = document.querySelector('[data-playlist-name-edit]');
    const cancelButton = editor.querySelector('[data-playlist-name-cancel]');

    if (!display || !form || !editButton || !cancelButton) {
        return;
    }

    editButton.addEventListener('click', () => {
        display.classList.add('hidden');
        form.classList.remove('hidden');
        form.querySelector('input')?.focus();
    });

    cancelButton.addEventListener('click', () => {
        form.classList.add('hidden');
        display.classList.remove('hidden');
    });
});

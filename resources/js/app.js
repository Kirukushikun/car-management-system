/**
 * The browser-side fence: when a Livewire request fails outright — the server crashed (500),
 * Cloudflare could not reach it (502/504), the upload was too big for the server (413) or the
 * connection dropped — show a plain notice instead of the raw error page. Only local
 * development with APP_DEBUG keeps Livewire's error page, so developers still see the trace.
 * "Page expired" (419) keeps Livewire's own refresh prompt.
 */
const keepDebugErrorPage = () => document.querySelector('meta[name="debug-error-page"]')?.content === '1';

const announceFailure = (status) => window.dispatchEvent(new CustomEvent('request-failed', { detail: { status } }));

document.addEventListener('livewire:init', () => {
    window.Livewire.interceptRequest(({ onError, onFailure }) => {
        onError(({ response, preventDefault }) => {
            if (response.status === 419 || keepDebugErrorPage()) {
                return;
            }

            preventDefault();
            announceFailure(response.status);
        });

        onFailure(() => announceFailure(0));
    });
});

window.addEventListener('livewire-upload-error', () => announceFailure('upload'));

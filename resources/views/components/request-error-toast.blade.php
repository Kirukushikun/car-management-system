{{--
    The notice resources/js/app.js raises when a request fails outright, instead of an error page.
    Nothing was saved by a failed request, so the message always says the user's entries are kept.
--}}
<div x-data="{
        open: false,
        message: '',
        timer: null,
        messages: {
            upload: 'The file did not upload. Check the message under the field, or try a smaller file. If it keeps happening, contact the IT Admin.',
            0: 'Could not reach the server — check your connection and try again. Nothing was saved.',
            413: 'That upload is too large for the server. Try fewer or smaller files. Nothing was saved.',
            502: 'The server did not respond in time, so nothing was saved. Your entries are still here — please try again in a moment.',
            503: 'The system is briefly unavailable (maintenance or restart). Nothing was saved — please try again in a moment.',
            504: 'The server did not respond in time, so nothing was saved. Your entries are still here — please try again in a moment.',
            403: 'You are not allowed to do that on this CAR. Refresh the page — it may have moved to someone else.',
            404: 'That item no longer exists. Refresh the page.',
        },
        show(status) {
            this.message = this.messages[status] ?? 'Something went wrong, so nothing was saved. Your entries are still here — please try again. If it keeps happening, contact the IT Admin.';
            this.open = true;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.open = false, 12000);
        },
     }"
     x-on:request-failed.window="show($event.detail.status)"
     x-show="open" x-cloak x-transition.opacity
     class="request-error" role="alert" aria-live="assertive">
    <div class="request-error-title">Not saved</div>
    <div class="request-error-message" x-text="message"></div>
    <button type="button" class="btn btn-ghost request-error-close" x-on:click="open = false" aria-label="Dismiss">✕</button>
</div>

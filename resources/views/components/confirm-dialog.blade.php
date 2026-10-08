{{--
    One confirmation dialog for the whole app. Open it from any button with
    $dispatch('confirm', { title, message, confirmLabel, danger, run: () => $wire.something() }).
--}}
<div x-data="{ open: false, title: '', message: '', confirmLabel: 'Confirm', danger: false, run: null }"
     x-on:confirm.window="title = $event.detail.title; message = $event.detail.message ?? ''; confirmLabel = $event.detail.confirmLabel ?? 'Confirm'; danger = !! $event.detail.danger; run = $event.detail.run; open = true; $nextTick(() => $refs.confirm.focus())"
     x-on:keydown.escape.window="open = false"
     x-show="open" x-cloak x-transition.opacity
     class="modal-backdrop">
    <div class="card modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title" x-on:click.outside="open = false">
        <div class="modal-title" id="confirm-title" x-text="title"></div>
        <div class="modal-message" x-text="message" x-show="message"></div>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" x-on:click="open = false">Cancel</button>
            <button type="button" x-ref="confirm" :class="danger ? 'btn btn-danger' : 'btn btn-accent'"
                    x-on:click="open = false; run && run()" x-text="confirmLabel"></button>
        </div>
    </div>
</div>

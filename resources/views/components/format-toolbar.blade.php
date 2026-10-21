{{--
    WYSIWYG for the textarea in the same field: a toolbar and a formatted editing box that shows
    bold, italic, underline and lists as you type. The real textarea (Livewire-bound, with its
    maxlength) is hidden and kept in sync as plain-text markers — see resources/js/rich-text.js.
    Pasting keeps the text only. Ctrl+B / Ctrl+I / Ctrl+U work as in a word processor.
--}}
<div {{ $attributes->merge(['class' => 'rich-editor']) }} x-data="richEditor" wire:ignore>
    <div class="format-toolbar" role="toolbar" aria-label="Text formatting">
        <button type="button" class="format-button" title="Bold (Ctrl+B)" aria-label="Bold" x-on:mousedown.prevent x-on:click="command('bold')"><i class="fa-solid fa-bold" aria-hidden="true"></i></button>
        <button type="button" class="format-button" title="Italic (Ctrl+I)" aria-label="Italic" x-on:mousedown.prevent x-on:click="command('italic')"><i class="fa-solid fa-italic" aria-hidden="true"></i></button>
        <button type="button" class="format-button" title="Underline (Ctrl+U)" aria-label="Underline" x-on:mousedown.prevent x-on:click="command('underline')"><i class="fa-solid fa-underline" aria-hidden="true"></i></button>
        <span class="format-divider" aria-hidden="true"></span>
        <button type="button" class="format-button" title="Bulleted list" aria-label="Bulleted list" x-on:mousedown.prevent x-on:click="command('insertUnorderedList')"><i class="fa-solid fa-list-ul" aria-hidden="true"></i></button>
        <button type="button" class="format-button" title="Numbered list" aria-label="Numbered list" x-on:mousedown.prevent x-on:click="command('insertOrderedList')"><i class="fa-solid fa-list-ol" aria-hidden="true"></i></button>
    </div>
    <div class="rich-box" x-ref="box" contenteditable="true" role="textbox" aria-multiline="true"
         x-on:input="sync()" x-on:blur="sync()" x-on:paste="paste($event)"></div>
</div>

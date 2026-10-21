/**
 * WYSIWYG for long text fields, without a library. The visible box is a contenteditable area that
 * shows bold, italic, underline and lists as you type; underneath, the field's real <textarea>
 * (bound to Livewire, carrying maxlength) keeps the same plain-text markers the server stores and
 * renders safely (App\Support\RichText): **bold**, *italic*, __underline__, "- " and "1. " lines.
 */

const escapeHtml = (text) => text.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[c]);

const inline = (line) => escapeHtml(line)
    .replace(/\*\*(.+?)\*\*/g, '<b>$1</b>')
    .replace(/__(.+?)__/g, '<u>$1</u>')
    .replace(/(^|[^*\w])\*(?![\s*])(.+?)(?<![\s*])\*(?![*\w])/g, '$1<i>$2</i>');

/**
 * Markers → editor HTML (mirrors RichText::toHtml, with one block per line so editing feels natural).
 */
export function markersToHtml(text) {
    if (! text) {
        return '';
    }

    let html = '';
    let list = null;

    for (const line of text.split(/\r?\n/)) {
        const bullet = line.match(/^\s*[-•]\s+(.*)$/);
        const numbered = line.match(/^\s*\d+[.)]\s+(.*)$/);
        const kind = bullet ? 'ul' : (numbered ? 'ol' : null);

        if (list && kind !== list) {
            html += `</${list}>`;
            list = null;
        }

        if (kind) {
            if (! list) {
                html += `<${kind}>`;
                list = kind;
            }
            html += `<li>${inline((bullet || numbered)[1]) || '<br>'}</li>`;
            continue;
        }

        html += `<div>${inline(line) || '<br>'}</div>`;
    }

    return list ? html + `</${list}>` : html;
}

/**
 * Editor HTML → markers. Only bold, italic, underline, lists and line breaks survive; everything
 * else becomes plain text.
 */
export function htmlToMarkers(root) {
    const lines = [];
    let current = '';

    const flush = () => {
        lines.push(current);
        current = '';
    };

    const style = (element) => {
        const tag = element.tagName;
        const weight = element.style?.fontWeight;

        return {
            bold: tag === 'B' || tag === 'STRONG' || weight === 'bold' || Number(weight) >= 600,
            italic: tag === 'I' || tag === 'EM' || element.style?.fontStyle === 'italic',
            underline: tag === 'U' || (element.style?.textDecoration || '').includes('underline'),
        };
    };

    const walkInline = (node) => {
        if (node.nodeType === Node.TEXT_NODE) {
            current += node.textContent.replace(/ /g, ' ').replace(/\n/g, ' ');
            return;
        }

        if (node.nodeType !== Node.ELEMENT_NODE) {
            return;
        }

        if (node.tagName === 'BR') {
            flush();
            return;
        }

        if (['DIV', 'P'].includes(node.tagName)) {
            if (current !== '') {
                flush();
            }
            node.childNodes.forEach(walkInline);
            flush();
            return;
        }

        if (['UL', 'OL'].includes(node.tagName)) {
            if (current !== '') {
                flush();
            }
            let n = 0;
            node.querySelectorAll(':scope > li').forEach((item) => {
                n += 1;
                current = node.tagName === 'UL' ? '- ' : `${n}. `;
                item.childNodes.forEach(walkInline);
                flush();
            });
            return;
        }

        const { bold, italic, underline } = style(node);
        const before = current;
        current = '';
        node.childNodes.forEach(walkInline);
        let inner = current;
        current = before;

        if (inner.trim() === '') {
            current += inner;
            return;
        }

        if (underline) inner = `__${inner}__`;
        if (italic) inner = `*${inner}*`;
        if (bold) inner = `**${inner}**`;
        current += inner;
    };

    root.childNodes.forEach(walkInline);
    if (current !== '') {
        flush();
    }

    while (lines.length && lines[lines.length - 1].trim() === '') {
        lines.pop();
    }

    return lines.join('\n');
}

/**
 * Alpine component: <div x-data="richEditor"> wrapping the toolbar, the editable box and the
 * real textarea (hidden).
 */
export function richEditor() {
    return {
        area: null,
        lastGood: '',

        init() {
            this.area = this.$root.closest('.field').querySelector('textarea');
            this.load(this.area.value);
            this.$refs.box.dataset.placeholder = this.area.placeholder || '';
            this.area.addEventListener('focus', () => this.$refs.box.focus());
            document.execCommand('defaultParagraphSeparator', false, 'div');

            // When the server changes the value (e.g. the reason box is cleared after an action), follow it.
            const path = this.area.getAttribute('wire:model') || this.area.getAttribute('wire:model.live');

            if (path && this.$wire?.$watch) {
                this.$wire.$watch(path, (value) => {
                    if ((value ?? '') !== htmlToMarkers(this.$refs.box)) {
                        this.load(value ?? '');
                    }
                });
            }
        },

        load(markers) {
            this.$refs.box.innerHTML = markersToHtml(markers);
            this.lastGood = this.$refs.box.innerHTML;
        },

        command(name) {
            this.$refs.box.focus();
            document.execCommand('styleWithCSS', false, false);
            document.execCommand(name, false, null);
            this.sync();
        },

        paste(event) {
            event.preventDefault();
            document.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
        },

        sync() {
            const markers = htmlToMarkers(this.$refs.box);

            if (this.area.maxLength > 0 && markers.length > this.area.maxLength) {
                this.$refs.box.innerHTML = this.lastGood;
                this.caretToEnd();
                return;
            }

            this.lastGood = this.$refs.box.innerHTML;

            if (this.area.value !== markers) {
                this.area.value = markers;
                this.area.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },

        caretToEnd() {
            const range = document.createRange();
            range.selectNodeContents(this.$refs.box);
            range.collapse(false);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        },
    };
}

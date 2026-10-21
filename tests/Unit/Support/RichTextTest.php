<?php

use App\Support\RichText;

it('turns the markers into bold, italic and underline', function () {
    expect((string) RichText::toHtml('Held **all** trays, *not* the __eggroom__ stock.'))
        ->toBe('Held <strong>all</strong> trays, <em>not</em> the <u>eggroom</u> stock.');
});

it('turns dash and numbered lines into lists, keeping other lines as text', function () {
    expect((string) RichText::toHtml("Actions:\n- Held the batch\n- Replaced stock\nThen:\n1. Retrain staff\n2. Audit"))
        ->toBe('Actions:<ul class="rt-list"><li>Held the batch</li><li>Replaced stock</li></ul>Then:<ol class="rt-list"><li>Retrain staff</li><li>Audit</li></ol>');
});

it('never lets typed or pasted HTML through', function () {
    expect((string) RichText::toHtml('<script>alert(1)</script> **<b onmouseover="x">hi</b>**'))
        ->toBe('&lt;script&gt;alert(1)&lt;/script&gt; <strong>&lt;b onmouseover=&quot;x&quot;&gt;hi&lt;/b&gt;</strong>');
});

it('leaves plain text, maths and empty values alone', function () {
    expect((string) RichText::toHtml("2 * 3 = 6\nline two"))->toBe('2 * 3 = 6<br>line two')
        ->and((string) RichText::toHtml(null))->toBe('')
        ->and((string) RichText::toHtml('   '))->toBe('');
});

it('drops the markers for CSV and email', function () {
    expect(RichText::toPlain('Held **all** trays, *not* the __eggroom__ stock.'))->toBe('Held all trays, not the eggroom stock.');
});

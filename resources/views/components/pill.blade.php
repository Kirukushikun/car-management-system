@props(['tone' => 'slate'])

<span {{ $attributes->merge(['class' => 'pill']) }} style="background:var(--{{ $tone }}-bg); color:var(--{{ $tone }});">{{ $slot }}</span>

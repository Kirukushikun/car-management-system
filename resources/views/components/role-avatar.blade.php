@props(['user', 'role' => null])

@php($tone = ($role ?? $user->role)->tone())

<div {{ $attributes }} style="background:var(--{{ $tone }}-bg); color:var(--{{ $tone }});">{{ $user->initials() }}</div>

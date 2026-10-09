<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ isset($title) ? $title.' — ' : '' }}{{ config('app.name') }}</title>

        <meta name="debug-error-page" content="{{ app()->isLocal() && config('app.debug') ? '1' : '0' }}">
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        {{ $slot }}

        <x-request-error-toast />
    </body>
</html>

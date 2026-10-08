<div class="login-screen">
    <div class="login-wrap">
        <div class="login-head">
            <span class="mark"><x-icon name="check" width="20" height="20" /></span>
            <h1>CAR Management System</h1>
            <p>Brookside Group of Companies — Table Egg &amp; DOP corrective action requests</p>
        </div>

        <form wire:submit="login" class="card login-card">
            @if (session('status'))
                <div class="flash">{{ session('status') }}</div>
            @endif
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" wire:model="email" autocomplete="username" autofocus>
                @error('email') <div class="error-text">{{ $message }}</div> @enderror
            </div>
            <div class="field" x-data="{ shown: false }">
                <label for="password">Password</label>
                <div style="display:flex; gap:6px;">
                    <input id="password" x-bind:type="shown ? 'text' : 'password'" type="password" wire:model="password" autocomplete="current-password">
                    <button type="button" class="btn btn-ghost" style="padding:0 8px;" x-on:click="shown = ! shown" x-text="shown ? 'Hide' : 'Show'">Show</button>
                </div>
                @error('password') <div class="error-text">{{ $message }}</div> @enderror
            </div>

            @if ($turnstileSiteKey)
                <div wire:ignore style="margin-top:12px;"
                     x-data
                     x-init="
                        window.onTurnstileReady = () => window.turnstile.render($el, {
                            sitekey: @js($turnstileSiteKey),
                            callback: (token) => $wire.set('turnstileToken', token, false),
                        });
                        const script = document.createElement('script');
                        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onTurnstileReady&render=explicit';
                        script.async = true;
                        document.head.appendChild(script);
                     "></div>
                @error('turnstileToken') <div class="error-text">{{ $message }}</div> @enderror
            @endif

            <button type="submit" class="btn btn-accent" style="width:100%; justify-content:center; margin-top:16px;">
                <span wire:loading.remove wire:target="login">Sign in</span>
                <span wire:loading wire:target="login">Signing in…</span>
            </button>
            <div class="hint" style="font-size:10.5px; color:var(--text3); margin-top:12px; text-align:center;">
                Use your company account. No access yet? Ask the IT Admin to grant it.
            </div>
        </form>

        @if ($sampleAccounts->isNotEmpty())
            <div class="divider">Sample accounts — development only · password "{{ config('login.sample_password') }}"</div>
            <div class="role-grid">
                @foreach ($roleCards as $sample)
                    <button type="button" class="card role-card" wire:key="sample-{{ $sample->id }}" wire:click="useSample('{{ $sample->email }}')">
                        <div class="role-top">
                            <x-role-avatar :user="$sample" class="role-avatar" />
                            <div>
                                <div class="role-name">{{ $sample->name }}</div>
                                <div class="role-scope">{{ $sample->email }} · {{ $sample->workplaceLabel() }}</div>
                            </div>
                        </div>
                        <x-pill :tone="$sample->role->tone()" class="role-title-pill">{{ $sample->role->label() }}</x-pill>
                        <div class="role-blurb">{{ $sample->role->description() }}</div>
                    </button>
                @endforeach
            </div>
            @if ($moreSampleAccounts->isNotEmpty())
                <details class="sample-more">
                    <summary>{{ $moreSampleAccounts->count() }} more sample {{ str('account')->plural($moreSampleAccounts->count()) }} (other farms and extra requestors)</summary>
                    <div class="sample-more-list">
                        @foreach ($moreSampleAccounts as $sample)
                            <button type="button" class="sample-more-row" wire:key="sample-more-{{ $sample->id }}" wire:click="useSample('{{ $sample->email }}')">
                                <span class="role-name">{{ $sample->name }}</span>
                                <span class="role-scope">{{ $sample->workplaceLabel() }}</span>
                                <x-pill :tone="$sample->role->tone()">{{ $sample->role->label() }}</x-pill>
                            </button>
                        @endforeach
                    </div>
                </details>
            @endif
            <div class="login-foot">Click an account to fill the form, then Sign in. Sample accounts never exist in production.</div>
        @elseif ($isNonProduction)
            <div class="login-foot" style="margin-top:22px;">No sample accounts found. Run <code>php artisan db:seed --class=TestSeeder</code>.</div>
        @endif
    </div>
</div>

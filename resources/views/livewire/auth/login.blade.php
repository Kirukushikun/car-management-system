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
            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" wire:model="password" autocomplete="current-password">
                @error('password') <div class="error-text">{{ $message }}</div> @enderror
            </div>
            <label class="check"><input type="checkbox" wire:model="remember"> Keep me signed in</label>
            <button type="submit" class="btn btn-accent" style="width:100%; justify-content:center; margin-top:16px;">
                <span wire:loading.remove wire:target="login">Sign in</span>
                <span wire:loading wire:target="login">Signing in…</span>
            </button>
            <div class="hint" style="font-size:10.5px; color:var(--text3); margin-top:12px; text-align:center;">
                No account? Ask the IT Admin — accounts are not self-registered.
            </div>
        </form>

        @if ($demoUsers->isNotEmpty())
            <div class="divider">Local only — sign in as a role</div>
            <div class="role-grid">
                @foreach ($demoUsers as $demoUser)
                    <button type="button" class="card role-card" wire:key="demo-{{ $demoUser->id }}" wire:click="loginAs({{ $demoUser->id }})">
                        <div class="role-top">
                            <x-role-avatar :user="$demoUser" class="role-avatar" />
                            <div>
                                <div class="role-name">{{ $demoUser->name }}</div>
                                <div class="role-scope">{{ $demoUser->workplaceLabel() }}</div>
                            </div>
                        </div>
                        <x-pill :tone="$demoUser->role->tone()" class="role-title-pill">{{ $demoUser->role->label() }}</x-pill>
                        <div class="role-blurb">{{ $demoUser->role->description() }}</div>
                    </button>
                @endforeach
            </div>
            <div class="login-foot">Shown only when APP_ENV=local. Seeded accounts all use the password "password".</div>
        @endif
    </div>
</div>

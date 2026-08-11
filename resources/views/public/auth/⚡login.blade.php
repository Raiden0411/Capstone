<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        body {
            background: #071412;
            color: white;
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .glass-card {
            background: rgba(255,255,255,.05);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 1.5rem;
            padding: 2rem;
            width: 100%;
            max-width: 28rem;
        }
        .input-field {
            width: 100%;
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            color: white;
            outline: none;
            transition: border-color 0.2s;
        }
        .input-field:focus {
            border-color: #22c55e;
            box-shadow: 0 0 0 2px rgba(34,197,94,.25);
        }
        .btn-primary {
            background: #16a34a;
            color: white;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary:hover {
            background: #15803d;
        }
    </style>
</head>
<body>
    <div class="glass-card">
        <h1 class="text-2xl font-bold mb-4">Sign In</h1>

        @if ($errors->any())
            <div class="text-red-400 mb-4 text-sm">{{ $errors->first('email') }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="mb-4">
                <label class="block text-sm text-white/70 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="input-field">
            </div>
            <div class="mb-4">
                <label class="block text-sm text-white/70 mb-1">Password</label>
                <input type="password" name="password" required class="input-field">
            </div>
            <div class="flex items-center mb-4">
                <input type="checkbox" name="remember" id="remember" class="mr-2">
                <label for="remember" class="text-sm text-white/60">Remember me</label>
            </div>
            <button type="submit" class="btn-primary w-full">Sign In</button>
        </form>
    </div>

    @livewireScripts
</body>
</html>
<!DOCTYPE html>
<html>
<head><title>Test Login</title></head>
<body>
    @if ($errors->any())
        <div style="color:red">{{ $errors->first('email') }}</div>
    @endif
    <form method="POST" action="/test-login">
        @csrf
        <label>Email: <input name="email" value="superadmin@gmail.com"></label>
        <label>Password: <input name="password" type="password" value="password"></label>
        <button type="submit">Sign In</button>
    </form>
</body>
</html>
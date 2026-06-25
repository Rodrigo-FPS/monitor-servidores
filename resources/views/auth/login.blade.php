<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — {{ config('app.name') }}</title>
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/all.min.css">
    <link rel="stylesheet" href="/css/login.css">
</head>
<body>
    <main class="login-card" aria-label="Inicio de sesión">
        <div class="card shadow-lg border-0">
            <div class="card-header bg-dark text-white text-center py-4">
                <h1 class="h4 mb-0"><i class="fas fa-server me-2" aria-hidden="true"></i>Monitor de Servidores</h1>
                <p class="text-muted mb-0 mt-1"><small>Panel de Administración</small></p>
            </div>
            <div class="card-body p-4">
                @if ($errors->has('auth'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i>{{ $errors->first('auth') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar alerta"></button>
                    </div>
                @endif

                <form method="POST" action="/login" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label for="username" class="form-label fw-semibold">Usuario</label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control @error('username') is-invalid @enderror"
                                   id="username" name="username"
                                   value="{{ old('username') }}"
                                   maxlength="64" autocomplete="username" autofocus required
                                   aria-required="true"
                                   @error('username') aria-describedby="error-username" @enderror>
                            @error('username')
                                <div class="invalid-feedback" id="error-username" role="alert">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label fw-semibold">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control @error('password') is-invalid @enderror"
                                   id="password" name="password"
                                   maxlength="255" autocomplete="current-password" required
                                   aria-required="true"
                                   @error('password') aria-describedby="error-password" @enderror>
                            @error('password')
                                <div class="invalid-feedback" id="error-password" role="alert">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2">
                        <i class="fas fa-sign-in-alt me-2" aria-hidden="true"></i>Entrar
                    </button>
                </form>
            </div>
        </div>
    </main>
    <script src="/js/bootstrap.bundle.min.js"></script>
</body>
</html>

from starlette.middleware.base import BaseHTTPMiddleware
from starlette.requests import Request
from starlette.responses import Response


class MiddlewareSeguridad(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next) -> Response:
        respuesta = await call_next(request)

        respuesta.headers["X-Frame-Options"]           = "DENY"
        respuesta.headers["X-Content-Type-Options"]    = "nosniff"
        respuesta.headers["Referrer-Policy"]           = "strict-origin-when-cross-origin"
        respuesta.headers["Content-Security-Policy"]   = (
            "default-src 'self'; "
            "script-src 'self'; "
            "style-src 'self'; "
            "style-src-attr 'unsafe-inline'; "
            "img-src 'self' data:; "
            "frame-ancestors 'none';"
        )
        respuesta.headers["Strict-Transport-Security"] = "max-age=31536000; includeSubDomains" #HSTS fuerza HTTPS por 1 ano
        respuesta.headers["Permissions-Policy"]        = "camera=(), microphone=(), geolocation=()"

        return respuesta

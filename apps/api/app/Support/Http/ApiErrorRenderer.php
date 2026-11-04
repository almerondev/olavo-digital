<?php

namespace App\Support\Http;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/** Garante o mesmo formato de erro em toda a API: { message, code, errors? }. */
final class ApiErrorRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $e instanceof ApiException => $this->json($e->getMessage(), $e->errorCode, $e->status),
            $e instanceof ValidationException => $this->json('Alguns campos precisam de correção.', 'validation_failed', $e->status, $e->errors()),
            $e instanceof AuthenticationException => $this->json('Entre na sua conta para continuar.', 'unauthenticated', 401),
            $e instanceof AccessDeniedHttpException => $this->forbidden($e),
            $e instanceof NotFoundHttpException => $this->json('Não encontramos o que você procurava.', 'not_found', 404),
            $e instanceof TooManyRequestsHttpException => $this->json('Muitas tentativas. Aguarde um momento.', 'too_many_requests', 429),
            $e instanceof HttpExceptionInterface && $e->getStatusCode() === 419 => $this->json('Sua sessão expirou. Recarregue a página.', 'csrf_mismatch', 419),
            $e instanceof HttpExceptionInterface => $this->json('Não foi possível concluir a solicitação.', 'http_error', $e->getStatusCode()),
            config('app.debug') => null,
            default => $this->json('Ocorreu um erro interno. Tente novamente.', 'server_error', 500),
        };
    }

    private function forbidden(Throwable $e): JsonResponse
    {
        $message = $e->getMessage();
        $generic = $message === '' || $message === 'This action is unauthorized.';

        return $this->json($generic ? 'Você não tem permissão para esta ação.' : $message, 'forbidden', 403);
    }

    /** @param array<string, list<string>>|null $errors */
    private function json(string $message, string $code, int $status, ?array $errors = null): JsonResponse
    {
        return response()->json(array_filter(
            ['message' => $message, 'code' => $code, 'errors' => $errors],
            fn ($value) => $value !== null,
        ), $status);
    }
}

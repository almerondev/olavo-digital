import { useMutation } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router'
import { Button } from '@/components/ui/button'
import { ApiError } from '@/services/http'
import { authApi } from '../api/auth'
import { AuthShell } from '../components/AuthShell'
import { Field } from '../components/Field'

export function ResetPasswordPage() {
  const [params] = useSearchParams()
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const reset = useMutation({ mutationFn: authApi.resetPassword })
  const error = reset.error instanceof ApiError ? reset.error : null

  function submit(event: FormEvent) {
    event.preventDefault()
    reset.mutate({
      token: params.get('token') ?? '',
      email: params.get('email') ?? '',
      password,
      password_confirmation: confirmation,
    })
  }

  return (
    <AuthShell title="Criar nova senha" subtitle="Use ao menos 8 caracteres, com letras e números.">
      {reset.isSuccess ? (
        <div className="grid gap-4">
          <p role="status" className="text-sm">
            {reset.data.message}
          </p>
          <Link to="/entrar" className="text-sm text-brand hover:underline">
            Ir para o login
          </Link>
        </div>
      ) : (
        <form onSubmit={submit} className="grid gap-5" noValidate>
          <Field
            id="password"
            label="Nova senha"
            type="password"
            autoComplete="new-password"
            autoFocus
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            error={error?.fieldError('password')}
          />
          <Field
            id="password_confirmation"
            label="Confirme a nova senha"
            type="password"
            autoComplete="new-password"
            value={confirmation}
            onChange={(e) => setConfirmation(e.target.value)}
          />
          {error?.fieldError('email') && (
            <p role="alert" className="text-sm text-destructive">
              {error.fieldError('email')}
            </p>
          )}
          <Button type="submit" size="lg" className="w-full font-semibold" disabled={reset.isPending}>
            {reset.isPending ? 'Salvando…' : 'Salvar nova senha'}
          </Button>
        </form>
      )}
    </AuthShell>
  )
}

import { useMutation } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { Button } from '@/components/ui/button'
import { ApiError } from '@/services/http'
import { authApi } from '../api/auth'
import { AuthShell } from '../components/AuthShell'
import { Field } from '../components/Field'

export function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const forgot = useMutation({ mutationFn: authApi.forgotPassword })
  const error = forgot.error instanceof ApiError ? forgot.error : null

  function submit(event: FormEvent) {
    event.preventDefault()
    forgot.mutate(email)
  }

  return (
    <AuthShell title="Redefinir senha" subtitle="Enviaremos um link para o seu e-mail.">
      {forgot.isSuccess ? (
        <p role="status" className="text-sm">
          {forgot.data.message}
        </p>
      ) : (
        <form onSubmit={submit} className="grid gap-5" noValidate>
          <Field
            id="email"
            label="E-mail"
            type="email"
            autoComplete="username"
            autoFocus
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            error={error?.fieldError('email') ?? (error?.errors ? undefined : error?.message)}
          />
          <Button type="submit" size="lg" className="w-full font-semibold" disabled={forgot.isPending}>
            {forgot.isPending ? 'Enviando…' : 'Enviar link'}
          </Button>
        </form>
      )}
      <Link to="/entrar" className="mt-6 inline-block text-sm text-brand hover:underline">
        Voltar para o login
      </Link>
    </AuthShell>
  )
}

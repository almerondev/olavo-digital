import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Link, useLocation, useNavigate } from 'react-router'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { AppDownload } from '@/modules/native/AppDownload'
import { isNativeApp } from '@/modules/native/push'
import { ApiError } from '@/services/http'
import { authApi, type Credentials } from '../api/auth'
import { AuthShell } from '../components/AuthShell'
import { Field } from '../components/Field'
import { CURRENT_USER_KEY } from '../hooks/useCurrentUser'
import { landingPath } from '../landing'

export function LoginPage() {
  const [form, setForm] = useState<Credentials>({ email: '', password: '', remember: isNativeApp() })
  const navigate = useNavigate()
  const location = useLocation()
  const queryClient = useQueryClient()
  const from = (location.state as { from?: string } | null)?.from

  const login = useMutation({
    mutationFn: authApi.login,
    onSuccess: (user) => {
      queryClient.setQueryData(CURRENT_USER_KEY, user)
      navigate(from ?? landingPath(user), { replace: true })
    },
  })

  const error = login.error instanceof ApiError ? login.error : null

  function submit(event: FormEvent) {
    event.preventDefault()
    login.mutate(form)
  }

  return (
    <AuthShell title="Entrar" subtitle="Use o e-mail cadastrado na secretaria do Olavo.">
      <form onSubmit={submit} className="grid gap-5" noValidate>
        <Field
          id="email"
          label="E-mail"
          type="email"
          autoComplete="username"
          autoFocus
          value={form.email}
          onChange={(e) => setForm({ ...form, email: e.target.value })}
          error={error?.fieldError('email')}
        />
        <Field
          id="password"
          label="Senha"
          type="password"
          autoComplete="current-password"
          value={form.password}
          onChange={(e) => setForm({ ...form, password: e.target.value })}
          error={error?.fieldError('password')}
        />
        <div className="flex items-center justify-between gap-4">
          <Label className="flex items-center gap-2 font-normal">
            <Checkbox
              checked={form.remember}
              onCheckedChange={(checked) => setForm({ ...form, remember: checked === true })}
            />
            Manter conectado
          </Label>
          <Link to="/esqueci-senha" className="text-sm text-brand hover:underline">
            Esqueci minha senha
          </Link>
        </div>
        {error && !error.fieldError('email') && !error.fieldError('password') && (
          <p role="alert" className="text-sm text-destructive">
            {error.message}
          </p>
        )}
        <Button type="submit" size="lg" className="w-full font-semibold" disabled={login.isPending}>
          {login.isPending ? 'Entrando…' : 'Entrar'}
        </Button>
      </form>
      <AppDownload />
    </AuthShell>
  )
}

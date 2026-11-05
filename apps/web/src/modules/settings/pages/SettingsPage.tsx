import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent, type ReactNode } from 'react'
import { Link } from 'react-router'
import { coverSrc } from '@/components/media/cover'
import { UploadField } from '@/components/media/UploadField'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { ApiError } from '@/services/http'
import type { PlatformSettings, Upload } from '@/types/api'
import { settingsApi } from '../api'
import { CONFIG_KEY } from '../config'

const KEY = ['admin', 'settings']
const SOCIALS = [['instagram', 'Instagram'], ['youtube', 'YouTube'], ['facebook', 'Facebook'], ['tiktok', 'TikTok']] as const
const STORAGE_LABELS: Record<string, string> = { local: 'Disco do servidor', s3: 'S3 ou Cloudflare R2' }

function Panel({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
  return (
    <section aria-label={title} className="grid gap-4 rounded-lg border bg-card p-5">
      <div>
        <h2 className="font-heading text-lg font-bold">{title}</h2>
        {description && <p className="text-sm text-muted-foreground">{description}</p>}
      </div>
      {children}
    </section>
  )
}

function SettingsForm({ settings }: { settings: PlatformSettings }) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState({
    name: settings.name,
    tagline: settings.tagline ?? '',
    footer: settings.footer ?? '',
    brand_color: settings.brand_color,
    socials: settings.socials ?? {},
    android_url: settings.android_url ?? '',
    terms: settings.terms ?? '',
    privacy: settings.privacy ?? '',
    auto_material: settings.auto_material,
    auto_live: settings.auto_live,
  })
  const [logo, setLogo] = useState<Upload | null>(null)
  const [currentLogo, setCurrentLogo] = useState(settings.logo_upload_id)
  const logoPreview = coverSrc(settings.logo_urls)

  const save = useMutation({
    mutationFn: () => settingsApi.update({
      ...form,
      tagline: form.tagline || null,
      android_url: form.android_url || null,
      footer: form.footer || null,
      terms: form.terms || null,
      privacy: form.privacy || null,
      logo_upload_id: logo?.id ?? currentLogo,
    }),
    onSuccess: (saved) => {
      queryClient.setQueryData(KEY, saved)
      setLogo(null)
      setCurrentLogo(saved.logo_upload_id)
      return queryClient.invalidateQueries({ queryKey: CONFIG_KEY })
    },
  })
  const error = save.error instanceof ApiError ? save.error : null

  function submit(event: FormEvent) {
    event.preventDefault()
    save.mutate()
  }

  return (
    <form onSubmit={submit} className="grid gap-5" noValidate>
      <Panel title="Identidade visual">
        <div className="grid gap-3 sm:grid-cols-2">
          <div className="grid gap-2">
            <Label htmlFor="set-name">Nome da plataforma</Label>
            <Input id="set-name" value={form.name} aria-invalid={Boolean(error?.fieldError('name'))} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            {error?.fieldError('name') && <p className="text-sm text-destructive">{error.fieldError('name')}</p>}
          </div>
          <div className="grid gap-2">
            <Label htmlFor="set-tagline">Frase</Label>
            <Input id="set-tagline" value={form.tagline} onChange={(e) => setForm({ ...form, tagline: e.target.value })} />
          </div>
        </div>
        <div className="grid gap-2">
          <Label htmlFor="set-color">Cor principal</Label>
          <div className="flex items-center gap-2">
            <input id="set-color" type="color" className="size-9 cursor-pointer rounded border bg-transparent" value={form.brand_color ?? '#e8a832'} onChange={(e) => setForm({ ...form, brand_color: e.target.value })} />
            <Button type="button" variant="ghost" size="sm" disabled={!form.brand_color} onClick={() => setForm({ ...form, brand_color: null })}>Usar cor padrão</Button>
          </div>
        </div>
        {currentLogo && !logo && logoPreview && (
          <div className="flex items-center gap-3">
            <img src={logoPreview} alt="Logo atual" className="h-12 rounded bg-black p-1" />
            <Button type="button" variant="ghost" size="sm" onClick={() => setCurrentLogo(null)}>Voltar à logo padrão</Button>
          </div>
        )}
        <UploadField label="Trocar logo" purpose="image" accept=".jpg,.jpeg,.png,.webp" value={logo} onChange={setLogo} />
        <p className="text-sm text-muted-foreground">Os banners ficam no <Link to="/admin/home" className="underline">Construtor da home</Link>.</p>
      </Panel>

      <Panel title="Rodapé e redes sociais">
        <div className="grid gap-2">
          <Label htmlFor="set-footer">Texto do rodapé</Label>
          <Input id="set-footer" value={form.footer} onChange={(e) => setForm({ ...form, footer: e.target.value })} />
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          {SOCIALS.map(([key, label]) => (
            <div key={key} className="grid gap-2">
              <Label htmlFor={`set-${key}`}>{label}</Label>
              <Input id={`set-${key}`} type="url" placeholder="https://" value={form.socials[key] ?? ''} aria-invalid={Boolean(error?.fieldError(`socials.${key}`))} onChange={(e) => setForm({ ...form, socials: { ...form.socials, [key]: e.target.value || null } })} />
              {error?.fieldError(`socials.${key}`) && <p className="text-sm text-destructive">Use um link completo com https.</p>}
            </div>
          ))}
        </div>
      </Panel>

      <Panel title="Aplicativo" description="Quem entra pelo navegador vê este link na tela de login. Sem link, o aviso não aparece.">
        <div className="grid gap-2">
          <Label htmlFor="set-android">Link do APK para Android</Label>
          <Input id="set-android" placeholder="/downloads/olavo-tv-plus.apk" value={form.android_url} aria-invalid={Boolean(error?.fieldError('android_url'))} onChange={(e) => setForm({ ...form, android_url: e.target.value })} />
          {error?.fieldError('android_url') && <p className="text-sm text-destructive">Use um caminho da plataforma ou um link https.</p>}
        </div>
      </Panel>

      <Panel title="Notificações automáticas" description="Mensagens enviadas por você sempre chegam.">
        <Label className="flex items-center gap-2 font-normal"><Checkbox checked={form.auto_material} onCheckedChange={(value) => setForm({ ...form, auto_material: value === true })} />Avisar sobre novos materiais e vídeos</Label>
        <Label className="flex items-center gap-2 font-normal"><Checkbox checked={form.auto_live} onCheckedChange={(value) => setForm({ ...form, auto_live: value === true })} />Avisar quando uma transmissão começar</Label>
      </Panel>

      <Panel title="Termos e privacidade" description="Ao preencher ou alterar estes textos, todos precisam aceitar de novo no próximo acesso. Revise com seu jurídico antes de publicar.">
        <div className="grid gap-2">
          <Label htmlFor="set-terms">Termos de uso</Label>
          <Textarea id="set-terms" rows={6} value={form.terms} onChange={(e) => setForm({ ...form, terms: e.target.value })} />
        </div>
        <div className="grid gap-2">
          <Label htmlFor="set-privacy">Política de privacidade</Label>
          <Textarea id="set-privacy" rows={6} value={form.privacy} onChange={(e) => setForm({ ...form, privacy: e.target.value })} />
        </div>
      </Panel>

      <Panel title="Integrações" description="Definidas no servidor. Chaves e senhas nunca aparecem aqui.">
        <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1 text-sm">
          <dt className="text-muted-foreground">Armazenamento</dt><dd>{STORAGE_LABELS[settings.environment.storage] ?? settings.environment.storage}</dd>
          <dt className="text-muted-foreground">Envio de e-mail</dt><dd>{settings.environment.mail === 'log' ? 'Desligado: e-mails ficam só no registro do servidor' : 'SMTP'}</dd>
          <dt className="text-muted-foreground">Fila</dt><dd>{settings.environment.queue}</dd>
          <dt className="text-muted-foreground">Vídeos e lives</dt><dd>YouTube</dd>
          <dt className="text-muted-foreground">Push no celular</dt><dd>{settings.environment.push ? 'Firebase conectado' : 'Falta conectar o Firebase'}</dd>
        </dl>
      </Panel>

      {error && Object.keys(error.errors ?? {}).length === 0 && <p role="alert" className="text-sm text-destructive">{error.message}</p>}
      {error && Object.keys(error.errors ?? {}).length > 0 && <p role="alert" className="text-sm text-destructive">Revise os campos marcados.</p>}
      {save.isSuccess && <p role="status" className="text-sm text-muted-foreground">Configurações salvas.</p>}
      <div><Button type="submit" disabled={save.isPending}>{save.isPending ? 'Salvando…' : 'Salvar configurações'}</Button></div>
    </form>
  )
}

export function SettingsPage() {
  const settings = useQuery({ queryKey: KEY, queryFn: settingsApi.show })

  return (
    <div className="grid max-w-3xl gap-5">
      <h1 className="font-heading text-3xl font-bold tracking-tight">Configurações</h1>
      {settings.isError && <p role="alert">Não foi possível carregar as configurações. Recarregue a página.</p>}
      {settings.data && <SettingsForm settings={settings.data} />}
    </div>
  )
}

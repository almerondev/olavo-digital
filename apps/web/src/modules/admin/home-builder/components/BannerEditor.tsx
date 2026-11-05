import { useMutation } from '@tanstack/react-query'
import { X } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { UploadField } from '@/components/media/UploadField'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ApiError } from '@/services/http'
import type { BuilderBanner, HeroMediaType, Upload } from '@/types/api'
import { homeBuilderApi } from '../api'
import { MaterialPicker, type PickedMaterial } from './MaterialPicker'
import { AudienceField, fromIso, PeriodFields, toIso } from './SharedFields'

const MEDIA_OPTIONS: { value: HeroMediaType; label: string; hint: string }[] = [
  { value: 'image', label: 'Imagem', hint: 'Mostra só a imagem, sem vídeo.' },
  { value: 'preview', label: 'Prévia do vídeo selecionado', hint: 'Toca uma prévia automática do conteúdo em vídeo escolhido abaixo.' },
  { value: 'video', label: 'Vídeo enviado', hint: 'Toca em repetição o vídeo que você enviar, sem som. Use MP4 ou WebM.' },
]

interface BannerEditorProps {
  layoutId: number
  banner: BuilderBanner | null
  onSaved: () => void
  onClose: () => void
}

export function BannerEditor({ layoutId, banner, onSaved, onClose }: BannerEditorProps) {
  const [material, setMaterial] = useState<PickedMaterial | null>(banner?.material ?? null)
  const [image, setImage] = useState<Upload | null>(banner?.image ?? null)
  const [video, setVideo] = useState<Upload | null>(banner?.video ?? null)
  const [mediaType, setMediaType] = useState<HeroMediaType>(banner?.media_type ?? 'image')
  const [form, setForm] = useState({
    title: banner?.title ?? '',
    description: banner?.description ?? '',
    cta_label: banner?.cta_label ?? '',
    external_url: banner?.external_url ?? '',
    is_active: banner?.is_active ?? true,
    starts_at: fromIso(banner?.starts_at ?? null),
    ends_at: fromIso(banner?.ends_at ?? null),
    group_ids: banner?.group_ids ?? [],
  })

  const save = useMutation({
    mutationFn: () => {
      const input = {
        ...form,
        external_url: form.external_url || null,
        starts_at: toIso(form.starts_at),
        ends_at: toIso(form.ends_at),
        material_id: material?.id ?? null,
        image_upload_id: image?.id ?? null,
        media_type: mediaType,
        video_upload_id: mediaType === 'video' ? video?.id ?? null : null,
      }
      return banner ? homeBuilderApi.updateBanner(banner.id, input) : homeBuilderApi.createBanner(layoutId, input)
    },
    onSuccess: onSaved,
  })
  const error = save.error instanceof ApiError ? save.error : null

  function submit(event: FormEvent) {
    event.preventDefault()
    save.mutate()
  }

  return (
    <form onSubmit={submit} className="grid gap-4 rounded-lg border bg-card p-5" noValidate>
      <div className="flex items-center justify-between">
        <h2 className="font-heading text-lg font-bold">{banner ? 'Editar destaque' : 'Novo destaque'}</h2>
        <Button type="button" variant="ghost" size="icon" aria-label="Fechar" onClick={onClose}><X className="size-4" /></Button>
      </div>

      <fieldset className="grid gap-2">
        <legend className="mb-1 text-sm font-medium">O que aparece no destaque</legend>
        {MEDIA_OPTIONS.map((option) => (
          <label key={option.value} className="flex items-start gap-2 text-sm">
            <input type="radio" name="banner-media" className="mt-0.5 accent-brand" checked={mediaType === option.value} onChange={() => setMediaType(option.value)} />
            <span>
              <span className="block">{option.label}</span>
              <span className="block text-xs text-muted-foreground">{option.hint}</span>
            </span>
          </label>
        ))}
      </fieldset>

      {mediaType === 'video' && <UploadField label="Vídeo do destaque" purpose="material" accept=".mp4,.webm" value={video} error={error?.fieldError('video_upload_id')} onChange={setVideo} />}

      <div className="grid gap-2">
        <p className="text-sm font-medium">Conteúdo</p>
        {material ? (
          <div className="flex items-center gap-2 rounded-lg bg-muted/60 px-2 py-1 text-sm">
            <span className="min-w-0 flex-1 truncate">{material.title}</span>
            <Button type="button" variant="ghost" size="icon" aria-label={`Remover ${material.title}`} onClick={() => setMaterial(null)}><X className="size-3.5" /></Button>
          </div>
        ) : (
          <MaterialPicker label="Buscar conteúdo" excluded={[]} onPick={setMaterial} />
        )}
        {error?.fieldError('material_id') && <p className="text-sm text-destructive">{error.fieldError('material_id')}</p>}
        <p className="text-xs text-muted-foreground">{mediaType === 'preview' ? 'Escolha um conteúdo em vídeo.' : 'Opcional: o botão do destaque abre este conteúdo.'}</p>
      </div>

      <div className="grid gap-2">
        <Label htmlFor="banner-title">Título {material && '(opcional, substitui o do conteúdo)'}</Label>
        <Input id="banner-title" value={form.title} aria-invalid={Boolean(error?.fieldError('title'))} onChange={(e) => setForm({ ...form, title: e.target.value })} />
        {error?.fieldError('title') && <p className="text-sm text-destructive">{error.fieldError('title')}</p>}
      </div>
      <div className="grid gap-2">
        <Label htmlFor="banner-description">Descrição</Label>
        <Input id="banner-description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
      </div>
      <div className="grid gap-3 sm:grid-cols-2">
        <div className="grid gap-2">
          <Label htmlFor="banner-cta">Texto do botão</Label>
          <Input id="banner-cta" placeholder="Assistir agora" value={form.cta_label} onChange={(e) => setForm({ ...form, cta_label: e.target.value })} />
        </div>
        <div className="grid gap-2">
          <Label htmlFor="banner-url">Endereço externo</Label>
          <Input id="banner-url" type="url" placeholder="https://" value={form.external_url} disabled={material !== null} aria-invalid={Boolean(error?.fieldError('external_url'))} onChange={(e) => setForm({ ...form, external_url: e.target.value })} />
          {error?.fieldError('external_url') && <p className="text-sm text-destructive">{error.fieldError('external_url')}</p>}
        </div>
      </div>

      <UploadField label={mediaType === 'image' ? 'Imagem (16:9)' : 'Imagem de capa (16:9), exibida antes do vídeo'} purpose="image" accept=".jpg,.jpeg,.png,.webp" value={image} onChange={setImage} />
      <PeriodFields prefix="banner" startsAt={form.starts_at} endsAt={form.ends_at} error={error?.fieldError('ends_at')} onChange={(field, value) => setForm({ ...form, [field]: value })} />
      <AudienceField value={form.group_ids} onChange={(group_ids) => setForm({ ...form, group_ids })} />

      <Label className="flex items-center gap-2 font-normal">
        <Checkbox checked={form.is_active} onCheckedChange={(value) => setForm({ ...form, is_active: value === true })} />
        Destaque ativo
      </Label>

      <div><Button type="submit" disabled={save.isPending}>{save.isPending ? 'Salvando…' : 'Salvar destaque'}</Button></div>
    </form>
  )
}

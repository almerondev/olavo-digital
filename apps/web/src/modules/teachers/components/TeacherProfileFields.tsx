import { useState, type KeyboardEvent } from 'react'
import { UploadField } from '@/components/media/UploadField'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import type { ApiError } from '@/services/http'
import type { TeacherSocial, Upload } from '@/types/api'

export const NETWORKS = [
  ['instagram', 'Instagram'],
  ['youtube', 'YouTube'],
  ['tiktok', 'TikTok'],
  ['linkedin', 'LinkedIn'],
  ['site', 'Site'],
] as const

export interface ProfileValues {
  short_bio: string
  bio: string
  specialties: string[]
  photo: Upload | null
  banner: Upload | null
  socials: TeacherSocial[]
}

export function toProfileInput({ photo, banner, ...rest }: ProfileValues) {
  return { ...rest, photo_upload_id: photo?.id ?? null, banner_upload_id: banner?.id ?? null }
}

export function TeacherProfileFields({ value, error, onChange }: { value: ProfileValues; error: ApiError | null; onChange: (value: ProfileValues) => void }) {
  const [draft, setDraft] = useState('')

  function addSpecialty(event: KeyboardEvent<HTMLInputElement>) {
    if (event.key !== 'Enter' && event.key !== ',') return
    event.preventDefault()
    const specialty = draft.trim()
    if (specialty && !value.specialties.includes(specialty)) onChange({ ...value, specialties: [...value.specialties, specialty] })
    setDraft('')
  }

  function setSocial(network: string, url: string) {
    onChange({ ...value, socials: [...value.socials.filter((item) => item.network !== network), { network, url }] })
  }

  return (
    <>
      <div className="grid gap-4 sm:grid-cols-2">
        <UploadField label="Foto (3:4)" purpose="image" accept=".jpg,.jpeg,.png,.webp" value={value.photo} onChange={(photo) => onChange({ ...value, photo })} />
        <UploadField label="Banner (16:9)" purpose="image" accept=".jpg,.jpeg,.png,.webp" value={value.banner} onChange={(banner) => onChange({ ...value, banner })} />
      </div>
      <div className="grid gap-2">
        <Label htmlFor="teacher-short-bio">Descrição curta</Label>
        <Input id="teacher-short-bio" maxLength={280} value={value.short_bio} onChange={(event) => onChange({ ...value, short_bio: event.target.value })} />
      </div>
      <div className="grid gap-2">
        <Label htmlFor="teacher-bio">Biografia</Label>
        <Textarea id="teacher-bio" rows={4} value={value.bio} onChange={(event) => onChange({ ...value, bio: event.target.value })} />
      </div>
      <div className="grid gap-2">
        <Label htmlFor="teacher-specialties">Especialidades</Label>
        {value.specialties.length > 0 && (
          <div className="flex flex-wrap gap-1">
            {value.specialties.map((specialty) => (
              <button
                key={specialty}
                type="button"
                aria-label={`Remover ${specialty}`}
                onClick={() => onChange({ ...value, specialties: value.specialties.filter((item) => item !== specialty) })}
                className="rounded bg-muted px-2 py-0.5 text-xs hover:bg-destructive/15"
              >
                {specialty} ×
              </button>
            ))}
          </div>
        )}
        <Input id="teacher-specialties" placeholder="Digite e pressione Enter" value={draft} onChange={(event) => setDraft(event.target.value)} onKeyDown={addSpecialty} />
      </div>
      <fieldset className="grid gap-2">
        <legend className="mb-1 text-sm font-medium">Redes sociais</legend>
        {NETWORKS.map(([network, label], index) => (
          <div key={network} className="grid gap-1">
            <Input
              aria-label={label}
              type="url"
              placeholder={`${label}: https://`}
              value={value.socials.find((item) => item.network === network)?.url ?? ''}
              onChange={(event) => setSocial(network, event.target.value)}
            />
            {error?.errors?.[`socials.${index}.url`] && <p className="text-sm text-destructive">Use um endereço iniciado por https://</p>}
          </div>
        ))}
      </fieldset>
    </>
  )
}

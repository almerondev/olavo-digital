import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useGroupOptions } from '@/modules/materials/hooks'
import { toLocalInput } from '@/utils/format'

export function toIso(value: string): string | null {
  return value ? new Date(value).toISOString() : null
}

export function fromIso(value: string | null): string {
  return toLocalInput(value)
}

export function PeriodFields({ prefix, startsAt, endsAt, error, onChange }: {
  prefix: string
  startsAt: string
  endsAt: string
  error?: string
  onChange: (field: 'starts_at' | 'ends_at', value: string) => void
}) {
  return (
    <div className="grid gap-3 sm:grid-cols-2">
      <div className="grid gap-2">
        <Label htmlFor={`${prefix}-start`}>Exibir a partir de</Label>
        <Input id={`${prefix}-start`} type="datetime-local" value={startsAt} onChange={(event) => onChange('starts_at', event.target.value)} />
      </div>
      <div className="grid gap-2">
        <Label htmlFor={`${prefix}-end`}>Exibir até</Label>
        <Input id={`${prefix}-end`} type="datetime-local" value={endsAt} aria-invalid={Boolean(error)} onChange={(event) => onChange('ends_at', event.target.value)} />
        {error && <p className="text-sm text-destructive">{error}</p>}
      </div>
    </div>
  )
}

export function AudienceField({ value, onChange }: { value: number[]; onChange: (ids: number[]) => void }) {
  const groups = useGroupOptions()

  return (
    <fieldset className="grid gap-2">
      <legend className="mb-1 text-sm font-medium">Público</legend>
      <p className="text-xs text-muted-foreground">Sem turma marcada, aparece para todos os alunos.</p>
      {groups.data?.map((group) => (
        <Label key={group.id} className="flex items-center gap-2 font-normal">
          <Checkbox
            checked={value.includes(group.id)}
            onCheckedChange={(checked) => onChange(checked === true ? [...value, group.id] : value.filter((id) => id !== group.id))}
          />
          {group.name}
        </Label>
      ))}
    </fieldset>
  )
}

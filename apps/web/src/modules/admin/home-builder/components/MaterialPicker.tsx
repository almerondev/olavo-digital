import { useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Input } from '@/components/ui/input'
import { materialsApi } from '@/modules/materials/api/materials'

export interface PickedMaterial {
  id: number
  title: string
}

/** Busca entre os materiais publicados para escolher um conteúdo. */
export function MaterialPicker({ label, excluded, onPick }: { label: string; excluded: number[]; onPick: (material: PickedMaterial) => void }) {
  const [search, setSearch] = useState('')
  const [term, setTerm] = useState('')

  useEffect(() => {
    const timer = setTimeout(() => setTerm(search.trim()), 300)
    return () => clearTimeout(timer)
  }, [search])

  const results = useQuery({
    queryKey: ['admin', 'material-picker', term],
    queryFn: () => materialsApi.list(new URLSearchParams({ status: 'published', q: term })),
    enabled: term.length >= 2,
  })
  const options = (results.data?.data ?? []).filter((material) => !excluded.includes(material.id)).slice(0, 6)

  return (
    <div className="grid gap-1">
      <Input aria-label={label} placeholder={label} value={search} onChange={(event) => setSearch(event.target.value)} />
      {term.length >= 2 && results.isSuccess && options.length === 0 && <p className="text-xs text-muted-foreground">Nenhum material publicado encontrado.</p>}
      {options.length > 0 && (
        <ul className="grid overflow-hidden rounded-lg border">
          {options.map((material) => (
            <li key={material.id}>
              <button
                type="button"
                className="w-full px-3 py-1.5 text-left text-sm hover:bg-muted"
                onClick={() => {
                  onPick({ id: material.id, title: material.title })
                  setSearch('')
                }}
              >
                {material.title}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

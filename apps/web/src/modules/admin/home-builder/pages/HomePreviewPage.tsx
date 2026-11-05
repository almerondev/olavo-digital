import { useQuery } from '@tanstack/react-query'
import { ArrowLeft } from 'lucide-react'
import { useMemo, useState } from 'react'
import { Link, useParams } from 'react-router'
import { NativeSelect } from '@/components/ui/native-select'
import { useGroupOptions } from '@/modules/materials/hooks'
import { SurfaceContent } from '@/modules/home/pages/HomePage'
import { homeBuilderApi } from '../api'

/** Usa os mesmos componentes do aluno, com o conteúdo do rascunho. */
export function HomePreviewPage() {
  const id = Number(useParams().id)
  const [group, setGroup] = useState('')
  const groups = useGroupOptions()
  const preview = useQuery({ queryKey: ['admin', 'home-preview', id, group], queryFn: () => homeBuilderApi.preview(id, group), staleTime: 0, gcTime: 0 })

  const data = useMemo(() => preview.data && {
    ...preview.data,
    sections: preview.data.sections.map((section) => ({ ...section, has_more: false })),
  }, [preview.data])

  return (
    <div className="dark min-h-dvh bg-background text-foreground">
      <div className="sticky top-0 z-50 flex flex-wrap items-center gap-3 bg-brand px-4 py-2 text-sm font-medium text-brand-foreground">
        <Link to="/admin/home" className="inline-flex items-center gap-1 hover:underline">
          <ArrowLeft className="size-4" />
          Voltar ao construtor
        </Link>
        <span className="mr-auto">Pré-visualização. Os alunos ainda não veem esta versão.</span>
        <label className="flex items-center gap-2">
          Ver como
          <NativeSelect aria-label="Ver como" className="w-48 border-black/30 bg-white/30 text-brand-foreground" value={group} onChange={(event) => setGroup(event.target.value)}>
            <option value="">Aluno sem turma</option>
            {groups.data?.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}
          </NativeSelect>
        </label>
      </div>
      {preview.isPending && <p role="status" className="p-8 text-center text-muted-foreground">Montando a pré-visualização…</p>}
      {preview.isError && <p role="alert" className="p-8 text-center">Não foi possível montar a pré-visualização.</p>}
      {data && (
        data.hero.length === 0 && data.sections.length === 0
          ? <p className="p-16 text-center text-muted-foreground">Nada apareceria para este público.</p>
          : <SurfaceContent key={group} data={data} cacheScope={`preview-${id}-${group}-${preview.dataUpdatedAt}`} />
      )}
    </div>
  )
}

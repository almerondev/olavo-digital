import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { Button } from '@/components/ui/button'
import { ApiError } from '@/services/http'
import type { TeacherAdmin } from '@/types/api'
import { teacherPanelApi } from '../api'
import { TeacherProfileFields, toProfileInput, type ProfileValues } from '../components/TeacherProfileFields'

function ProfileForm({ teacher }: { teacher: TeacherAdmin }) {
  const queryClient = useQueryClient()
  const [profile, setProfile] = useState<ProfileValues>({
    short_bio: teacher.short_bio ?? '',
    bio: teacher.bio ?? '',
    specialties: teacher.specialties,
    photo: teacher.photo,
    banner: teacher.banner,
    socials: teacher.socials,
  })

  const save = useMutation({
    mutationFn: () => teacherPanelApi.updateProfile(toProfileInput(profile)),
    onSuccess: (updated) => {
      queryClient.setQueryData(['teacher', 'profile'], updated)
      queryClient.invalidateQueries({ queryKey: ['cast'] })
    },
  })
  const error = save.error instanceof ApiError ? save.error : null

  function submit(event: FormEvent) {
    event.preventDefault()
    save.mutate()
  }

  return (
    <form onSubmit={submit} className="grid gap-4 rounded-lg border bg-card p-5" noValidate>
      <TeacherProfileFields value={profile} error={error} onChange={setProfile} />
      {save.isSuccess && <p role="status" className="text-sm text-muted-foreground">Perfil salvo.</p>}
      {error && <p role="alert" className="text-sm text-destructive">{error.message}</p>}
      <div><Button type="submit" disabled={save.isPending}>{save.isPending ? 'Salvando…' : 'Salvar'}</Button></div>
    </form>
  )
}

export function TeacherOwnProfilePage() {
  const profile = useQuery({ queryKey: ['teacher', 'profile'], queryFn: teacherPanelApi.profile })

  return (
    <div className="grid max-w-2xl gap-5">
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-heading text-3xl font-bold tracking-tight">Meu perfil no Elenco</h1>
          <p className="mt-1 text-sm text-muted-foreground">É assim que os alunos conhecem você.</p>
        </div>
        {profile.data && <Link to={`/elenco/${profile.data.slug}`} className="text-sm underline">Ver como aluno</Link>}
      </header>
      {profile.isError && <p role="alert">Não foi possível carregar seu perfil. Recarregue a página.</p>}
      {profile.data && <ProfileForm teacher={profile.data} />}
    </div>
  )
}

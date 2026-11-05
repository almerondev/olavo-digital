import { useQuery } from '@tanstack/react-query'
import { teacherBackend } from '@/modules/materials/backends'
import { MaterialEditor } from '@/modules/materials/pages/MaterialEditorPage'
import { teacherPanelApi } from '../api'

export function TeacherMaterialEditorPage() {
  const overview = useQuery({ queryKey: ['teacher', 'overview'], queryFn: teacherPanelApi.overview })

  return <MaterialEditor backend={teacherBackend} canPublish={overview.data?.can_publish ?? false} />
}

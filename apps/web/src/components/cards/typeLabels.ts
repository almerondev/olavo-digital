import type { MaterialType } from '@/types/api'

export const STUDENT_TYPE_LABELS: Record<MaterialType, string> = {
  pdf: 'PDF',
  image: 'Imagem',
  video: 'Videoaula',
  external_video: 'Videoaula',
  link: 'Link',
  document: 'Documento',
  presentation: 'Apresentação',
  spreadsheet: 'Planilha',
  audio: 'Áudio',
  other: 'Arquivo',
}

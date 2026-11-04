import type { MaterialStatus, MaterialType, MaterialVisibility } from '@/types/api'

export const TYPE_LABELS: Record<MaterialType, string> = {
  pdf: 'PDF',
  image: 'Imagem',
  video: 'Vídeo enviado',
  external_video: 'Vídeo externo',
  link: 'Link',
  document: 'Documento (DOC, DOCX)',
  presentation: 'Apresentação (PPT, PPTX)',
  spreadsheet: 'Planilha (XLS, XLSX)',
  audio: 'Áudio',
  other: 'Outro formato',
}

export const TYPE_ACCEPT: Partial<Record<MaterialType, string>> = {
  pdf: '.pdf',
  image: '.jpg,.jpeg,.png,.webp',
  video: '.mp4,.webm',
  document: '.doc,.docx',
  presentation: '.ppt,.pptx',
  spreadsheet: '.xls,.xlsx',
  audio: '.mp3,.m4a,.ogg,.wav',
}

export const STATUS_LABELS: Record<MaterialStatus, string> = {
  draft: 'Rascunho',
  pending_review: 'Em revisão',
  scheduled: 'Agendado',
  published: 'Publicado',
  archived: 'Arquivado',
}

export const STATUS_STYLES: Record<MaterialStatus, string> = {
  draft: 'bg-muted text-muted-foreground',
  pending_review: 'bg-amber-100 text-amber-900',
  scheduled: 'bg-sky-100 text-sky-900',
  published: 'bg-emerald-100 text-emerald-900',
  archived: 'bg-neutral-200 text-neutral-700',
}

export const VISIBILITY_LABELS: Record<MaterialVisibility, string> = {
  all: 'Todos os alunos',
  restricted: 'Turmas selecionadas',
  staff: 'Somente equipe',
}

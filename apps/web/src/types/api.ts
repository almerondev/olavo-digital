export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  status: 'active' | 'pending' | 'blocked'
  avatar_urls: Record<string, string>
  is_student: boolean
  preferences: Record<string, unknown>
  last_login_at: string | null
  roles: string[]
  permissions: string[]
}

export interface Role {
  id: number
  slug: string
  name: string
  description: string | null
  is_system: boolean
  users_count: number
  permissions: string[]
}

export interface Permission {
  slug: string
  module: string
  description: string
}

export type PlatformConfig = Record<string, unknown>


export interface Upload {
  id: number
  uuid: string
  status: 'pending' | 'processing' | 'ready' | 'rejected'
  rejection_reason: string | null
  original_name: string
  file_class: string
  mime_type: string | null
  size_bytes: number | null
  width: number | null
  height: number | null
  image_urls: Record<string, string>
}

export interface CategoryType {
  id: number
  slug: string
  name: string
  is_system: boolean
}

export interface Category {
  id: number
  name: string
  slug: string
  category_type_id: number
  parent_id: number | null
  description: string | null
  icon: string | null
  cover: Upload | null
  sort_order: number
  is_active: boolean
  visibility: 'public' | 'restricted' | 'hidden'
  materials_count: number
  teacher_ids: number[]
  group_ids: number[]
}

export type MaterialStatus = 'draft' | 'pending_review' | 'scheduled' | 'published' | 'archived'
export type MaterialVisibility = 'all' | 'restricted' | 'staff'
export type MaterialType =
  | 'pdf' | 'image' | 'video' | 'external_video' | 'link' | 'document' | 'presentation' | 'spreadsheet' | 'audio' | 'other'

export interface Material {
  id: number
  slug: string
  title: string
  description: string | null
  type: MaterialType
  status: MaterialStatus
  visibility: MaterialVisibility
  teacher: { id: number; name: string } | null
  cover: Upload | null
  banner: Upload | null
  file: Upload | null
  subtitle: Upload | null
  external_url: string | null
  publish_at: string | null
  published_at: string | null
  allow_download: boolean
  is_featured: boolean
  review_note: string | null
  categories: { id: number; name: string; is_primary: boolean }[]
  tags: string[]
  collaborator_ids?: number[]
  group_ids?: number[]
  created_at: string
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; total: number }
}

export interface Option {
  id: number
  name: string
}

export interface MaterialProgress {
  percent: number
  remaining_seconds: number | null
  completed: boolean
}

export interface CardMaterial {
  id: number
  slug: string
  title: string
  description: string | null
  type: MaterialType
  teacher: string | null
  category: string | null
  image_urls: Record<string, string>
  banner_urls: Record<string, string>
  is_new: boolean
  is_favorite: boolean
  progress: MaterialProgress | null
}

export interface MaterialDetail extends CardMaterial {
  teacher_slug: string | null
  categories: string[]
  tags: string[]
  published_at: string | null
  external_url: string | null
  video_provider: string | null
  allow_download: boolean
  file: { name: string; class: string; size_bytes: number | null } | null
}

export interface HomeHero {
  id: number
  title: string | null
  description: string | null
  cta_label: string | null
  image_urls: Record<string, string>
  material_slug: string | null
  material_type: MaterialType | null
  teacher: string | null
  category: string | null
  external_url: string | null
  preview: { kind: 'file' | 'embed'; url: string } | null
}

export interface SectionPage {
  items: CardMaterial[]
  has_more: boolean
}

export interface HomeSection extends Partial<SectionPage> {
  id: number
  title: string
  description: string | null
  layout: string
}

export interface HomeData {
  hero: HomeHero[]
  sections: HomeSection[]
}

export interface PlaybackSource {
  kind: 'file' | 'hls'
  url: string
  subtitles: { locale: string; label: string; url: string }[]
  resume_at_seconds: number
  completed: boolean
  next: { slug: string; title: string } | null
}

export interface HistoryEntry {
  id: number
  accessed_at: string
  seconds_watched: number
  completed: boolean
  material: CardMaterial
}

export interface ActiveSession {
  id: string
  ip_address: string | null
  user_agent: string | null
  last_activity_at: string
  is_current: boolean
}

export interface BuilderSection {
  id: number
  title: string
  description: string | null
  source_type: string
  category_id: number | null
  teacher_id: number | null
  layout: string
  max_items: number
  sort_order: number
  is_active: boolean
  starts_at: string | null
  ends_at: string | null
  materials: { id: number; title: string }[]
  group_ids: number[]
}

export interface BuilderBanner {
  id: number
  title: string | null
  description: string | null
  cta_label: string | null
  image: Upload | null
  material: { id: number; title: string } | null
  external_url: string | null
  sort_order: number
  is_active: boolean
  starts_at: string | null
  ends_at: string | null
  group_ids: number[]
}

export interface BuilderLayout {
  id: number
  surface: 'home' | 'materials'
  status: 'draft' | 'published' | 'archived'
  published_at: string | null
  sections: BuilderSection[]
  banners: BuilderBanner[]
}

export interface LayoutVersion {
  id: number
  status: 'published' | 'archived'
  published_at: string
}

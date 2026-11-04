export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  status: 'active' | 'pending' | 'blocked'
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
  file: Upload | null
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

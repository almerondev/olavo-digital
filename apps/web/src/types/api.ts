export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  status: 'active' | 'pending' | 'blocked'
  avatar_urls: Record<string, string>
  is_student: boolean
  terms_pending: boolean
  deletion_requested_at: string | null
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
  course_id: number | null
  course_name: string | null
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
  views_count?: number
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
  embed_url: string | null
  allow_download: boolean
  file: { name: string; class: string; size_bytes: number | null } | null
}

export interface HomeHero {
  id: number | string
  is_live: boolean
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
  announcements: Announcement[]
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

export interface TeacherCard {
  id: number
  slug: string
  name: string
  short_bio: string | null
  photo_urls: Record<string, string>
  subjects: string[]
}

export interface TeacherSocial {
  network: string
  url: string
}

export interface TeacherProfile extends TeacherCard {
  bio: string | null
  specialties: string[]
  banner_urls: Record<string, string>
  socials: TeacherSocial[]
}

export interface TeacherAdmin extends TeacherProfile {
  email: string | null
  status: 'active' | 'inactive'
  publish_mode: 'inherit' | 'immediate' | 'approval'
  show_in_cast: boolean
  photo: Upload | null
  banner: Upload | null
  category_ids: number[]
  materials_count?: number
}

export interface CastProfile {
  teacher: TeacherProfile
  videos: CardMaterial[]
  specials: CardMaterial[]
  materials: CardMaterial[]
  live: CardMaterial[]
}

export interface TeacherOverview {
  can_publish: boolean
  totals: { published: number; pending_review: number; drafts: number; views: number; students: number }
  top_materials: { id: number; title: string; status: MaterialStatus; views: number }[]
}

export type ScheduleStatus = 'scheduled' | 'live' | 'ended' | 'cancelled'

export interface LiveScheduleItem {
  id: number
  title: string
  description: string | null
  teacher: { id: number; name: string; slug: string } | null
  category: { id: number; name: string } | null
  cover_urls: Record<string, string>
  starts_at: string
  ends_at: string
  status: ScheduleStatus
  is_adhoc: boolean
  notify_on_start: boolean
  youtube_url?: string | null
  course: { id: number; name: string } | null
  group_ids?: number[]
}

export interface TvNow {
  schedule_enabled: boolean
  live: { stream_id: number; started_at: string; embed_url: string; schedule: LiveScheduleItem } | null
  upcoming: LiveScheduleItem[]
  recordings: CardMaterial[]
}

export interface LiveStreamItem {
  id: number
  title: string
  course: string | null
  youtube_url: string | null
  started_at: string
  ended_at: string | null
  material: { id: number; status: MaterialStatus } | null
}

export interface Course {
  id: number
  name: string
  slug: string
  description: string | null
  is_active: boolean
  categories_count: number
  students_count: number
}

export type DisplayMode = 'banner' | 'card' | 'section'

export interface Announcement {
  id: number
  title: string
  body: string
  image_urls: Record<string, string>
  display_mode: DisplayMode
  priority: number
  is_pinned: boolean
  requires_ack: boolean
  read_at: string | null
  published_at: string
}

export interface AdminAnnouncement {
  id: number
  title: string
  body: string
  image: Upload | null
  display_mode: DisplayMode
  priority: number
  course: { id: number; name: string } | null
  published_at: string
  expires_at: string | null
  is_pinned: boolean
  requires_ack: boolean
  send_notification: boolean
  notified_at: string | null
  reads_count: number
  group_ids: number[]
}

export interface AppNotification {
  id: number
  type: string
  title: string
  body: string
  url: string | null
  image_urls: Record<string, string>
  read_at: string | null
  created_at: string
}

export interface SentNotification {
  id: number
  type: string
  title: string
  body: string
  sender: string | null
  recipients_count: number
  read_count: number
  created_at: string
}

export interface SearchResult {
  total: number
  groups: { key: string; label: string; items: CardMaterial[] }[]
  teachers: TeacherCard[]
}

export interface StudentSummary {
  id: number
  user_id: number
  name: string
  email: string
  phone: string | null
  status: 'active' | 'pending' | 'blocked'
  enrollment_code: string | null
  courses: string[]
  groups: string[]
  created_at: string
  last_login_at: string | null
  deletion_requested_at: string | null
}

export interface StudentDetail extends StudentSummary {
  birth_date: string | null
  guardian_name: string | null
  guardian_contact: string | null
  course_ids: number[]
  group_ids: number[]
  terms_accepted_at: string | null
  stats: { accesses: number; completed: number; favorites: number }
  history: { id: number; title: string; slug: string; started_at: string; seconds_watched: number }[]
}

export interface StudentGroupItem {
  id: number
  name: string
  slug: string
  kind: 'turma' | 'grupo'
  year: number | null
  is_active: boolean
  students_count: number
}

export interface DashboardData {
  totals: {
    students: number
    active_students: number
    recent_students: number
    teachers: number
    published_materials: number
    videos: number
    views: number
    live_streams: number
  }
  views_by_day: { day: string; views: number }[]
  students_by_course: { name: string; students: number }[]
  recent_students: { id: number; name: string; last_login_at: string }[]
  top_materials: { id: number; title: string; views: number }[]
  latest_materials: { id: number; title: string; teacher: string | null; published_at: string | null }[]
  recent_activity: { id: number; action: string; actor: string | null; created_at: string }[]
}

export interface AuditEntry {
  id: number
  action: string
  actor: string | null
  subject: string | null
  changes: Record<string, unknown> | null
  ip_address: string | null
  created_at: string
}

export interface PlatformSettings {
  name: string
  tagline: string | null
  footer: string | null
  brand_color: string | null
  socials: Record<string, string | null>
  terms: string | null
  privacy: string | null
  auto_material: boolean
  auto_live: boolean
  logo_upload_id: number | null
  logo_urls: Record<string, string>
  legal_version: string | null
  environment: { storage: string; mail: string; queue: string; push: boolean }
}

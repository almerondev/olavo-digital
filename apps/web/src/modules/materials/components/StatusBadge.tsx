import { cn } from '@/lib/utils'
import type { MaterialStatus } from '@/types/api'
import { STATUS_LABELS, STATUS_STYLES } from '../labels'

export function StatusBadge({ status }: { status: MaterialStatus }) {
  return (
    <span className={cn('inline-block rounded px-2 py-0.5 text-xs font-semibold whitespace-nowrap', STATUS_STYLES[status])}>
      {STATUS_LABELS[status]}
    </span>
  )
}

import { ref, type Ref } from 'vue'
import {
  isStaffDirectorySortable,
  type StaffDirectoryColumnKey,
  type StaffDirectorySortDir,
} from '@/lib/staffDirectoryColumns'

export function useStaffTableSort(defaultBy: StaffDirectoryColumnKey = 'firstname') {
  const sortBy = ref<StaffDirectoryColumnKey>(defaultBy)
  const sortDir = ref<StaffDirectorySortDir>('asc')

  function toggleSort(column: StaffDirectoryColumnKey, onChange?: () => void) {
    if (!isStaffDirectorySortable(column)) return
    if (sortBy.value === column) {
      sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
      sortBy.value = column
      sortDir.value = 'asc'
    }
    onChange?.()
  }

  function sortIcon(column: StaffDirectoryColumnKey): string {
    if (!isStaffDirectorySortable(column)) return ''
    if (sortBy.value !== column) return 'fa-sort'
    return sortDir.value === 'asc' ? 'fa-sort-up' : 'fa-sort-down'
  }

  function sortParams() {
    return {
      sort_by: sortBy.value,
      sort_dir: sortDir.value,
    }
  }

  return {
    sortBy,
    sortDir,
    toggleSort,
    sortIcon,
    sortParams,
    isSortable: isStaffDirectorySortable,
  }
}

/** Client-side sort for small in-memory staff tables (birthdays, data quality, etc.). */
export function useClientTableSort<T>(
  rows: Ref<T[]>,
  accessors: Record<string, (row: T) => string | number | null | undefined>,
  defaultBy: string,
) {
  const sortBy = ref(defaultBy)
  const sortDir = ref<StaffDirectorySortDir>('asc')

  function toggleSort(column: string) {
    if (!(column in accessors)) return
    if (sortBy.value === column) {
      sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
      sortBy.value = column
      sortDir.value = 'asc'
    }
  }

  function sortIcon(column: string): string {
    if (!(column in accessors)) return ''
    if (sortBy.value !== column) return 'fa-sort'
    return sortDir.value === 'asc' ? 'fa-sort-up' : 'fa-sort-down'
  }

  function sortedRows(): T[] {
    const key = sortBy.value in accessors ? sortBy.value : defaultBy
    const accessor = accessors[key]
    const dir = sortDir.value === 'desc' ? -1 : 1
    return [...rows.value].sort((a, b) => {
      const av = accessor(a)
      const bv = accessor(b)
      if (av == null && bv == null) return 0
      if (av == null) return 1
      if (bv == null) return -1
      if (typeof av === 'number' && typeof bv === 'number') {
        return (av - bv) * dir
      }
      return String(av).localeCompare(String(bv), undefined, { sensitivity: 'base' }) * dir
    })
  }

  return { sortBy, sortDir, toggleSort, sortIcon, sortedRows }
}

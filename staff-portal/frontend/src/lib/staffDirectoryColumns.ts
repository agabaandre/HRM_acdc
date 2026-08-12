export const STAFF_DIRECTORY_COLUMNS_STORAGE_KEY = 'staff-portal.staff-directory.columns.v1'

export type StaffDirectoryColumnKey =
  | 'photo'
  | 'name'
  | 'work_email'
  | 'sap_number'
  | 'job'
  | 'division'
  | 'duty_station'
  | 'contract_type'
  | 'category'
  | 'status'
  | 'grade'
  | 'start_date'
  | 'end_date'
  | 'funder'
  | 'nationality'

export interface StaffDirectoryColumnDefinition {
  key: StaffDirectoryColumnKey
  label: string
}

export const staffDirectoryColumns: StaffDirectoryColumnDefinition[] = [
  { key: 'photo', label: 'Photo' },
  { key: 'name', label: 'Name' },
  { key: 'work_email', label: 'Work email' },
  { key: 'sap_number', label: 'SAP' },
  { key: 'job', label: 'Job' },
  { key: 'division', label: 'Division' },
  { key: 'duty_station', label: 'Duty station' },
  { key: 'contract_type', label: 'Contract type' },
  { key: 'category', label: 'Category' },
  { key: 'status', label: 'Status' },
  { key: 'grade', label: 'Grade' },
  { key: 'start_date', label: 'Start date' },
  { key: 'end_date', label: 'End date' },
  { key: 'funder', label: 'Funder' },
  { key: 'nationality', label: 'Nationality' },
]

export const defaultStaffDirectoryColumns: StaffDirectoryColumnKey[] = [
  'photo',
  'name',
  'work_email',
  'job',
  'division',
  'duty_station',
  'contract_type',
  'status',
  'end_date',
]

const validColumnKeys = new Set<StaffDirectoryColumnKey>(staffDirectoryColumns.map((column) => column.key))

export function normalizeStaffDirectoryColumns(value: unknown): StaffDirectoryColumnKey[] {
  if (!Array.isArray(value)) {
    return [...defaultStaffDirectoryColumns]
  }

  const unique = value.filter((key): key is StaffDirectoryColumnKey => validColumnKeys.has(key as StaffDirectoryColumnKey))
  return unique.length > 0 ? Array.from(new Set(unique)) : [...defaultStaffDirectoryColumns]
}

export function loadStaffDirectoryColumns(): StaffDirectoryColumnKey[] {
  try {
    const raw = window.localStorage.getItem(STAFF_DIRECTORY_COLUMNS_STORAGE_KEY)
    return normalizeStaffDirectoryColumns(raw ? JSON.parse(raw) : null)
  } catch {
    return [...defaultStaffDirectoryColumns]
  }
}

export function saveStaffDirectoryColumns(columns: StaffDirectoryColumnKey[]): void {
  const normalized = normalizeStaffDirectoryColumns(columns)
  window.localStorage.setItem(STAFF_DIRECTORY_COLUMNS_STORAGE_KEY, JSON.stringify(normalized))
}

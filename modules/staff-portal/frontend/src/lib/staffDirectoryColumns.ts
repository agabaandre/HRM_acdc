export const STAFF_DIRECTORY_COLUMNS_STORAGE_KEY = 'staff-portal.staff-directory.columns.v3'

export type StaffDirectoryColumnKey =
  | 'sap_number'
  | 'title'
  | 'photo'
  | 'firstname'
  | 'surname'
  | 'othernames'
  | 'gender'
  | 'date_of_birth'
  | 'age'
  | 'nationality'
  | 'region'
  | 'duty_station'
  | 'division'
  | 'grade'
  | 'job'
  | 'initiation_date'
  | 'start_date'
  | 'end_date'
  | 'years_of_tenure'
  | 'job_acting'
  | 'first_supervisor'
  | 'second_supervisor'
  | 'funder'
  | 'work_email'
  | 'telephone'
  | 'whatsapp'
  | 'contract_type'
  | 'category'
  | 'status'

export interface StaffDirectoryColumnDefinition {
  key: StaffDirectoryColumnKey
  label: string
}

/** Column catalog — CI3 all_staff order first, then optional extras. */
export const staffDirectoryColumns: StaffDirectoryColumnDefinition[] = [
  { key: 'sap_number', label: 'SAPNO' },
  { key: 'photo', label: 'Passport Photo' },
  { key: 'title', label: 'Title' },
  { key: 'firstname', label: 'Firstname' },
  { key: 'surname', label: 'Surname' },
  { key: 'othernames', label: 'Othernames' },
  { key: 'gender', label: 'Gender' },
  { key: 'date_of_birth', label: 'Date of Birth' },
  { key: 'age', label: 'Age' },
  { key: 'nationality', label: 'Nationality' },
  { key: 'region', label: 'Region' },
  { key: 'duty_station', label: 'Duty Station' },
  { key: 'division', label: 'Division' },
  { key: 'grade', label: 'Grade' },
  { key: 'job', label: 'Job' },
  { key: 'initiation_date', label: 'Initiation Date' },
  { key: 'start_date', label: 'Current Contract Start Date' },
  { key: 'end_date', label: 'Current Contract End Date' },
  { key: 'years_of_tenure', label: 'Years of Tenure' },
  { key: 'job_acting', label: 'Acting Job' },
  { key: 'first_supervisor', label: 'First Supervisor' },
  { key: 'second_supervisor', label: 'Second Supervisor' },
  { key: 'funder', label: 'Funder' },
  { key: 'work_email', label: 'Email' },
  { key: 'telephone', label: 'Telephone' },
  { key: 'whatsapp', label: 'WhatsApp' },
  { key: 'contract_type', label: 'Contract type' },
  { key: 'category', label: 'Category' },
  { key: 'status', label: 'Status' },
]

/** Defaults match CI3 `/staff/all_staff` table columns (name split into parts). */
export const defaultStaffDirectoryColumns: StaffDirectoryColumnKey[] = [
  'sap_number',
  'photo',
  'title',
  'firstname',
  'surname',
  'othernames',
  'gender',
  'date_of_birth',
  'age',
  'nationality',
  'region',
  'duty_station',
  'division',
  'grade',
  'job',
  'initiation_date',
  'start_date',
  'end_date',
  'years_of_tenure',
  'job_acting',
  'first_supervisor',
  'second_supervisor',
  'funder',
  'work_email',
  'telephone',
  'whatsapp',
]

const validColumnKeys = new Set<StaffDirectoryColumnKey>(staffDirectoryColumns.map((column) => column.key))

const namePartKeys: StaffDirectoryColumnKey[] = ['firstname', 'surname', 'othernames']

export function normalizeStaffDirectoryColumns(value: unknown): StaffDirectoryColumnKey[] {
  if (!Array.isArray(value)) {
    return [...defaultStaffDirectoryColumns]
  }

  const expanded: StaffDirectoryColumnKey[] = []
  for (const key of value) {
    // Migrate legacy combined "name" column from v1/v2 storage.
    if (key === 'name') {
      for (const part of namePartKeys) {
        if (!expanded.includes(part)) expanded.push(part)
      }
      continue
    }
    if (validColumnKeys.has(key as StaffDirectoryColumnKey)) {
      const typed = key as StaffDirectoryColumnKey
      if (!expanded.includes(typed)) expanded.push(typed)
    }
  }

  return expanded.length > 0 ? expanded : [...defaultStaffDirectoryColumns]
}

export function loadStaffDirectoryColumns(): StaffDirectoryColumnKey[] {
  try {
    const raw = window.localStorage.getItem(STAFF_DIRECTORY_COLUMNS_STORAGE_KEY)
    if (raw) {
      return normalizeStaffDirectoryColumns(JSON.parse(raw))
    }
    // One-time migrate from v2 key if present.
    const legacy = window.localStorage.getItem('staff-portal.staff-directory.columns.v2')
    if (legacy) {
      const migrated = normalizeStaffDirectoryColumns(JSON.parse(legacy))
      saveStaffDirectoryColumns(migrated)
      return migrated
    }
    return [...defaultStaffDirectoryColumns]
  } catch {
    return [...defaultStaffDirectoryColumns]
  }
}

export function saveStaffDirectoryColumns(columns: StaffDirectoryColumnKey[]): void {
  const normalized = normalizeStaffDirectoryColumns(columns)
  window.localStorage.setItem(STAFF_DIRECTORY_COLUMNS_STORAGE_KEY, JSON.stringify(normalized))
}

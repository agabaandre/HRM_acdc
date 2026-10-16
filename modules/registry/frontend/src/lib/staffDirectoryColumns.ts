export const STAFF_DIRECTORY_COLUMNS_STORAGE_KEY = 'staff-portal.staff-directory.columns.v2'

export type StaffDirectoryColumnKey =
  | 'sap_number'
  | 'title'
  | 'photo'
  | 'name'
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
  { key: 'title', label: 'Title' },
  { key: 'photo', label: 'Passport Photo' },
  { key: 'name', label: 'Name' },
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

/** Defaults match CI3 `/staff/all_staff` table columns. */
export const defaultStaffDirectoryColumns: StaffDirectoryColumnKey[] = [
  'sap_number',
  'title',
  'photo',
  'name',
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

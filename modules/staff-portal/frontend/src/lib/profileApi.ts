import { api } from './api'

export interface KinRelationshipType {
  id: number
  name: string
}

export interface NextOfKinRow {
  name: string
  relationship_id: number | string | ''
  phone: string
  email: string
}

export interface MyProfileStaff {
  staff_id: number
  SAPNO?: string | null
  title?: string | null
  fname?: string | null
  lname?: string | null
  oname?: string | null
  gender?: string | null
  date_of_birth?: string | null
  nationality?: string | null
  region_name?: string | null
  work_email?: string | null
  private_email?: string | null
  whatsapp?: string | null
  tel_1?: string | null
  tel_2?: string | null
  langauge?: string | null
  physical_location?: string | null
  residential_address_duty_station?: string | null
  number_of_dependants?: number | null
  initiation_date?: string | null
  photo?: string | null
  signature?: string | null
  passport_biodata_page?: string | null
  next_of_kin: NextOfKinRow[]
}

export interface MyProfilePayload {
  staff: MyProfileStaff
  contract: Record<string, string | null> | null
  supervisors: {
    first: { name: string } | null
    second: { name: string } | null
  }
  media: {
    photo_url?: string | null
    signature_url?: string | null
    passport_url?: string | null
    passport_is_pdf?: boolean
  }
  lookups: {
    kin_relationship_types: KinRelationshipType[]
    languages?: Array<{ code: string; name: string; flag: string }>
  }
  flags: {
    allow_email_login: boolean
    password_login_available: boolean
  }
}

export interface UpdateMyProfileInput {
  private_email: string
  whatsapp?: string | null
  tel_1: string
  tel_2?: string | null
  langauge?: string | null
  residential_address_duty_station: string
  number_of_dependants: number
  next_of_kin: NextOfKinRow[]
}

export async function fetchMyProfile(): Promise<MyProfilePayload> {
  const { data } = await api.get('/api/v1/me/profile')
  return data.data as MyProfilePayload
}

export async function updateMyProfile(payload: UpdateMyProfileInput): Promise<MyProfilePayload> {
  const { data } = await api.put('/api/v1/me/profile', payload)
  return data.data as MyProfilePayload
}

async function postMultipart(path: string, body: FormData): Promise<MyProfilePayload> {
  // Do not set Content-Type — the browser must supply multipart boundary.
  const { data } = await api.post(path, body, {
    headers: { 'Content-Type': undefined },
    transformRequest: [
      (payload, headers) => {
        if (payload instanceof FormData && headers && typeof headers === 'object') {
          const h = headers as Record<string, unknown>
          delete h['Content-Type']
          delete h['content-type']
        }
        return payload
      },
    ],
  })
  return data.data as MyProfilePayload
}

export async function uploadMyPhoto(file: File): Promise<MyProfilePayload> {
  const body = new FormData()
  body.append('photo', file)
  return postMultipart('/api/v1/me/profile/photo', body)
}

export async function uploadMyPassport(file: File): Promise<MyProfilePayload> {
  const body = new FormData()
  body.append('passport', file)
  return postMultipart('/api/v1/me/profile/passport', body)
}

export async function uploadMySignatureFile(file: File): Promise<MyProfilePayload> {
  const body = new FormData()
  body.append('signature', file)
  return postMultipart('/api/v1/me/profile/signature', body)
}

export async function uploadMySignatureDataUrl(dataUrl: string): Promise<MyProfilePayload> {
  const { data } = await api.post('/api/v1/me/profile/signature', { data_url: dataUrl })
  return data.data as MyProfilePayload
}

export async function changeMyPassword(payload: {
  current_password: string
  password: string
  password_confirmation: string
}): Promise<void> {
  await api.put('/api/v1/me/password', payload)
}

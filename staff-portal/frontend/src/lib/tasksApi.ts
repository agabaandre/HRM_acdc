import { api } from './api'

export interface TasksHubLink {
  to: string
  label: string
  description?: string
  icon?: string
  count?: number | null
  permission?: number
}

export interface TasksHubSummary {
  total: number
  pending: number
  overdue: number
  execution_rate: number
  completed?: number
}

export interface TasksHubData {
  summary: TasksHubSummary
  links: TasksHubLink[]
}

export interface WeeklyTaskRow {
  activity_id: number
  staff_id?: string | number | null
  staff_name?: string | null
  work_planner_tasks_id?: number | null
  activity_name?: string | null
  week?: string | null
  start_date?: string | null
  end_date?: string | null
  status?: number | null
  status_label?: string | null
  overdue?: boolean
  comments?: string | null
  workplan_id?: number | null
  workplan_activity?: string | null
  specific_activity?: string | null
  planner_activity?: string | null
  pra_activity_id?: number | null
  division_id?: number | null
  division_name?: string | null
  division_short_name?: string | null
  [key: string]: unknown
}

export interface WeeklySpecificActivity {
  activity_id: number
  activity_name: string
  workplan_id?: number | null
  workplan_activity?: string | null
  pra_activity_id?: number | null
  year?: string | number | null
}

export interface WeeklyStats {
  total: number
  pending: number
  completed: number
  carried_forward: number
  cancelled: number
  overdue: number
  execution_rate: number
}

export interface WeeklyTasksResponse {
  data: WeeklyTaskRow[]
  meta: {
    division_id: number
    financial_year?: string | number
    source?: string
    ingested_from?: string
    staff: Array<{ staff_id: number; fname?: string | null; lname?: string | null }>
    specific_activities: WeeklySpecificActivity[]
    status_options: Array<{ value: number; title: string }>
    stats: WeeklyStats
    divisions: Array<{
      division_id: number
      division_name: string
      division_short_name?: string | null
    }>
  }
}

export interface WeeklyTaskFilters {
  division_id?: number | null
  staff_id?: number | null
  status?: number | null
  start_date?: string | null
  end_date?: string | null
  work_planner_tasks_id?: number | null
  q?: string | null
}

export async function fetchTasksHub(): Promise<TasksHubData> {
  const { data } = await api.get<{ data: TasksHubData }>('/api/v1/tasks/hub')
  return {
    summary: data.data.summary ?? {
      total: 0,
      pending: 0,
      overdue: 0,
      execution_rate: 0,
    },
    links: data.data.links || [],
  }
}

export async function fetchWeeklyTasks(params: WeeklyTaskFilters = {}): Promise<WeeklyTasksResponse> {
  const { data } = await api.get<WeeklyTasksResponse>('/api/v1/tasks/weekly', {
    params: {
      division_id: params.division_id || undefined,
      staff_id: params.staff_id || undefined,
      status: params.status || undefined,
      start_date: params.start_date || undefined,
      end_date: params.end_date || undefined,
      work_planner_tasks_id: params.work_planner_tasks_id || undefined,
      q: params.q || undefined,
    },
  })
  return data
}

export async function createWeeklyTasks(payload: {
  work_planner_tasks_id: number
  start_date: string
  end_date: string
  staff_ids?: number[]
  activities: Array<{ activity_name: string; comments?: string }>
}): Promise<{ message: string; data: { saved: number } }> {
  const { data } = await api.post<{ message: string; data: { saved: number } }>(
    '/api/v1/tasks/weekly',
    payload,
  )
  return data
}

export async function updateWeeklyTask(
  id: number,
  payload: {
    activity_name: string
    comments?: string
    status: number
    staff_ids?: number[]
  },
): Promise<{ message: string }> {
  const { data } = await api.put<{ message: string }>(`/api/v1/tasks/weekly/${id}`, payload)
  return data
}

import type { PerformanceObjective } from '@/lib/performanceApi'

export type EndtermScoreCategory = 'outstanding' | 'satisfactory' | 'poor' | 'not_rated'

export interface EndtermOverallRating {
  score: number
  category: EndtermScoreCategory
  label: string
  annotation: string
}

/** CI3: sum(appraiser rating × weight) / 5. */
export function calculateEndtermOverallRating(
  objectives: Record<number | string, PerformanceObjective | undefined> | null | undefined,
): EndtermOverallRating {
  let total = 0

  for (const row of Object.values(objectives || {})) {
    if (!row) {
      continue
    }
    const rating = Number(row.appraiser_rating || 0)
    const weight = Number(row.weight || 0)
    if (rating > 0 && weight > 0) {
      total += rating * weight
    }
  }

  const score = total > 0 ? Math.round((total / 5) * 100) / 100 : 0

  if (score >= 80) {
    return {
      score,
      category: 'outstanding',
      label: 'Outstanding Performance',
      annotation:
        'Outstanding Performance - Overall performance is superior and significantly exceeds expectations',
    }
  }
  if (score >= 51) {
    return {
      score,
      category: 'satisfactory',
      label: 'Satisfactory Performance',
      annotation: 'Satisfactory Performance - Overall performance is consistent with expectations',
    }
  }
  if (score > 0) {
    return {
      score,
      category: 'poor',
      label: 'Poor Performance',
      annotation: 'Poor Performance - Overall Performance fails to meet the expectations',
    }
  }

  return {
    score: 0,
    category: 'not_rated',
    label: 'Not Rated – New in Position',
    annotation: 'Not Rated – New in Position',
  }
}

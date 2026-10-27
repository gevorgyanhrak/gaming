export type FieldErrors = Record<string, string[]>

/**
 * A failed API call, normalized from Laravel's JSON error format
 * (`{ message, errors? }`). `status` is 0 when the server could not be reached.
 */
export class ApiError extends Error {
  readonly status: number
  readonly fieldErrors: FieldErrors

  constructor(status: number, message: string, fieldErrors: FieldErrors = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.fieldErrors = fieldErrors
  }

  get isNetworkError(): boolean {
    return this.status === 0
  }

  get isNotFound(): boolean {
    return this.status === 404
  }

  static fromResponse(status: number, payload: unknown): ApiError {
    const body = isRecord(payload) ? payload : {}
    const message = typeof body.message === 'string' ? body.message : `Request failed (${status})`
    const errors = isRecord(body.errors) ? toFieldErrors(body.errors) : {}

    return new ApiError(status, message, errors)
  }
}

export function isApiError(error: unknown): error is ApiError {
  return error instanceof ApiError
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function toFieldErrors(errors: Record<string, unknown>): FieldErrors {
  return Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [
      field,
      Array.isArray(messages) ? messages.filter((m): m is string => typeof m === 'string') : [],
    ]),
  )
}

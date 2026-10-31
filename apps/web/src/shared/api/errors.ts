export type FieldErrors = Record<string, string[]>

/**
 * A failed API call, normalized from Laravel's JSON error format
 * (`{ message, errors?, code? }`). `status` is 0 when the server could not be reached. `code` names
 * outcomes the client reacts to (e.g. "insufficient_fishkas"); `body` keeps the rest of the payload.
 */
export class ApiError extends Error {
  readonly status: number
  readonly fieldErrors: FieldErrors
  readonly code: string | null
  readonly body: Record<string, unknown>

  constructor(
    status: number,
    message: string,
    fieldErrors: FieldErrors = {},
    code: string | null = null,
    body: Record<string, unknown> = {},
  ) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.fieldErrors = fieldErrors
    this.code = code
    this.body = body
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

    const code = typeof body.code === 'string' ? body.code : null

    return new ApiError(status, message, errors, code, body)
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

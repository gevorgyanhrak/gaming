import type { components, operations } from './schema'

type JsonBody<Response> = Response extends { content: { 'application/json': infer Body } }
  ? Body
  : never

/** Successful (2xx) JSON response body of a generated API operation. */
export type ApiResponse<Operation extends keyof operations> =
  operations[Operation]['responses'] extends infer Responses
    ? Responses extends { 200: infer R }
      ? JsonBody<R>
      : Responses extends { 201: infer R }
        ? JsonBody<R>
        : Responses extends { 202: infer R }
          ? JsonBody<R>
          : never
    : never

/** JSON request body of a generated API operation. */
export type ApiBody<Operation extends keyof operations> = operations[Operation] extends {
  requestBody?: { content: { 'application/json': infer Body } }
}
  ? Body
  : never

export type Schemas = components['schemas']

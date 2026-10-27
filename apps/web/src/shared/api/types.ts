import type { components, operations } from './schema'

/** Response body of a generated API operation (200 JSON). */
export type ApiResponse<Operation extends keyof operations> = operations[Operation] extends {
  responses: { 200: { content: { 'application/json': infer Body } } }
}
  ? Body
  : never

export type Schemas = components['schemas']

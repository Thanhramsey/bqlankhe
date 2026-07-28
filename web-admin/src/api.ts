const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1'

export type ApiResponse<T> = { success: boolean; message: string; data: T }

export async function api<T>(path: string, options: RequestInit = {}): Promise<ApiResponse<T>> {
  const token = localStorage.getItem('token')
  const response = await fetch(`${API_URL}${path}`, {
    ...options,
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}), ...options.headers },
  })
  const result = await response.json()
  if (!response.ok) throw new Error(result.message || 'Không thể kết nối máy chủ')
  return result
}

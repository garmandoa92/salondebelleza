import { ref } from 'vue'
import axios from 'axios'

export function useDiagnosis(base) {
  const diagnosis = ref(null)
  const loading = ref(false)
  const editing = ref(false)

  const load = async (appointmentId) => {
    loading.value = true
    try {
      const { data } = await axios.get(`${base}/agenda/appointments/${appointmentId}/diagnosis`)
      diagnosis.value = data
    } catch {
      diagnosis.value = null
    } finally {
      loading.value = false
    }
  }

  const save = async (appointmentId, formData) => {
    const method = diagnosis.value ? 'put' : 'post'
    const { data } = await axios[method](`${base}/agenda/appointments/${appointmentId}/diagnosis`, formData)
    diagnosis.value = data
    editing.value = false
    return data
  }

  return { diagnosis, loading, editing, load, save }
}

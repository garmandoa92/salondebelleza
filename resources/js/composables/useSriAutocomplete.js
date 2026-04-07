import { ref } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'

export function useSriAutocomplete() {
    const page = usePage()
    const tenantId = page.props.tenant?.id
    const base = `/salon/${tenantId}`

    const loading = ref(false)
    const status = ref(null)   // null | 'authorized' | 'partial' | 'invalid' | 'error'
    const message = ref('')
    const items = ref([])
    let debounceTimer = null

    async function queryByAccessKey(clave, form) {
        if (clave.length !== 49) return

        // Validar dígito verificador localmente (sin llamada)
        if (!verifyCheckDigit(clave)) {
            status.value = 'invalid'
            message.value = 'Clave de acceso inválida — verifica los dígitos'
            return
        }

        loading.value = true
        status.value = null
        items.value = []

        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(async () => {
            try {
                const { data } = await axios.get(`${base}/gastos/consultar-sri`, {
                    params: { clave }
                })

                status.value = data.status
                message.value = data.message

                // Autocompletar campos del formulario
                if (data.ruc) form.supplier_ruc = data.ruc
                if (data.supplier_name) form.supplier_name = data.supplier_name
                if (data.invoice_number) form.sri_invoice_number = data.invoice_number
                if (data.numero_autorizacion) form.sri_authorization_number = data.numero_autorizacion
                if (data.fecha_emision) form.expense_date = data.fecha_emision
                if (data.subtotal) form.amount = data.subtotal
                if (data.iva !== null && data.iva !== undefined) form.iva_amount = data.iva

                items.value = data.items || []

            } catch (e) {
                status.value = 'error'
                message.value = e.response?.data?.message ?? 'Error al consultar el SRI'
            } finally {
                loading.value = false
            }
        }, 300)
    }

    // Validación local del dígito verificador (módulo 11)
    function verifyCheckDigit(clave) {
        const digits = clave.slice(0, 48).split('').map(Number)
        const coefs = [2, 3, 4, 5, 6, 7]
        let suma = 0
        digits.reverse().forEach((d, i) => { suma += d * coefs[i % 6] })
        const residuo = suma % 11
        const verificador = residuo === 0 ? 0 : residuo === 1 ? 1 : 11 - residuo
        return verificador === Number(clave[48])
    }

    function reset() {
        loading.value = false
        status.value = null
        message.value = ''
        items.value = []
    }

    return { loading, status, message, items, queryByAccessKey, reset }
}

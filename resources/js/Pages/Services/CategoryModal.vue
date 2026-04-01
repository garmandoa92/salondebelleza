<script setup>
import { ref } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'

const props = defineProps({
  category: { type: Object, default: null },
})

const emit = defineEmits(['close'])
const page = usePage()
const tenantId = page.props.tenant?.id

const form = useForm({
  name: props.category?.name || '',
  color: props.category?.color || '#3B82F6',
})

const submit = () => {
  if (props.category) {
    form.put(`/salon/${tenantId}/categorias/${props.category.id}`, {
      onSuccess: () => emit('close'),
    })
  } else {
    form.post(`/salon/${tenantId}/categorias`, {
      onSuccess: () => emit('close'),
    })
  }
}

const deleteCategory = () => {
  if (confirm(`Eliminar "${props.category.name}"?`)) {
    router.delete(`/salon/${tenantId}/categorias/${props.category.id}`, {
      onSuccess: () => emit('close'),
    })
  }
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="emit('close')">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 space-y-4">
      <h2 class="text-lg font-semibold">
        {{ category ? 'Editar categoria' : 'Nueva categoria' }}
      </h2>

      <form @submit.prevent="submit" class="space-y-4">
        <div class="space-y-2">
          <Label for="name">Nombre</Label>
          <Input id="name" v-model="form.name" required />
          <p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p>
        </div>

        <div class="space-y-2">
          <Label for="color">Color</Label>
          <div class="flex items-center gap-3">
            <input
              id="color"
              type="color"
              v-model="form.color"
              class="h-10 w-14 rounded border cursor-pointer"
            />
            <Badge :style="{ backgroundColor: form.color, color: 'white' }">
              {{ form.name || 'Preview' }}
            </Badge>
          </div>
          <p v-if="form.errors.color" class="text-sm text-red-500">{{ form.errors.color }}</p>
        </div>

        <div class="flex justify-between pt-2">
          <div>
            <Button
              v-if="category"
              type="button"
              variant="ghost"
              class="text-red-600"
              @click="deleteCategory"
            >
              Eliminar
            </Button>
          </div>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="emit('close')">Cancelar</Button>
            <Button type="submit" :disabled="form.processing">
              {{ category ? 'Guardar' : 'Crear' }}
            </Button>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

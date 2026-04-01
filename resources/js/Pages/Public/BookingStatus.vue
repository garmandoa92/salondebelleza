<script setup>
import { Head } from '@inertiajs/vue3'
import PublicLayout from '@/Layouts/PublicLayout.vue'

defineOptions({ layout: PublicLayout })

defineProps({
  status: String,
  message: String,
  appointment: { type: Object, default: null },
})
</script>

<template>
  <Head :title="status === 'confirmed' ? 'Cita confirmada' : 'Estado de cita'" />

  <div class="max-w-md mx-auto px-4 py-16 text-center space-y-4">
    <div
      class="inline-flex items-center justify-center w-16 h-16 rounded-full"
      :class="status === 'confirmed' ? 'bg-green-100' : status === 'cancelled' ? 'bg-red-100' : 'bg-gray-100'"
    >
      <svg v-if="status === 'confirmed'" class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
      </svg>
      <svg v-else class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </div>

    <h1 class="text-xl font-bold text-gray-900">{{ message }}</h1>

    <div v-if="appointment" class="text-sm text-gray-600">
      <p>{{ appointment.service?.name }} con {{ appointment.stylist?.name }}</p>
    </div>
  </div>
</template>

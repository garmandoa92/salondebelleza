<script setup>
import { Head } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

defineProps({
  tenant: Object,
})
</script>

<template>
  <Head title="Dashboard" />

  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
      <p class="text-sm text-gray-500">Bienvenido a {{ tenant?.name }}</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium text-gray-500">Citas hoy</CardTitle>
        </CardHeader>
        <CardContent>
          <p class="text-3xl font-bold">0</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium text-gray-500">Clientes</CardTitle>
        </CardHeader>
        <CardContent>
          <p class="text-3xl font-bold">0</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium text-gray-500">Ventas del mes</CardTitle>
        </CardHeader>
        <CardContent>
          <p class="text-3xl font-bold">$0</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="pb-2">
          <CardTitle class="text-sm font-medium text-gray-500">Estilistas activos</CardTitle>
        </CardHeader>
        <CardContent>
          <p class="text-3xl font-bold">0</p>
        </CardContent>
      </Card>
    </div>
  </div>
</template>

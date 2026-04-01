<script setup>
import { Head } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import AuthLayout from '@/Layouts/AuthLayout.vue'

defineOptions({ layout: AuthLayout })

defineProps({
  tenant: Object,
})
</script>

<template>
  <Head title="Actualizar plan" />

  <Card>
    <CardHeader class="text-center">
      <CardTitle>Tu periodo de prueba ha terminado</CardTitle>
      <CardDescription>Elige un plan para continuar usando {{ tenant?.name }}</CardDescription>
    </CardHeader>
    <CardContent class="space-y-4">
      <div class="grid gap-3">
        <div class="border rounded-lg p-4 hover:border-primary cursor-pointer transition-colors">
          <div class="flex justify-between items-center">
            <div>
              <h3 class="font-medium">Basico</h3>
              <p class="text-sm text-gray-500">2 estilistas, 1 sucursal</p>
            </div>
            <p class="text-lg font-bold">$15<span class="text-sm font-normal text-gray-500">/mes</span></p>
          </div>
        </div>

        <div class="border-2 border-primary rounded-lg p-4 relative">
          <span class="absolute -top-2.5 left-4 bg-primary text-white text-xs px-2 py-0.5 rounded-full">Recomendado</span>
          <div class="flex justify-between items-center">
            <div>
              <h3 class="font-medium">Profesional</h3>
              <p class="text-sm text-gray-500">8 estilistas, 1 sucursal</p>
            </div>
            <p class="text-lg font-bold">$29<span class="text-sm font-normal text-gray-500">/mes</span></p>
          </div>
        </div>

        <div class="border rounded-lg p-4 hover:border-primary cursor-pointer transition-colors">
          <div class="flex justify-between items-center">
            <div>
              <h3 class="font-medium">Cadena</h3>
              <p class="text-sm text-gray-500">Estilistas y sucursales ilimitados</p>
            </div>
            <p class="text-lg font-bold">$59<span class="text-sm font-normal text-gray-500">/mes</span></p>
          </div>
        </div>
      </div>

      <Button class="w-full">Seleccionar plan</Button>
      <p class="text-xs text-center text-gray-400">La integracion con Stripe se configurara en la Sesion 10</p>
    </CardContent>
  </Card>
</template>

<script setup>
import { Link, Head } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import AuthLayout from '@/Layouts/AuthLayout.vue'

defineOptions({ layout: AuthLayout })
</script>

<template>
  <Head title="Bienvenido" />

  <div class="space-y-6 text-center">
    <h2 class="text-xl font-semibold text-gray-900">Bienvenido a Salon SaaS</h2>
    <p class="text-gray-600">La plataforma completa para gestionar tu salon de belleza en Ecuador.</p>

    <div class="space-y-3">
      <Link :href="route('register')" class="block">
        <Button class="w-full">Crear mi salon</Button>
      </Link>
      <Link :href="route('login')" class="block">
        <Button variant="outline" class="w-full">Iniciar sesion</Button>
      </Link>
    </div>

    <div class="pt-4 border-t border-gray-200">
      <p class="text-xs text-gray-400">30 dias de prueba gratis. Sin tarjeta de credito.</p>
    </div>
  </div>
</template>

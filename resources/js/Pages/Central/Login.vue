<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import AuthLayout from '@/Layouts/AuthLayout.vue'

defineOptions({ layout: AuthLayout })

const form = useForm({
  email: '',
  password: '',
})

const submit = () => {
  form.post(route('login'))
}
</script>

<template>
  <Head title="Iniciar sesion" />

  <Card>
    <CardHeader>
      <CardTitle>Iniciar sesion</CardTitle>
      <CardDescription>Accede al panel de tu salon</CardDescription>
    </CardHeader>
    <CardContent>
      <form @submit.prevent="submit" class="space-y-4">
        <div class="space-y-2">
          <Label for="email">Email</Label>
          <Input id="email" v-model="form.email" type="email" placeholder="juan@ejemplo.com" required autofocus />
          <p v-if="form.errors.email" class="text-sm text-red-500">{{ form.errors.email }}</p>
        </div>

        <div class="space-y-2">
          <Label for="password">Contrasena</Label>
          <Input id="password" v-model="form.password" type="password" required />
          <p v-if="form.errors.password" class="text-sm text-red-500">{{ form.errors.password }}</p>
        </div>

        <Button type="submit" class="w-full" :disabled="form.processing">
          {{ form.processing ? 'Ingresando...' : 'Ingresar' }}
        </Button>

        <p class="text-center text-sm text-gray-500">
          No tienes cuenta?
          <Link :href="route('register')" class="text-primary hover:underline">Registrate gratis</Link>
        </p>
      </form>
    </CardContent>
  </Card>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import AuthLayout from '@/Layouts/AuthLayout.vue'

defineOptions({ layout: AuthLayout })

const form = useForm({
  salon_name: '',
  slug: '',
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
})

const generateSlug = () => {
  form.slug = form.salon_name
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
}

const submit = () => {
  form.post(route('register'))
}
</script>

<template>
  <Head title="Registrar salon" />

  <Card>
    <CardHeader>
      <CardTitle>Crea tu salon</CardTitle>
      <CardDescription>30 dias gratis en plan Profesional</CardDescription>
    </CardHeader>
    <CardContent>
      <form @submit.prevent="submit" class="space-y-4">
        <div class="space-y-2">
          <Label for="salon_name">Nombre del salon</Label>
          <Input
            id="salon_name"
            v-model="form.salon_name"
            @input="generateSlug"
            placeholder="Mi Salon de Belleza"
            required
          />
          <p v-if="form.errors.salon_name" class="text-sm text-red-500">{{ form.errors.salon_name }}</p>
        </div>

        <div class="space-y-2">
          <Label for="slug">Subdominio</Label>
          <div class="flex items-center">
            <Input
              id="slug"
              v-model="form.slug"
              class="rounded-r-none"
              placeholder="mi-salon"
              required
            />
            <span class="inline-flex items-center px-3 h-9 border border-l-0 border-input rounded-r-md bg-muted text-sm text-muted-foreground">
              .miapp.test
            </span>
          </div>
          <p v-if="form.errors.slug" class="text-sm text-red-500">{{ form.errors.slug }}</p>
        </div>

        <div class="space-y-2">
          <Label for="name">Tu nombre completo</Label>
          <Input id="name" v-model="form.name" placeholder="Juan Perez" required />
          <p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p>
        </div>

        <div class="space-y-2">
          <Label for="email">Email</Label>
          <Input id="email" v-model="form.email" type="email" placeholder="juan@ejemplo.com" required />
          <p v-if="form.errors.email" class="text-sm text-red-500">{{ form.errors.email }}</p>
        </div>

        <div class="space-y-2">
          <Label for="phone">Telefono</Label>
          <Input id="phone" v-model="form.phone" type="tel" placeholder="0991234567" required />
          <p v-if="form.errors.phone" class="text-sm text-red-500">{{ form.errors.phone }}</p>
        </div>

        <div class="space-y-2">
          <Label for="password">Contrasena</Label>
          <Input id="password" v-model="form.password" type="password" required />
          <p v-if="form.errors.password" class="text-sm text-red-500">{{ form.errors.password }}</p>
        </div>

        <div class="space-y-2">
          <Label for="password_confirmation">Confirmar contrasena</Label>
          <Input id="password_confirmation" v-model="form.password_confirmation" type="password" required />
        </div>

        <Button type="submit" class="w-full" :disabled="form.processing">
          {{ form.processing ? 'Creando...' : 'Crear salon gratis' }}
        </Button>
      </form>
    </CardContent>
  </Card>
</template>

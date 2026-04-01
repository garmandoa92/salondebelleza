<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import AuthLayout from '@/Layouts/AuthLayout.vue'

defineOptions({ layout: AuthLayout })

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

const page = usePage()
const tenantId = page.props.tenant?.id

const submit = () => {
  form.post(`/salon/${tenantId}/login`)
}
</script>

<template>
  <Head title="Login" />

  <Card>
    <CardHeader>
      <CardTitle>Acceso al salon</CardTitle>
      <CardDescription>Ingresa tus credenciales</CardDescription>
    </CardHeader>
    <CardContent>
      <form @submit.prevent="submit" class="space-y-4">
        <div class="space-y-2">
          <Label for="email">Email</Label>
          <Input id="email" v-model="form.email" type="email" required autofocus />
          <p v-if="form.errors.email" class="text-sm text-red-500">{{ form.errors.email }}</p>
        </div>

        <div class="space-y-2">
          <Label for="password">Contrasena</Label>
          <Input id="password" v-model="form.password" type="password" required />
        </div>

        <Button type="submit" class="w-full" :disabled="form.processing">
          {{ form.processing ? 'Ingresando...' : 'Ingresar' }}
        </Button>
      </form>
    </CardContent>
  </Card>
</template>

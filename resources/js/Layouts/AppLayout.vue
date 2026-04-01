<script setup>
import { ref, computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet'

const page = usePage()
const sidebarOpen = ref(true)
const mobileOpen = ref(false)

const user = computed(() => page.props.auth?.user)
const tenant = computed(() => page.props.tenant)

const initials = computed(() => {
  if (!user.value?.name) return '?'
  return user.value.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
})

const basePath = computed(() => `/salon/${tenant.value?.id}`)

const navigation = computed(() => [
  { name: 'Dashboard', href: `${basePath.value}/dashboard`, icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { name: 'Agenda', href: `${basePath.value}/agenda`, icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
  { name: 'Clientes', href: `${basePath.value}/clientes`, icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
  { name: 'Servicios', href: `${basePath.value}/servicios`, icon: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z' },
  { name: 'Estilistas', href: `${basePath.value}/estilistas`, icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
  { name: 'Inventario', href: `${basePath.value}/inventario`, icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
  { name: 'Reportes', href: `${basePath.value}/reportes`, icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
  { name: 'Configuracion', href: `${basePath.value}/configuracion`, icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' },
])

const logout = () => {
  router.post(`${basePath.value}/logout`)
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Desktop Sidebar -->
    <aside
      :class="[
        'fixed inset-y-0 left-0 z-50 bg-white border-r border-gray-200 transition-all duration-300 hidden lg:flex flex-col',
        sidebarOpen ? 'w-64' : 'w-20'
      ]"
    >
      <div class="flex items-center justify-between h-16 px-4 border-b">
        <span v-if="sidebarOpen" class="text-lg font-bold text-gray-900 truncate">
          {{ tenant?.name || 'Salon SaaS' }}
        </span>
        <Button variant="ghost" size="icon" @click="sidebarOpen = !sidebarOpen">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </Button>
      </div>

      <nav class="flex-1 px-2 py-4 space-y-1 overflow-y-auto">
        <Link
          v-for="item in navigation"
          :key="item.name"
          :href="item.href"
          :class="[
            'flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors',
            $page.url.startsWith(item.href)
              ? 'bg-primary/10 text-primary'
              : 'text-gray-700 hover:bg-gray-100'
          ]"
        >
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
          </svg>
          <span v-if="sidebarOpen" class="ml-3 truncate">{{ item.name }}</span>
        </Link>
      </nav>
    </aside>

    <!-- Mobile bottom navigation -->
    <nav class="fixed bottom-0 inset-x-0 z-50 bg-white border-t border-gray-200 lg:hidden">
      <div class="flex justify-around py-2">
        <Link
          v-for="item in navigation.slice(0, 5)"
          :key="item.name"
          :href="item.href"
          :class="[
            'flex flex-col items-center px-2 py-1 text-xs',
            $page.url.startsWith(item.href) ? 'text-primary' : 'text-gray-500'
          ]"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
          </svg>
          <span class="mt-1">{{ item.name }}</span>
        </Link>
      </div>
    </nav>

    <!-- Main content -->
    <div :class="['transition-all duration-300', sidebarOpen ? 'lg:pl-64' : 'lg:pl-20']">
      <!-- Topbar -->
      <header class="sticky top-0 z-40 bg-white border-b border-gray-200">
        <div class="flex items-center justify-between h-16 px-4 sm:px-6">
          <!-- Mobile menu button -->
          <Sheet v-model:open="mobileOpen">
            <SheetTrigger as-child>
              <Button variant="ghost" size="icon" class="lg:hidden">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
              </Button>
            </SheetTrigger>
            <SheetContent side="left" class="w-64 p-0">
              <div class="flex items-center h-16 px-4 border-b">
                <span class="text-lg font-bold">{{ tenant?.name || 'Salon SaaS' }}</span>
              </div>
              <nav class="px-2 py-4 space-y-1">
                <Link
                  v-for="item in navigation"
                  :key="item.name"
                  :href="item.href"
                  @click="mobileOpen = false"
                  :class="[
                    'flex items-center px-3 py-2 rounded-lg text-sm font-medium',
                    $page.url.startsWith(item.href)
                      ? 'bg-primary/10 text-primary'
                      : 'text-gray-700 hover:bg-gray-100'
                  ]"
                >
                  <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                  </svg>
                  {{ item.name }}
                </Link>
              </nav>
            </SheetContent>
          </Sheet>

          <div class="flex-1" />

          <div class="flex items-center gap-3">
            <!-- Notifications -->
            <Button variant="ghost" size="icon" class="relative">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
              <Badge class="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center p-0 text-[10px]">0</Badge>
            </Button>

            <!-- User menu -->
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="ghost" class="flex items-center gap-2">
                  <Avatar class="h-8 w-8">
                    <AvatarFallback class="text-xs">{{ initials }}</AvatarFallback>
                  </Avatar>
                  <span class="hidden sm:block text-sm font-medium">{{ user?.name }}</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" class="w-48">
                <DropdownMenuItem>Perfil</DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem @click="logout" class="text-red-600">
                  Cerrar sesion
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
      </header>

      <!-- Page content -->
      <main class="p-4 sm:p-6 pb-20 lg:pb-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, watchEffect } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const tenant = computed(() => page.props.tenant)
const settings = computed(() => page.props.settings || {})

watchEffect(() => {
  const c = page.props.themeColors
  if (!c) return
  const root = document.documentElement
  root.style.setProperty('--color-primary', c.primary)
  root.style.setProperty('--color-accent', c.accent)
  // HSL for shadcn
  let r = parseInt(c.primary.slice(1, 3), 16) / 255, g = parseInt(c.primary.slice(3, 5), 16) / 255, b = parseInt(c.primary.slice(5, 7), 16) / 255
  const max = Math.max(r, g, b), min = Math.min(r, g, b)
  let h = 0, s = 0, l = (max + min) / 2
  if (max !== min) { const d = max - min; s = l > 0.5 ? d / (2 - max - min) : d / (max + min); if (max === r) h = ((g - b) / d + (g < b ? 6 : 0)) / 6; else if (max === g) h = ((b - r) / d + 2) / 6; else h = ((r - g) / d + 4) / 6 }
  root.style.setProperty('--primary', `${Math.round(h * 360)} ${Math.round(s * 100)}% ${Math.round(l * 100)}%`)
  root.style.setProperty('--primary-foreground', '0 0% 100%')
})
</script>

<template>
  <div class="min-h-screen bg-[#FAFBFA]">
    <!-- Header -->
    <header class="bg-white border-b border-gray-100 sticky top-0 z-10">
      <div class="max-w-lg mx-auto px-4 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm" style="background-color: var(--color-primary);">
            {{ (tenant?.name || 'S')[0] }}
          </div>
          <div>
            <p class="font-semibold text-[15px] text-gray-900">{{ tenant?.name || 'Salon' }}</p>
            <p v-if="tenant?.address" class="text-[11px] text-gray-500">{{ tenant.address }}</p>
          </div>
        </div>
        <a v-if="tenant?.phone" :href="`tel:${tenant.phone}`" class="flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-full border border-gray-200 text-gray-600 hover:bg-gray-50">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
          Llamar
        </a>
      </div>
    </header>

    <!-- Content -->
    <main>
      <slot />
    </main>

    <!-- Footer -->
    <footer class="border-t border-gray-100 bg-white mt-12">
      <div class="max-w-lg mx-auto px-4 py-6 text-center space-y-2">
        <p class="text-xs text-gray-400">{{ tenant?.name }} · {{ tenant?.address }}</p>
        <div class="flex items-center justify-center gap-4 text-xs text-gray-400">
          <a v-if="tenant?.phone" :href="`https://wa.me/593${tenant.phone?.replace(/^0/, '')}`" target="_blank" class="hover:text-gray-600">WhatsApp</a>
          <a v-if="tenant?.phone" :href="`tel:${tenant.phone}`" class="hover:text-gray-600">{{ tenant.phone }}</a>
        </div>
      </div>
    </footer>
  </div>
</template>

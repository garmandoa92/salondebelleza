<script setup>
defineEmits(['close'])

const shortcuts = [
  { key: 'N', desc: 'Nueva cita' },
  { key: 'T', desc: 'Ir a hoy' },
  { key: 'D', desc: 'Vista dia' },
  { key: 'W', desc: 'Vista semana' },
  { key: 'M', desc: 'Vista mes' },
  { key: '<-/->', desc: 'Dia anterior/siguiente' },
  { key: 'Esc', desc: 'Cerrar panel/modal' },
  { key: '?', desc: 'Mostrar atajos' },
]
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="$emit('close')">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">
      <h2 class="text-lg font-semibold mb-4">Atajos de teclado</h2>
      <div class="space-y-2">
        <div v-for="s in shortcuts" :key="s.key" class="flex justify-between text-sm">
          <kbd class="px-2 py-0.5 bg-gray-100 rounded text-xs font-mono">{{ s.key }}</kbd>
          <span class="text-gray-600">{{ s.desc }}</span>
        </div>
      </div>
      <button @click="$emit('close')" class="mt-4 w-full text-center text-sm text-gray-500 hover:text-gray-700">Cerrar</button>
    </div>
  </div>
</template>

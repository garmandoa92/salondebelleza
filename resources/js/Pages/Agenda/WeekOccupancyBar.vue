<script setup>
defineProps({
  weekOccupancy: { type: Array, default: () => [] },
})
defineEmits(['navigate'])
</script>

<template>
  <div class="flex gap-1 px-2 py-2 border-b border-gray-200 bg-white">
    <div
      v-for="day in weekOccupancy"
      :key="day.date"
      @click="$emit('navigate', day.date)"
      :title="`${day.label}: ${day.pct}% ocupado (${day.count} citas)`"
      class="flex-1 cursor-pointer flex flex-col items-center gap-0.5"
    >
      <div class="w-full h-8 bg-gray-100 rounded relative overflow-hidden">
        <div
          class="absolute bottom-0 w-full transition-all duration-300"
          :style="{
            height: day.pct + '%',
            background: day.pct > 85 ? '#ef4444' : day.pct > 60 ? '#f59e0b' : '#22c55e',
          }"
        />
      </div>
      <span class="text-[10px] text-gray-500">{{ day.shortName }}</span>
      <span class="text-[10px] font-semibold">{{ day.pct }}%</span>
    </div>
  </div>
</template>

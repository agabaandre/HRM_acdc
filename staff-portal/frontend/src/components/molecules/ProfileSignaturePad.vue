<script setup lang="ts">
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue'

const emit = defineEmits<{
  saveDataUrl: [dataUrl: string]
  saveFile: [file: File]
}>()

const mode = ref<'draw' | 'type'>('draw')
const typedName = ref('')
const canvasRef = ref<HTMLCanvasElement | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const drawing = ref(false)
const busy = ref(false)

let ctx: CanvasRenderingContext2D | null = null

function setupCanvas() {
  const canvas = canvasRef.value
  if (!canvas) return
  const ratio = window.devicePixelRatio || 1
  const width = canvas.clientWidth || 480
  const height = 160
  canvas.width = Math.floor(width * ratio)
  canvas.height = Math.floor(height * ratio)
  ctx = canvas.getContext('2d')
  if (!ctx) return
  ctx.setTransform(ratio, 0, 0, ratio, 0, 0)
  ctx.lineWidth = 2
  ctx.lineCap = 'round'
  ctx.strokeStyle = '#1a237e'
  clearCanvas()
}

function clearCanvas() {
  const canvas = canvasRef.value
  if (!canvas || !ctx) return
  ctx.fillStyle = '#ffffff'
  ctx.fillRect(0, 0, canvas.clientWidth || 480, 160)
}

function pointerPos(e: PointerEvent) {
  const canvas = canvasRef.value
  if (!canvas) return { x: 0, y: 0 }
  const rect = canvas.getBoundingClientRect()
  return { x: e.clientX - rect.left, y: e.clientY - rect.top }
}

function onPointerDown(e: PointerEvent) {
  if (mode.value !== 'draw' || !ctx) return
  drawing.value = true
  canvasRef.value?.setPointerCapture(e.pointerId)
  const { x, y } = pointerPos(e)
  ctx.beginPath()
  ctx.moveTo(x, y)
}

function onPointerMove(e: PointerEvent) {
  if (!drawing.value || !ctx) return
  const { x, y } = pointerPos(e)
  ctx.lineTo(x, y)
  ctx.stroke()
}

function onPointerUp(e: PointerEvent) {
  drawing.value = false
  canvasRef.value?.releasePointerCapture(e.pointerId)
}

function renderTyped() {
  if (!ctx || !canvasRef.value) return
  clearCanvas()
  const text = typedName.value.trim()
  if (!text) return
  ctx.fillStyle = '#1a237e'
  ctx.font = '48px "Segoe Script", "Brush Script MT", cursive'
  ctx.textBaseline = 'middle'
  ctx.fillText(text, 24, 80)
}

async function saveDrawnOrTyped() {
  busy.value = true
  try {
    if (mode.value === 'type') {
      renderTyped()
    }
    await nextTick()
    const canvas = canvasRef.value
    if (!canvas) return
    emit('saveDataUrl', canvas.toDataURL('image/png'))
  } finally {
    busy.value = false
  }
}

function onFile(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (file) emit('saveFile', file)
  input.value = ''
}

function clear() {
  typedName.value = ''
  clearCanvas()
}

watch(mode, async () => {
  await nextTick()
  setupCanvas()
})

watch(typedName, () => {
  if (mode.value === 'type') renderTyped()
})

onMounted(() => {
  setupCanvas()
  window.addEventListener('resize', setupCanvas)
})

onUnmounted(() => {
  window.removeEventListener('resize', setupCanvas)
})
</script>

<template>
  <div class="profile-signature-pad">
    <v-btn-toggle v-model="mode" mandatory density="compact" color="primary" class="mb-2">
      <v-btn value="draw" size="small">Draw</v-btn>
      <v-btn value="type" size="small">Type</v-btn>
    </v-btn-toggle>

    <v-text-field
      v-if="mode === 'type'"
      v-model="typedName"
      label="Type your name"
      placeholder=" "
      class="mb-2"
    />

    <canvas
      ref="canvasRef"
      class="profile-signature-pad__canvas"
      @pointerdown="onPointerDown"
      @pointermove="onPointerMove"
      @pointerup="onPointerUp"
      @pointerleave="onPointerUp"
    />

    <div class="d-flex flex-wrap ga-2 mt-2">
      <input
        ref="fileInput"
        type="file"
        accept="image/png,image/jpeg,image/jpg,image/gif,image/webp"
        class="d-none"
        @change="onFile"
      />
      <v-btn size="small" variant="outlined" @click="clear">Clear</v-btn>
      <v-btn size="small" color="primary" :loading="busy" @click="saveDrawnOrTyped">
        Save signature
      </v-btn>
      <v-btn size="small" variant="tonal" @click="fileInput?.click()">Upload image</v-btn>
    </div>
  </div>
</template>

<style scoped>
.profile-signature-pad__canvas {
  display: block;
  width: 100%;
  height: 160px;
  border: 1px solid #b0bec5;
  border-radius: 4px;
  background: #fff;
  touch-action: none;
  cursor: crosshair;
}
</style>

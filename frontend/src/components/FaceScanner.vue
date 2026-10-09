<template>
  <div class="face-scanner-container">
    <div class="scanner-card" :class="mode">
      <div class="card-header">
        <h2 v-if="mode === 'admin'">Yangi xodimni ro'yxatga olish</h2>
        <h2 v-if="mode === 'checkpoint'">Shaxsni identifikatsiya qilish</h2>
      </div>

      <div class="video-wrapper">
        <!-- Hidden video element -->
        <video ref="videoElement" class="hidden-video" autoplay playsinline muted></video>
        <!-- Canvas for drawing video + bounding box -->
        <canvas ref="canvasElement" class="display-canvas"></canvas>
        
        <!-- Loading overlay -->
        <div v-if="isLoading" class="overlay">
          <div class="spinner"></div>
          <p>Kamera ulanmoqda...</p>
        </div>
      </div>

      <transition name="fade">
        <div class="actions-panel" v-if="isCameraOn">
          
          <!-- ADMIN MODE -->
          <div v-if="mode === 'admin'" class="action-card register-card">
            <div class="input-group">
              <label>Xodimning ism-familiyasi</label>
              <input 
                v-model="fullName" 
                placeholder="Familiya Ism" 
                class="modern-input"
                @keyup.enter="register"
              />
            </div>
            <button @click="register" :disabled="!canProcess" class="btn action-btn">
              {{ isProcessing ? 'Saqlanmoqda...' : 'Saqlash' }}
            </button>
          </div>
          
          <!-- CHECKPOINT MODE -->
          <div v-if="mode === 'checkpoint'" class="action-card recognize-card">
            <p class="subtitle">Kameraga qarang. Tizim avtomatik tekshiradi.</p>
            <div v-if="isProcessing" class="auto-scan-indicator">
               <div class="spinner small"></div> Tekshirilmoqda...
            </div>
          </div>

        </div>
      </transition>
    </div>

    <transition name="toast">
      <div v-if="message" :class="['toast-message', messageType]">
        <div class="toast-content">
          <span v-if="messageType === 'success'" class="toast-icon">✅</span>
          <span v-if="messageType === 'error'" class="toast-icon">❌</span>
          {{ message }}
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  mode: {
    type: String,
    required: true
  }
});

// Get MediaPipe from the global window object (loaded via CDN in index.html)
const FaceDetection = window.FaceDetection;
const Camera = window.Camera;

// Component state
const videoElement = ref(null);
const canvasElement = ref(null);
const isCameraOn = ref(false);
const isLoading = ref(false);
const isFaceDetected = ref(false);
const isProcessing = ref(false);
const fullName = ref('');
const message = ref('');
const messageType = ref('');

// Internal references
let camera = null;
let faceDetection = null;
let currentBoundingBox = null;
let recognitionCooldown = false;
let messageTimeout = null;
const API_BASE_URL = 'http://localhost:8000/api';

const canProcess = computed(() => isFaceDetected.value && !isProcessing.value && !isLoading.value);

const initMediaPipe = () => {
  faceDetection = new FaceDetection({
    locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/face_detection/${file}`
  });

  faceDetection.setOptions({
    model: 'short',
    minDetectionConfidence: 0.7
  });

  faceDetection.onResults(onResults);
};

const startCamera = async () => {
  isLoading.value = true;
  if (!faceDetection) initMediaPipe();

  if (videoElement.value) {
    camera = new Camera(videoElement.value, {
      onFrame: async () => {
        if (videoElement.value && faceDetection) {
          await faceDetection.send({ image: videoElement.value });
        }
      },
      width: 640,
      height: 480
    });
    
    try {
      await camera.start();
      isCameraOn.value = true;
    } catch (err) {
      showMessage("Kameraga ulanishda xatolik yuz berdi.", 'error');
    } finally {
      isLoading.value = false;
    }
  }
};

const stopCamera = () => {
  if (camera) {
    camera.stop();
    camera = null;
  }
  isCameraOn.value = false;
  isFaceDetected.value = false;
  currentBoundingBox = null;
  
  if (canvasElement.value) {
    const ctx = canvasElement.value.getContext('2d');
    ctx.clearRect(0, 0, canvasElement.value.width, canvasElement.value.height);
  }
};

const onResults = (results) => {
  const canvas = canvasElement.value;
  const video = videoElement.value;
  
  if (!canvas || !video) return;

  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  const ctx = canvas.getContext('2d');
  
  // Draw the video frame on the canvas and mirror it
  ctx.save();
  ctx.scale(-1, 1);
  ctx.translate(-canvas.width, 0);
  ctx.drawImage(results.image, 0, 0, canvas.width, canvas.height);
  
  if (results.detections.length > 0) {
    isFaceDetected.value = true;
    const detection = results.detections[0]; // Take primary face
    currentBoundingBox = detection.boundingBox;

    // Draw standard simple bounding box
    const width = currentBoundingBox.width * canvas.width;
    const height = currentBoundingBox.height * canvas.height;
    const x = currentBoundingBox.xCenter * canvas.width - width / 2;
    const y = currentBoundingBox.yCenter * canvas.height - height / 2;

    ctx.strokeStyle = props.mode === 'admin' ? '#3b82f6' : '#10b981';
    ctx.lineWidth = 2;
    
    ctx.beginPath();
    ctx.rect(x, y, width, height);
    ctx.stroke();

    // AUTO-RECOGNIZE LOGIC for Checkpoint mode
    if (props.mode === 'checkpoint' && isFaceDetected.value && !isProcessing.value && !recognitionCooldown) {
      recognize();
    }
  } else {
    isFaceDetected.value = false;
    currentBoundingBox = null;
    recognitionCooldown = false; // Reset instantly when face leaves the camera
  }
  ctx.restore();
};

const cropFace = async () => {
  if (!currentBoundingBox || !videoElement.value) return null;

  const video = videoElement.value;
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d');

  const width = currentBoundingBox.width * video.videoWidth;
  const height = currentBoundingBox.height * video.videoHeight;
  const x = currentBoundingBox.xCenter * video.videoWidth - width / 2;
  const y = currentBoundingBox.yCenter * video.videoHeight - height / 2;

  // Add 150% padding to capture full head shape and shoulders so the AI detector doesn't fail
  const paddingX = width * 1.5;
  const paddingY = height * 1.5;

  const cropX = Math.max(0, x - paddingX);
  const cropY = Math.max(0, y - paddingY);
  const cropWidth = Math.min(video.videoWidth - cropX, width + paddingX * 2);
  const cropHeight = Math.min(video.videoHeight - cropY, height + paddingY * 2);

  canvas.width = cropWidth;
  canvas.height = cropHeight;

  // Draw unmirrored crop for the backend
  ctx.drawImage(
    video,
    cropX, cropY, cropWidth, cropHeight,
    0, 0, cropWidth, cropHeight
  );

  return new Promise((resolve) => {
    canvas.toBlob((blob) => resolve(blob), 'image/jpeg', 0.95);
  });
};

const register = async () => {
  if (!fullName.value.trim()) {
    showMessage('Iltimos xodim ismini kiriting', 'error');
    return;
  }

  isProcessing.value = true;
  
  try {
    const faceBlob = await cropFace();
    if (!faceBlob) throw new Error("Yuz aniqlanmadi, kameraga to'g'ri qarang.");

    const formData = new FormData();
    formData.append('file', faceBlob, 'face.jpg');
    formData.append('full_name', fullName.value);

    const response = await axios.post(`${API_BASE_URL}/register`, formData);
    showMessage(`Muvaffaqiyatli kiritildi! ID: ${response.data.id}`, 'success');
    fullName.value = '';
  } catch (error) {
    showMessage(error.response?.data?.detail || error.message, 'error');
  } finally {
    isProcessing.value = false;
  }
};

const recognize = async () => {
  isProcessing.value = true;
  recognitionCooldown = true; // Prevent spamming while the face is still in frame
  
  try {
    const faceBlob = await cropFace();
    if (!faceBlob) throw new Error("Yuz aniqlanmadi, kameraga to'g'ri qarang.");

    const formData = new FormData();
    formData.append('file', faceBlob, 'face.jpg');

    const response = await axios.post(`${API_BASE_URL}/recognize`, formData);
    
    if (response.data.match) {
      const p = response.data.person;
      showMessage(`Tasdiqlandi: ${p.full_name}`, 'success');
    } else {
      showMessage('Ruxsat etilmadi: Shaxs topilmadi.', 'error');
    }
  } catch (error) {
    showMessage(error.response?.data?.detail || error.message, 'error');
  } finally {
    isProcessing.value = false;
    // Release the cooldown after 2 seconds if the person is still standing there.
    // If they move away earlier, the `onResults` else-block clears it instantly.
    setTimeout(() => { recognitionCooldown = false; }, 2000);
  }
};

const showMessage = (msg, type) => {
  message.value = msg;
  messageType.value = type;
  if (messageTimeout) clearTimeout(messageTimeout);
  messageTimeout = setTimeout(() => { message.value = ''; }, 2500); // Faster auto dismiss
};

onMounted(() => {
  startCamera();
});

onUnmounted(() => {
  stopCamera();
});
</script>

<style scoped>
.face-scanner-container {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.scanner-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 2.5rem;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
  width: 100%;
  max-width: 800px;
}

.card-header {
  text-align: center;
  margin-bottom: 2rem;
}

.card-header h2 {
  color: #0f172a;
  margin: 0;
  font-size: 1.75rem;
  font-weight: 600;
  letter-spacing: -0.5px;
}

.video-wrapper {
  position: relative;
  width: 100%;
  max-width: 640px;
  margin: 0 auto;
  aspect-ratio: 4/3;
  background: #0f172a;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: inset 0 0 20px rgba(0,0,0,0.5);
}

.hidden-video {
  display: none;
}

.display-canvas {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(4px);
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  color: #334155;
  font-weight: 500;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid rgba(59, 130, 246, 0.2);
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.controls-panel {
  display: flex;
  justify-content: center;
  margin-top: 1.5rem;
}

.btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
}

.primary-btn {
  background: #3b82f6;
  color: #fff;
  box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.2);
}

.primary-btn:hover {
  background: #2563eb;
  transform: translateY(-1px);
}

.danger-btn {
  background: #ef4444;
  color: white;
  box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
}

.danger-btn:hover {
  background: #dc2626;
  transform: translateY(-1px);
}

.success-btn {
  background: #10b981;
  color: #fff;
  box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
}

.success-btn:hover {
  background: #059669;
  transform: translateY(-1px);
}

.actions-panel {
  margin-top: 2rem;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.action-card {
  background: #f8fafc;
  padding: 2rem;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  text-align: center;
}

.input-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  text-align: left;
  margin-bottom: 1.5rem;
  max-width: 400px;
  margin-left: auto;
  margin-right: auto;
}

.input-group label {
  font-size: 0.95rem;
  color: #475569;
  font-weight: 500;
}

.modern-input {
  padding: 0.875rem 1rem;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 1rem;
  transition: border-color 0.2s, box-shadow 0.2s;
  font-family: inherit;
  background: #ffffff;
  color: #0f172a;
}

.modern-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.action-btn {
  background: #0f172a;
  color: #fff;
  width: 100%;
  max-width: 400px;
  margin: 0 auto;
  justify-content: center;
  box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.2);
}

.action-btn:hover:not(:disabled) {
  background: #1e293b;
  transform: translateY(-1px);
}

.action-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.large-btn {
  padding: 1rem 2rem;
  font-size: 1.1rem;
}

.subtitle {
  color: #64748b;
  margin-bottom: 1.5rem;
  font-size: 1.05rem;
}

.toast-message {
  position: fixed;
  bottom: 2rem;
  right: 2rem;
  padding: 1rem 1.5rem;
  border-radius: 8px;
  font-weight: 500;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
  z-index: 100;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background: #ffffff;
  color: #0f172a;
}

.toast-message.success {
  border-left: 4px solid #10b981;
}

.toast-message.error {
  border-left: 4px solid #ef4444;
}

.toast-content {
  display: flex;
  align-items: center;
  gap: 10px;
}

.toast-enter-active, .toast-leave-active { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px) scale(0.95); }

.auto-scan-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  color: #10b981;
  font-weight: 600;
  margin-top: 1rem;
  background: #f0fdf4;
  padding: 0.875rem;
  border-radius: 8px;
  border: 1px solid #bbf7d0;
}

.spinner.small {
  width: 20px;
  height: 20px;
  border-width: 3px;
  margin-bottom: 0;
}
</style>

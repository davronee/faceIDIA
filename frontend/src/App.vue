<script setup>
import { ref } from 'vue'
import FaceScanner from './components/FaceScanner.vue'

const currentMode = ref('home') // 'home', 'admin', 'checkpoint'
</script>

<template>
  <div class="app-wrapper">
    <!-- Main Content -->
    <main class="main-content">
      <transition name="fade" mode="out-in">
        
        <!-- Home Menu -->
        <div v-if="currentMode === 'home'" class="home-menu">
          <div class="welcome-text">
            <h2>Tizim rejimlari</h2>
            <p>Iltimos, ish rejimini tanlang</p>
          </div>
          
          <div class="modules-grid">
            <!-- Admin Module -->
            <div class="module-card" @click="currentMode = 'admin'">
              <div class="module-icon admin-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                  <path stroke-linecap="round" stroke-linejoin="round" d="M23 21v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75"></path>
                </svg>
              </div>
              <div class="module-info">
                <h3>Ro'yxatga olish</h3>
                <p>Yangi xodimlarni bazaga kiritish</p>
              </div>
            </div>

            <!-- Checkpoint Module -->
            <div class="module-card" @click="currentMode = 'checkpoint'">
              <div class="module-icon checkpoint-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
              </div>
              <div class="module-info">
                <h3>Skaner punkti</h3>
                <p>Xodimlarni avtomatik tanish</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Scanner Modes -->
        <div v-else class="scanner-section">
          <button @click="currentMode = 'home'" class="back-btn">
            &larr; Orqaga qaytish
          </button>
          <FaceScanner :mode="currentMode" />
        </div>
        
      </transition>
    </main>
  </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');

body {
  margin: 0;
  padding: 0;
  background-color: #f8fafc;
  font-family: 'Inter', sans-serif;
  color: #334155;
  overflow-x: hidden;
}

.app-wrapper {
  min-height: 100vh;
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  background: radial-gradient(circle at center, #ffffff 0%, #f1f5f9 100%);
  padding: 2rem 0;
}

/* Main Content */
.main-content {
  width: 100%;
  max-width: 900px;
  padding: 2rem;
  box-sizing: border-box;
}

/* Home Menu */
.home-menu {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.welcome-text {
  text-align: center;
  margin-bottom: 3rem;
}

.welcome-text h2 {
  font-size: 2rem;
  color: #0f172a;
  margin: 0 0 0.5rem 0;
  font-weight: 600;
  letter-spacing: -0.5px;
}

.welcome-text p {
  color: #64748b;
  font-size: 1.1rem;
  margin: 0;
}

.modules-grid {
  display: flex;
  gap: 2rem;
  width: 100%;
}

.module-card {
  flex: 1;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 3rem 2rem;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
}

.module-card:hover {
  transform: translateY(-4px);
  border-color: #cbd5e1;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.module-icon {
  width: 64px;
  height: 64px;
  margin: 0 auto 1.5rem auto;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.module-icon svg {
  width: 32px;
  height: 32px;
}

.admin-icon {
  background: #eff6ff;
  color: #3b82f6;
}

.checkpoint-icon {
  background: #f0fdf4;
  color: #10b981;
}

.module-info h3 {
  font-size: 1.25rem;
  color: #1e293b;
  margin: 0 0 0.5rem 0;
  font-weight: 600;
}

.module-info p {
  color: #64748b;
  margin: 0;
  font-size: 0.95rem;
  line-height: 1.5;
}

.scanner-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
}

.back-btn {
  align-self: flex-start;
  background: #ffffff;
  color: #475569;
  border: 1px solid #e2e8f0;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
  margin-bottom: 1.5rem;
  font-size: 0.95rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.2s;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.back-btn:hover {
  background: #f8fafc;
  color: #0f172a;
  border-color: #cbd5e1;
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(10px);
}
</style>

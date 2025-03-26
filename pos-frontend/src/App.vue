<script setup>
import Sidebar from './components/Sidebar.vue'
import SidebarCashier from './components/Sidebar-cashier.vue'
import { ref, onMounted } from 'vue'
import { useHass } from '../composables/useHass'

const showSidebar = ref(false)
const isAdmin = ref(false)
const { hass, entities, connected, error, connect: reconnect } = useHass()

onMounted(() => {
  // Check admin status directly from localStorage string value
  isAdmin.value = localStorage.getItem('isAdmin') === 'true' 
})

const toggleSidebar = () => {
  showSidebar.value = !showSidebar.value
}

const closeSidebar = () => {
  showSidebar.value = false
}
</script>

<template>
  <div>
    <div v-if="connected" class="hass-status connected">HASS Connected</div>
    <div v-else class="hass-status disconnected">
      HASS Disconnected
      <span v-if="error" class="error-msg">({{ error }})</span>
      <button @click="reconnect" class="retry-btn">Retry</button>
    </div>
    <Sidebar
      v-if="isAdmin"
      :is-visible="showSidebar"
      @close-sidebar="closeSidebar"
    />
    
    <SidebarCashier
      v-if="!isAdmin"
      :is-visible="showSidebar"
      @close-sidebar="closeSidebar"
    />

    <router-view @toggle-sidebar="toggleSidebar" />
  </div>
</template>

<style scoped>
.hass-status {
  position: fixed;
  top: 10px;
  right: 10px;
  padding: 5px 10px;
  border-radius: 4px;
  z-index: 1000;
}
.connected {
  background-color: #4caf50;
  color: white;
}
.disconnected {
  background-color: #f44336;
  color: white;
}
.error-msg {
  font-size: 0.8em;
  margin-left: 5px;
}
.retry-btn {
  margin-left: 10px;
  padding: 2px 8px;
  background: white;
  border: none;
  border-radius: 3px;
  cursor: pointer;
}
</style>
<template>
  <div class="card shadow-sm">
    <div class="card-header bg-white">
      <h5 class="mb-0">Restaurants ({{ restaurants.length }})</h5>
    </div>
    
    <div class="card-body p-0">
      <div v-if="loading" class="text-center p-4">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Loading restaurants...</p>
      </div>
      
      <div v-else-if="restaurants.length === 0" class="text-center p-4">
        <i class="bi bi-search fs-1 text-muted"></i>
        <p class="mt-2">No restaurants found. Try a different search term.</p>
      </div>
      
      <div v-else class="restaurant-list" style="max-height: 600px; overflow-y: auto;">
        <div 
          v-for="restaurant in restaurants" 
          :key="restaurant.id"
          class="restaurant-item border-bottom p-3 cursor-pointer"
          :class="{ 'bg-light': selectedRestaurant?.id === restaurant.id }"
          @click="$emit('select-restaurant', restaurant)"
        >
          <div class="row g-0">
            <div class="col-4">
              <div class="restaurant-image" :style="{ backgroundImage: `url(${restaurant.image})` }"></div>
            </div>
            <div class="col-8 ps-3">
              <h5 class="mb-1">{{ restaurant.name }}</h5>
              <p class="mb-1 small text-muted">
                <i class="bi bi-geo-alt-fill"></i> {{ restaurant.address }}
              </p>
              <div class="d-flex align-items-center mb-1">
                <span class="badge bg-light text-dark me-2">{{ restaurant.cuisine }}</span>
                <small>{{ restaurant.rating }}/5 ⭐</small>
              </div>
              <p class="mb-0 small text-truncate">{{ restaurant.openingHours }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  restaurants: {
    type: Array,
    required: true
  },
  loading: {
    type: Boolean,
    default: false
  },
  selectedRestaurant: {
    type: Object,
    default: null
  }
});

defineEmits(['select-restaurant']);
</script>

<style scoped>
.restaurant-item {
  transition: background-color 0.2s;
}

.restaurant-item:hover {
  background-color: rgba(0, 0, 0, 0.03);
}

.cursor-pointer {
  cursor: pointer;
}

.restaurant-image {
  height: 80px;
  background-size: cover;
  background-position: center;
  border-radius: 4px;
}
</style>
<template>
  <div class="card shadow-sm mb-4">
    <div class="card-body p-0">
      <div class="map-container" ref="mapContainer" style="height: 600px; width: 100%"></div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';

const props = defineProps({
  restaurants: {
    type: Array,
    required: true
  },
  selectedRestaurant: {
    type: Object,
    default: null
  }
});

const emit = defineEmits(['select-restaurant', 'close-info-window']);
const mapContainer = ref(null);
const googleMap = ref(null);
const markers = ref([]);
const infoWindow = ref(null);

const initMap = () => {
  if (!window.google || !window.google.maps) {
    console.error('Google Maps API not loaded');
    return;
  }
  
  // Bangkok coordinates as default center
  const center = { lat: 13.8055, lng: 100.5312 };
  
  googleMap.value = new window.google.maps.Map(mapContainer.value, {
    center,
    zoom: 12,
    mapTypeControl: false,
    streetViewControl: false,
    fullscreenControl: true,
    styles: [
      {
        featureType: 'poi',
        elementType: 'labels',
        stylers: [{ visibility: 'off' }]
      }
    ]
  });
  
  infoWindow.value = new window.google.maps.InfoWindow();
  infoWindow.value.addListener('closeclick', () => {
    emit('close-info-window');
  });
  
  updateMarkers();
};

const updateMarkers = () => {
  // Clear existing markers
  markers.value.forEach(marker => marker.setMap(null));
  markers.value = [];
  
  // Add new markers
  props.restaurants.forEach(restaurant => {
    const marker = new window.google.maps.Marker({
      position: {
        lat: restaurant.location.lat,
        lng: restaurant.location.lng
      },
      map: googleMap.value,
      title: restaurant.name,
      animation: window.google.maps.Animation.DROP
    });
    
    marker.addListener('click', () => {
      emit('select-restaurant', restaurant);
    });
    
    markers.value.push(marker);
  });
};

const updateInfoWindow = () => {
  if (!props.selectedRestaurant || !infoWindow.value) return;
  
  const content = `
    <div class="info-window">
      <h5 class="mb-1">${props.selectedRestaurant.name}</h5>
      <p class="mb-1 text-muted">${props.selectedRestaurant.address}</p>
      <div class="d-flex align-items-center mb-1">
        <span class="badge bg-primary me-2">${props.selectedRestaurant.cuisine}</span>
        <small>${props.selectedRestaurant.rating}/5 ⭐</small>
      </div>
      <p class="mb-0 small">${props.selectedRestaurant.openingHours}</p>
    </div>
  `;
  
  infoWindow.value.setContent(content);
  infoWindow.value.setPosition({
    lat: props.selectedRestaurant.location.lat,
    lng: props.selectedRestaurant.location.lng
  });
  
  infoWindow.value.open(googleMap.value);
};

const loadGoogleMapsScript = () => {
  if (window.google && window.google.maps) {
    initMap();
    return;
  }
  
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;
  const script = document.createElement('script');
  script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places`;
  script.async = true;
  script.defer = true;
  script.onload = initMap;
  document.head.appendChild(script);
};

onMounted(() => {
  loadGoogleMapsScript();
});

watch(() => props.restaurants, updateMarkers, { deep: true });
watch(() => props.selectedRestaurant, updateInfoWindow);
</script>

<style scoped>
.info-window {
  padding: 8px;
  max-width: 250px;
}
</style>
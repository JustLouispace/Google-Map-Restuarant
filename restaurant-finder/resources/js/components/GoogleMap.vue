<template>
  <div class="card shadow-sm mb-4">
    <div class="card-body p-0">
      <div v-if="mapError" class="alert alert-danger m-3">
        {{ mapError }}
      </div>
      <div class="map-container" ref="mapContainer" style="height: 600px; width: 100%"></div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';

const props = defineProps({
  restaurants: {
    type: Array,
    default: () => []
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
const mapError = ref('');

const initMap = () => {
  try {
    console.log('Initializing map...');
    console.log('Google object:', window.google);
    console.log('Maps object:', window.google?.maps);
    
    if (!window.google || !window.google.maps) {
      mapError.value = 'Google Maps API not loaded. Please check your API key and internet connection.';
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
    });
    
    infoWindow.value = new window.google.maps.InfoWindow();
    
    if (infoWindow.value) {
      infoWindow.value.addListener('closeclick', () => {
        emit('close-info-window');
      });
    }
    
    updateMarkers();
  } catch (error) {
    console.error('Error initializing map:', error);
    mapError.value = `Error initializing map: ${error.message}`;
  }
};

const updateMarkers = () => {
  try {
    if (!googleMap.value) return;
    
    // Clear existing markers
    if (markers.value && Array.isArray(markers.value)) {
      markers.value.forEach(marker => {
        if (marker) marker.setMap(null);
      });
    }
    markers.value = [];
    
    // Add new markers
    if (props.restaurants && Array.isArray(props.restaurants)) {
      props.restaurants.forEach(restaurant => {
        if (!restaurant || !restaurant.location) return;
        
        const marker = new window.google.maps.Marker({
          position: {
            lat: restaurant.location.lat,
            lng: restaurant.location.lng
          },
          map: googleMap.value,
          title: restaurant.name
        });
        
        marker.addListener('click', () => {
          emit('select-restaurant', restaurant);
        });
        
        markers.value.push(marker);
      });
    }
  } catch (error) {
    console.error('Error updating markers:', error);
  }
};

const updateInfoWindow = () => {
  try {
    if (!props.selectedRestaurant || !infoWindow.value || !googleMap.value) return;
    
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
  } catch (error) {
    console.error('Error updating info window:', error);
  }
};

const loadGoogleMapsScript = () => {
  return new Promise((resolve, reject) => {
    try {
      // If Google Maps is already loaded
      if (window.google && window.google.maps) {
        console.log('Google Maps already loaded');
        resolve();
        return;
      }
      
      const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;
      console.log('Loading Google Maps with API Key:', apiKey ? 'Key exists' : 'No key found');
      
      // Create script element
      const script = document.createElement('script');
      script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places`;
      script.async = true;
      script.defer = true;
      
      script.onload = () => {
        console.log('Google Maps script loaded successfully');
        resolve();
      };
      
      script.onerror = (error) => {
        console.error('Google Maps script failed to load:', error);
        reject(new Error('Failed to load Google Maps API'));
      };
      
      // Add script to document
      document.head.appendChild(script);
    } catch (error) {
      console.error('Error in loadGoogleMapsScript:', error);
      reject(error);
    }
  });
};

onMounted(async () => {
  try {
    await loadGoogleMapsScript();
    initMap();
  } catch (error) {
    console.error('Failed to initialize map:', error);
    mapError.value = `Failed to load Google Maps: ${error.message}`;
  }
});

// Only watch for changes if props.restaurants is defined
watch(() => props.restaurants, (newVal) => {
  if (newVal && Array.isArray(newVal) && googleMap.value) {
    updateMarkers();
  }
}, { deep: true });

// Only watch for changes if props.selectedRestaurant is defined
watch(() => props.selectedRestaurant, (newVal) => {
  if (newVal && googleMap.value) {
    updateInfoWindow();
  }
});
</script>

<style scoped>
.info-window {
  padding: 8px;
  max-width: 250px;
}

.map-container {
  background-color: #f8f9fa;
}
</style>
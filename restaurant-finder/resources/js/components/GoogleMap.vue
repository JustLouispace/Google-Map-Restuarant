<template>
  <div class="card shadow-sm mb-4">
    <div class="card-body p-0">
      <!-- Show error if map fails to load -->
      <div v-if="mapError" class="alert alert-danger m-3">
        {{ mapError }}
      </div>
      <!-- Map container -->
      <div class="map-container" ref="mapContainer" style="height: 600px; width: 100%"></div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, onUnmounted, defineExpose } from 'vue';

// Props
const props = defineProps({
  restaurants: {
    type: Array,
    default: () => []
  },
  selectedRestaurant: {
    type: Object,
    default: null
  },
  searchLocation: {
    type: Object,
    default: null
  },
  searchMode: {
    type: String,
    default: 'keyword'
  },
  searchTerm: {
    type: String,
    default: ''
  },
  key: {
    type: Number,
    default: 0
  }
});

// Emits
const emit = defineEmits(['select-restaurant', 'close-info-window', 'location-selected']);

// Refs
const mapContainer = ref(null);
const googleMap = ref(null);
const markers = ref([]);
const infoWindow = ref(null);
const mapError = ref('');
const mapListeners = ref([]);
const locationMarker = ref(null);

/**
 * Initialize the Google Map
 */
const initMap = () => {
  try {
    console.log('Initializing map...');
    
    // Check if Google Maps API is loaded
    if (!window.google || !window.google.maps) {
      mapError.value = 'Google Maps API not loaded. Please check your API key and internet connection.';
      return;
    }
    
    // Bangkok coordinates as default center
    const center = { lat: 13.8055, lng: 100.5312 };
    
    // Create the map
    googleMap.value = new window.google.maps.Map(mapContainer.value, {
      center,
      zoom: 12,
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: true,
    });
    
    // Create info window
    infoWindow.value = new window.google.maps.InfoWindow();
    
    // Add info window close listener
    if (infoWindow.value) {
      const listener = infoWindow.value.addListener('closeclick', () => {
        emit('close-info-window');
      });
      mapListeners.value.push({ target: infoWindow.value, name: 'closeclick', listener });
    }
    
    // Add click listener for setting search location
    const clickListener = googleMap.value.addListener('click', (event) => {
      const lat = event.latLng.lat();
      const lng = event.latLng.lng();
      emit('location-selected', { lat, lng });
    });
    mapListeners.value.push({ target: googleMap.value, name: 'click', listener: clickListener });
    
    // Update markers
    updateMarkers();
    
    // Update search location marker if we have one
    if (props.searchLocation) {
      updateSearchLocation();
    }
  } catch (error) {
    console.error('Error initializing map:', error);
    mapError.value = `Error initializing map: ${error.message}`;
  }
};



/**
 * Completely recreate the map from scratch
 */
const recreateMapComponent = async () => {
  showMap.value = false;
  selectedRestaurant.value = null;
  searchLocation.value = null;
  searchLocationKey.value++;
  
  // Increase timeout to ensure DOM updates
  await new Promise(resolve => setTimeout(resolve, 100));
  
  showMap.value = true;
};

/**
 * Clear all markers from the map
 */
const clearAllMarkers = () => {
  // Clear restaurant markers
  if (markers.value && Array.isArray(markers.value)) {
    markers.value.forEach(marker => {
      if (marker) marker.setMap(null);
    });
    markers.value = [];
  }
  
  // Clear location marker
  if (locationMarker.value) {
    locationMarker.value.setMap(null);
    locationMarker.value = null;
  }
};

/**
 * Update markers on the map
 */
const updateMarkers = () => {
  try {
    if (!googleMap.value) return;
    
    // Clear existing restaurant markers
    if (markers.value && Array.isArray(markers.value)) {
      markers.value.forEach(marker => {
        if (marker) marker.setMap(null);
      });
      markers.value = [];
    }
    
    // Add new markers
    if (props.restaurants && Array.isArray(props.restaurants) && props.restaurants.length > 0) {
      console.log(`Adding ${props.restaurants.length} markers to map`);
      
      // Create bounds to fit all markers
      const bounds = new window.google.maps.LatLngBounds();
      
      props.restaurants.forEach(restaurant => {
        if (!restaurant || !restaurant.location) {
          console.warn('Restaurant missing location data:', restaurant);
          return;
        }
        
        const position = {
          lat: parseFloat(restaurant.location.lat),
          lng: parseFloat(restaurant.location.lng)
        };
        
        if (isNaN(position.lat) || isNaN(position.lng)) {
          console.warn('Invalid coordinates for restaurant:', restaurant);
          return;
        }
        
        // Create marker
        const marker = new window.google.maps.Marker({
          position,
          map: googleMap.value,
          title: restaurant.name,
          animation: window.google.maps.Animation.DROP
        });
        
        // Add click listener
        const clickListener = marker.addListener('click', () => {
          emit('select-restaurant', restaurant);
        });
        mapListeners.value.push({ target: marker, name: 'click', listener: clickListener });
        
        markers.value.push(marker);
        bounds.extend(position);
      });
      
      // Only adjust bounds if we have markers
      if (markers.value.length > 0) {
        // If we have a search location, include it in the bounds
        if (props.searchLocation) {
          bounds.extend(props.searchLocation);
        }
        
        googleMap.value.fitBounds(bounds);
        
        // Don't zoom in too far
        const listener = googleMap.value.addListener('idle', () => {
          if (googleMap.value.getZoom() > 16) {
            googleMap.value.setZoom(16);
          }
          window.google.maps.event.removeListener(listener);
        });
      }
    } else {
      console.log('No restaurants to display on map');
      
      // If we have a search location, center on it
      if (props.searchLocation) {
        googleMap.value.setCenter(props.searchLocation);
        googleMap.value.setZoom(14);
      }
    }
  } catch (error) {
    console.error('Error updating markers:', error);
    mapError.value = `Error updating map markers: ${error.message}`;
  }
};

/**
 * Update info window for selected restaurant
 */
const updateInfoWindow = () => {
  try {
    if (!props.selectedRestaurant || !googleMap.value) return;
    
    if (!props.selectedRestaurant.location) {
      console.warn('Selected restaurant has no location data:', props.selectedRestaurant);
      return;
    }
    
    // Create a new info window if needed
    if (!infoWindow.value) {
      infoWindow.value = new window.google.maps.InfoWindow();
      
      // Add info window close listener
      const listener = infoWindow.value.addListener('closeclick', () => {
        emit('close-info-window');
      });
      mapListeners.value.push({ target: infoWindow.value, name: 'closeclick', listener });
    }
    
    // Include distance in the info window if available
    const distanceInfo = props.selectedRestaurant.distanceText 
      ? `<p class="mb-1 small"><i class="bi bi-geo"></i> ${props.selectedRestaurant.distanceText}</p>` 
      : '';
    
    // Create info window content
    const content = `
      <div class="info-window">
        <h5 class="mb-1">${props.selectedRestaurant.name}</h5>
        <p class="mb-1 text-muted">${props.selectedRestaurant.address}</p>
        <div class="d-flex align-items-center mb-1">
          <span class="badge bg-primary me-2">${props.selectedRestaurant.cuisine}</span>
          <small>${props.selectedRestaurant.rating}/5 ⭐</small>
        </div>
        ${distanceInfo}
        <p class="mb-0 small">${props.selectedRestaurant.openingHours}</p>
      </div>
    `;
    
    // Set info window content and position
    infoWindow.value.setContent(content);
    infoWindow.value.setPosition({
      lat: parseFloat(props.selectedRestaurant.location.lat),
      lng: parseFloat(props.selectedRestaurant.location.lng)
    });
    
    // Open info window
    infoWindow.value.open(googleMap.value);
  } catch (error) {
    console.error('Error updating info window:', error);
    mapError.value = `Error showing restaurant details: ${error.message}`;
  }
};

/**
 * Update search location marker
 */
const updateSearchLocation = () => {
  try {
    if (!googleMap.value || !props.searchLocation) return;
    
    console.log('Updating search location marker:', props.searchLocation);
    
    // Clear existing location marker
    if (locationMarker.value) {
      locationMarker.value.setMap(null);
      locationMarker.value = null;
    }
    
    // Create new location marker
    locationMarker.value = new window.google.maps.Marker({
      position: props.searchLocation,
      map: googleMap.value,
      icon: {
        path: window.google.maps.SymbolPath.CIRCLE,
        scale: 10,
        fillColor: '#4285F4',
        fillOpacity: 1,
        strokeColor: '#ffffff',
        strokeWeight: 2
      },
      zIndex: 1000 // Ensure it's above restaurant markers
    });
    
    // Center map on search location
    googleMap.value.setCenter(props.searchLocation);
  } catch (error) {
    console.error('Error updating search location:', error);
    mapError.value = `Error updating search location: ${error.message}`;
  }
};

/**
 * Load Google Maps script
 */
const loadGoogleMapsScript = () => {
  return new Promise((resolve, reject) => {
    try {
      // If Google Maps is already loaded
      if (window.google && window.google.maps) {
        console.log('Google Maps already loaded');
        resolve();
        return;
      }
      
      // Get API key from Laravel window object
      const apiKey = window.Laravel?.googleMapsApiKey;
      console.log('Loading Google Maps with API Key:', apiKey ? 'Key exists' : 'No key found');
      
      if (!apiKey) {
        reject(new Error('Google Maps API key not found. Please check your .env file.'));
        return;
      }
      
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

/**
 * Clean up event listeners to prevent memory leaks
 */
const cleanupListeners = () => {
  mapListeners.value.forEach(({ target, name, listener }) => {
    if (target && listener) {
      window.google.maps.event.removeListener(listener);
    }
  });
  mapListeners.value = [];
};

const recreateMap = () => {
  try {
    console.log('Completely recreating map...');
    
    // Clear existing elements first
    if (mapContainer.value) {
      mapContainer.value.innerHTML = '';
    }
    
    cleanupListeners();
    clearAllMarkers();
    
    // Reinitialize fresh map
    initMap();
  } catch (error) {
    console.error('Error recreating map:', error);
    mapError.value = `Error recreating map: ${error.message}`;
  }
};


// In GoogleMap.vue's onMounted
onMounted(async () => {
  try {
    await loadGoogleMapsScript();
    // Always recreate the map on mount
    recreateMap();
  } catch (error) {
    console.error('Failed to initialize map:', error);
    mapError.value = `Failed to load Google Maps: ${error.message}`;
  }
});

// When component is unmounted
onUnmounted(() => {
  cleanupListeners();
  clearAllMarkers();
});

// Watch for changes in restaurants
watch(() => props.restaurants, (newVal) => {
  if (newVal && Array.isArray(newVal) && googleMap.value) {
    console.log('Restaurants changed, updating markers');
    updateMarkers();
  }
}, { deep: true });

// Watch for changes in selected restaurant
watch(() => props.selectedRestaurant, (newVal) => {
  if (newVal && googleMap.value) {
    console.log('Selected restaurant changed, updating info window');
    updateInfoWindow();
  }
});

// Watch for changes in search location
watch(() => props.searchLocation, (newVal, oldVal) => {
  console.log('Search location changed:', oldVal, '->', newVal);
  if (googleMap.value) {
    // Completely recreate the map when search location changes
    recreateMap();
  }
}, { deep: true });



// Expose methods to parent component
defineExpose({
  recreateMap
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
